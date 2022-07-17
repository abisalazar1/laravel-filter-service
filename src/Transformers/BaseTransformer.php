<?php

namespace Abix\DataFiltering\Transformers;

use Exception;
use Illuminate\Database\Eloquent\Model;
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
     * @param mixed $data
     * @param string|null $format
     * @return void
     */
    public function transformData($data, ?string $format = null)
    {
        return $this->formatModel($data, $this->getFormatting($format));
    }

    /**
     * Gets format
     *
     * @param string|null $formatMethod
     * @return array
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
        $requireFormat = '_' . $requireFormat;

        if (array_key_exists($requireFormat, $this->formats)) {
            return $this->formats[$requireFormat];
        }

        return $mainFormat;
    }

    /**
     * Formats the model
     *
     * @param Model|Collection $collection
     * @param array $attributes
     * @param array $currentKey
     * @return array|null
     */
    protected function formatModel($collection, $attributes, array $currentKey = []): ?array
    {
        if (!$collection instanceof Model) {
            return optional(
                optional($collection)->map(function ($model) use ($attributes, $currentKey) {
                    return $this->formatModel(
                        $model,
                        $attributes,
                        $currentKey
                    );
                })
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
                Str::startsWith($attribute, ':')
            ) {
                continue;
            }

            $modelFormatted[$this->renameKey($attribute, $currentKey)] = $this->applyDefaultValue(
                $this->findFormatters(
                    $attribute,
                    $this->checkForCustomAttributeAndGetValue(
                        $attribute,
                        $collection
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
     * Keys
     *
     * @param string $key
     * @return array
     */
    protected function renameKey(string $key, array $currentKey = []): string
    {
        // If theres nothing to rename we just return the current key
        if (!count($this->renames)) {
            return $key;
        }

        // We check if we have any global keys that we need to rename
        if (array_key_exists('*', $this->renames) && array_key_exists($key, $this->renames['*'])) {
            return $this->renames['*'][$key];
        }

        $currentKey[] = $key;

        $uniqueKey = implode('.', $currentKey);

        if (array_key_exists($uniqueKey, $this->renames)) {
            return $this->renames[$uniqueKey];
        }

        return $key;
    }

    /**
     * Run formatters
     *
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    protected function findFormatters(string $key, $value, array $currentKey = [])
    {
        // If there are nothing in the formatters we just return the value
        if (!count($this->formatters)) {
            return $value;
        }
        // We check global formatters if we have them we run the formatters
        if (array_key_exists('*', $this->formatters) && array_key_exists($key, $this->formatters['*'])) {
            $globalFormatters = $this->formatters['*'][$key];
            $value = $this->runFormatters($value, $globalFormatters);
        }

        $currentKey[] = $key;

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
     * @param mixed $value
     * @param mixed $formatters
     * @return mixed
     */
    protected function runFormatters($value, $formatters)
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
     * If it need to be a custom property it will trigger the function for it
     *
     * @param string $key
     * @param Model $model
     * @return mixed
     */
    protected function checkForCustomAttributeAndGetValue(string $key, $model)
    {
        if (array_key_exists($key, $this->customAttributes)) {
            return $this->{$this->customAttributes[$key]}($model);
        }

        return $model->$key;
    }

    /**
     * Checks if we need to apply defaults
     *
     * @param mixed $value
     * @param string $key
     * @param string $uniqueKey
     * @return mixed
     */
    public function applyDefaultValue(
        $value,
        string $key,
        array $currentKey,
        $model
    ) {
        if (!is_null($value)) {
            return $value;
        }

        // We check global defaults if we have it, set the value
        if (
            array_key_exists('*', $this->defaults) &&
            array_key_exists($key, $this->defaults['*'])
        ) {
            $newValue = $this->defaults['*'][$key];
            if (is_string($newValue) && method_exists($this, $newValue)) {
                return $this->$newValue($model);
            }

            return $newValue;
        }

        $currentKey[] = $key;

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
     *
     * @return bool
     */
    protected function isAttributeGuarded(string $key, array $currentKey, $model): bool
    {
        // We check global defaults if we have it, set the value
        if (
            array_key_exists('*', $this->guarded) &&
            array_key_exists($key, $this->guarded['*']) &&
            method_exists($this, $this->guarded['*'][$key])

        ) {
            return $this->{$this->guarded['*'][$key]}($model);
        }

        $currentKey[] = $key;

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
