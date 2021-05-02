<?php

namespace Abix\DataFiltering\Transformers;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Route;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

abstract class BaseTransformer
{
    /**
     * Wrapper
     *
     * @var string
     */
    protected $wrapper = 'data';

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
     * FormatterMethod
     *
     * @var string
     */
    protected $formatMethod = null;

    /**
     * Sets the data
     *
     * @param array|Model|Collection|LengthAwarePaginator $data
     * @return self
     */
    public function transformData($data, string $formatMethod = null)
    {
        $pagination = [];

        $this->formatMethod = $formatMethod;

        if ($data instanceof LengthAwarePaginator) {
            $pagination['pagination'] = $this->getPagination($data);
        }

        return array_merge([
            $this->wrapper => $this->format($data),
        ], $pagination);
    }


    /**
     * Gets the pagination
     *
     * @param LengthAwarePaginator $data
     * @return array
     */
    protected function getPagination(LengthAwarePaginator $data): array
    {
        return [
            'current_page' => $data->currentPage(),
            'from' => $data->firstItem(),
            'last_page' => $data->lastPage(),
            'next_page_url' => $data->nextPageUrl(),
            'per_page' => $data->perPage(),
            'prev_page_url' => $data->previousPageUrl(),
            'to' => $data->lastItem(),
            'total' => $data->total(),
        ];
    }

    /**
     * Formats the model
     *
     * @param Model|Collection $model
     * @return array
     */
    protected function format($model): array
    {
        $format = $this->undotAttributes($this->getFormatting());

        return $this->formatModel($model, $format);
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
            return $collection->map(function ($model) use ($attributes) {
                return $this->formatModel($model, $attributes);
            })->toArray();
        }

        $modelFormatted = [];

        foreach ($attributes as $key => $value) {
            if (is_iterable($value)) {
                $modelFormatted[$this->renameKey($key)] = $this->formatModel($collection->$key, $value);
                continue;
            }

            $modelFormatted[$this->renameKey($key)] = $this->runFormatters($key, $collection->$value);
        }

        return $modelFormatted;
    }

    /**
     * Undot the formatting for the tranformer
     *
     * @param array $attributes
     * @return array
     */
    protected function undotAttributes(array $attributes): array
    {
        $container = [];

        foreach ($attributes as $attribute) {
            Arr::set($container, $attribute, Str::afterLast($attribute, '.'));
        }

        return $container;
    }

    /**
     * Gets the formatter
     *
     * @return array
     */
    protected function getFormatting(): array
    {
        $method =  Str::afterLast(Route::currentRouteAction(), '@');

        if (!$method && !$this->formatMethod) {
            return $this->show();
        }

        if ($this->formatMethod) {
            return $this->{$this->formatMethod}();
        }

        if (!method_exists($this, $method)) {
            return $this->show();
        }

        return $this->$method();
    }

    /**
     * Renames they key
     *
     * @param string $key
     * @return string
     */
    protected function renameKey(string $key): string
    {
        if (array_key_exists($key, $this->rename)) {
            return $this->rename[$key];
        }

        return $key;
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

    /**
     * Show format
     *
     * @return array
     */
    protected function show(): array
    {
        return [];
    }
}
