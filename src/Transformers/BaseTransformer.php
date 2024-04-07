<?php

namespace Devespresso\DataFiltering\Transformers;

use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

abstract class BaseTransformer
{
    /**
     * Wrapper
     *
     * @var string
     */
    public $wrapper = 'data';

    /**
     * Format
     *
     * @var array
     */
    protected $formats = [];

    /**
     * Rename props
     *
     * @var array
     */
    protected $renames = [];

    /**
     * Formatters
     *
     * @var array
     */
    protected $formatters = [];

    /**
     * Custom Attributes
     *
     * @var array
     */
    protected $customAttributes = [];

    /**
     * Default Attributes
     *
     * @var array
     */
    protected $defaults = [];

    /**
     * Guarded attributes
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * Transforms the data
     *
     * @param  mixed  $data
     */
    public function transformData($data, ?string $format = null): array
    {
        return $this->formatModel($data, $this->getFormatting($format));
    }

    /**
     * Gets the current format
     */
    public function getFormatting(?string $formatMethod = null): array
    {
        $method = Str::afterLast(Route::currentRouteAction(), '@');

        $mainFormat = $this->formats['*'] ?? [];

        $requireFormat = $formatMethod ?? $method;

        if (array_key_exists($requireFormat, $this->formats)) {
            return array_merge(
                $mainFormat,
                $this->formats[$requireFormat]
            );
        }
        // If the require format is prefixed by a underscore it wont merge with the main format
        $requireFormat = config('devespressoApi.transformers.prefixes.unmerged_format') . $requireFormat;

        if (array_key_exists($requireFormat, $this->formats)) {
            return $this->formats[$requireFormat];
        }

        return $mainFormat;
    }

    /**
     * Formats the models
     *
     * @param  array  $attributes
     */
    protected function formatModel(Collection|Model|Paginator|null $collection, $attributes, array $currentKey = []): ?array
    {
        if (!$collection) {
            return null;
        }

        if (!$collection instanceof Model) {
            return optional(
                optional($collection)->map(
                    function ($model) use ($attributes, $currentKey) {
                        return $this->formatModel(
                            $model,
                            $attributes,
                            $currentKey
                        );
                    }
                )
            )->toArray();
        }

        $modelFormatted = [];

        foreach ($attributes as $key => $attribute) {
            if (is_iterable($attribute)) {
                $renamedKey = $this->renameKey($key, $currentKey);

                $currentKey[] = $key;

                $modelFormatted[$renamedKey] = $this->formatModel(
                    $collection->$key,
                    $attribute,
                    $currentKey
                );

                continue;
            }

            if (
                $this->isAttributeGuarded($attribute, $currentKey, $collection) ||
                Str::startsWith($attribute, config('devespressoApi.transformers.prefixes.hidden_attributes'))
            ) {
                continue;
            }

            // If table is present we should remove it
            $attribute = Str::after($attribute, '.');

            $isCustomAttribute = Str::startsWith(
                $attribute,
                config('devespressoApi.transformers.prefixes.custom_attributes')
            );

            $attribute = Str::replaceFirst(
                config('devespressoApi.transformers.prefixes.custom_attributes'),
                '',
                $attribute
            );

            $modelFormatted[$this->renameKey($attribute, $currentKey)] = $this->applyDefaultValue(
                $this->findFormatters(
                    $attribute,
                    $this->checkForCustomAttributeAndGetValue(
                        $attribute,
                        $collection,
                        $isCustomAttribute
                    ),
                    $currentKey
                ),
                $attribute,
                $currentKey,
                $collection
            );
        }

        return $modelFormatted;
    }

    /**
     * Renames the key
     */
    protected function renameKey(string $attribute, array $currentKey = []): string
    {
        // If theres nothing to rename we just return the current key
        if (!count($this->renames)) {
            return $attribute;
        }

        // We check if we have any global keys that we need to rename
        if (array_key_exists('*', $this->renames) && array_key_exists($attribute, $this->renames['*'])) {
            return $this->renames['*'][$attribute];
        }

        $currentKey[] = $attribute;

        $uniqueKey = implode('.', $currentKey);

        if (array_key_exists($uniqueKey, $this->renames)) {
            return $this->renames[$uniqueKey];
        }

        return $attribute;
    }

    /**
     * Run formatters
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function findFormatters(string $attribute, $value, array $currentKey = [])
    {
        // If there are nothing in the formatters we just return the value
        if (!count($this->formatters)) {
            return $value;
        }

        // We check global formatters if we have them we run the formatters
        if (array_key_exists('*', $this->formatters) && array_key_exists($attribute, $this->formatters['*'])) {
            $globalFormatters = $this->formatters['*'][$attribute];
            $value = $this->runFormatters($value, $globalFormatters);
        }

        $currentKey[] = $attribute;

        $uniqueKey = implode('.', $currentKey);

        if (!array_key_exists($uniqueKey, $this->formatters)) {
            return $value;
        }

        $formatters = $this->formatters[$uniqueKey];

        return $this->runFormatters($value, $formatters);
    }

    /**
     * Runs formatters
     *
     * @param  mixed  $value
     * @param  mixed  $formatters
     */
    protected function runFormatters($value, $formatters): mixed
    {
        if (is_string($formatters)) {
            return $this->$formatters($value);
        }

        foreach ($formatters as $method => $params) {
            if (is_array($params) && method_exists($this, $method)) {
                $value = $this->$method($value, ...$params);

                continue;
            }

            $value = $this->$params($value);
        }

        return $value;
    }

    /**
     * Checks if the attribute is custom
     *
     * @param  mixed  $model
     * @return void
     */
    protected function checkForCustomAttributeAndGetValue(string $attribute, $model, bool $isCustomAttribute)
    {
        if ($isCustomAttribute && array_key_exists($attribute, $this->customAttributes)) {
            return $this->{$this->customAttributes[$attribute]}($model);
        }

        return $model->$attribute;
    }

    /**
     * Checks if we need to apply defaults
     *
     * @param  mixed  $value
     * @param  string  $uniqueKey
     * @return mixed
     */
    public function applyDefaultValue(
        $value,
        string $attribute,
        array $currentKey,
        $model
    ) {
        if (!is_null($value)) {
            return $value;
        }

        // We check global defaults if we have it, set the value
        if (
            array_key_exists('*', $this->defaults) &&
            array_key_exists($attribute, $this->defaults['*'])
        ) {
            $newValue = $this->defaults['*'][$attribute];
            if (is_string($newValue) && method_exists($this, $newValue)) {
                return $this->$newValue($model);
            }

            return $newValue;
        }

        $currentKey[] = $attribute;

        $uniqueKey = implode('.', $currentKey);

        if (array_key_exists($uniqueKey, $this->defaults)) {
            $newValue = $this->defaults[$uniqueKey];
            if (is_string($newValue) && method_exists($this, $newValue)) {
                return $this->$newValue($model);
            }

            return $newValue;
        }

        return $value;
    }

    /**
     * Check for guarded prop
     */
    protected function isAttributeGuarded(string $attribute, array $currentKey, $model): bool
    {
        // We check global defaults if we have it, set the value
        if (
            array_key_exists('*', $this->guarded) &&
            array_key_exists($attribute, $this->guarded['*']) &&
            method_exists($this, $this->guarded['*'][$attribute])
        ) {
            return $this->{$this->guarded['*'][$attribute]}($model);
        }

        $currentKey[] = $attribute;

        $uniqueKey = implode('.', $currentKey);

        if (
            array_key_exists($uniqueKey, $this->guarded) &&
            method_exists($this, $this->guarded[$uniqueKey])
        ) {
            return $this->{$this->guarded[$uniqueKey]}($model);
        }

        return false;
    }
}
