<?php

namespace Abix\DataFiltering\Transformers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
     * Rename props
     *
     * @var array
     */
    protected $rename = [];

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
    protected $defaultAttributes = [];

    /**
     * Protected Attributes
     *
     * @var array
     */
    protected $protectedAttributes = [];

    /**
     * Format
     *
     * @var array
     */
    protected $format = [
        '*' => [],
    ];

    /**
     * Sets the data
     *
     * @param array|Model|Collection|LengthAwarePaginator $data
     * @return self
     */
    public function transformData($data, string $formatMethod = null)
    {
        return $this->format($data, $formatMethod);
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

        $format = $this->format['*'] ?? [];

        if ($formatMethod && array_key_exists($formatMethod, $this->format)) {
            return array_merge($this->format[$formatMethod], $format);
        }

        if ($method && array_key_exists($method, $this->format)) {
            return array_merge($this->format[$method], $format);
        }

        return [];
    }

    /**
     * Formats the model
     *
     * @param Model|Collection $model
     * @return array
     */
    protected function format($model, ?string $formatMethod = null): array
    {
        return $this->formatModel($model, $this->getFormatting($formatMethod));
    }

    /**
     * Formats the model
     *
     * @param Model|Collection $collection
     * @param array $attributes
     * @return array|null
     */
    protected function formatModel($collection, $attributes): ?array
    {
        if (!$collection instanceof Model) {
            return optional(
                optional($collection)->map(function ($model) use ($attributes) {
                    return $this->formatModel($model, $attributes);
                })
            )->toArray();
        }

        $modelFormatted = [];

        foreach ($attributes as $key => $attribute) {
            if (is_iterable($attribute)) {
                [$rename, $key] = $this->extractRenameAndValue($key);
                $modelFormatted[$rename] = $this->formatModel($collection->$key, $attribute);
                continue;
            }
            [$rename, $value] = $this->extractRenameAndValue($attribute);

            $modelFormatted[$rename] = $this->runFormatters($key, $collection->$value);
        }

        return $modelFormatted;
    }

    /**
     * Keys
     *
     * @param string $key
     * @return array
     */
    protected function extractRenameAndValue(string $key): array
    {
        return array_pad(explode(':', $key), 2, $key);
    }

    /**
     * Run formatters
     *
     * @param string $key
     * @param mix $value
     * @return mix
     */
    protected function runFormatters(string $key, $value)
    {
        if (!array_key_exists($key, $this->formatters)) {
            return $value;
        }

        $methods = $this->formatters[$key];

        if (!is_iterable($methods)) {
            return $this->$methods($value);
        }

        foreach ($methods as $method) {
            $value = $this->$method($value);
        }

        return $value;
    }
}
