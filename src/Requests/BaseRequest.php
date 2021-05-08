<?php

namespace Abix\DataFiltering\Requests;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Http\FormRequest;

abstract class BaseRequest extends FormRequest
{
    /**
     * Rules to append
     *
     * @var array
     */
    protected $appendRules = [];

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        $method = $this->guessMethodFor('Auth');

        if (method_exists($this, $method)) {
            return $this->$method();
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        $rules = [];

        $method = $this->guessMethodFor('Rules');

        if (method_exists($this, $method)) {
            $rules = $this->$method();
        }

        if (array_key_exists($method, $this->appendRules)) {
            $rules = array_merge(
                $this->appendRules[$method],
                $rules
            );
        }

        return $rules;
    }

    /**
     * Index rules
     *
     * @return array
     */
    protected function indexRules(): array
    {
        return [
            'sort' => ['string'],
            'per_page' => ['integer', 'min:5', 'max:100'],
        ];
    }

    /**
     * Guess the method to trigger
     *
     * @param string $ending
     * @return string
     */
    protected function guessMethodFor(string $ending): string
    {
        return (string) Str::of(Route::currentRouteAction())
            ->afterLast('@')
            ->append($ending);
    }
}
