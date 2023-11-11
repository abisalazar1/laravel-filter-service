<?php

namespace Devespresso\DataFiltering\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

abstract class BaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
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
     */
    public function rules(): array
    {
        $rules = [];

        $method = $this->guessMethodFor('Rules');

        if (method_exists($this, $method)) {
            $rules = $this->$method();
        }

        if (array_key_exists($this->getRouteAction(), $this->actionsRules())) {
            $rules = array_merge(
                $this->getActionRule($this->getRouteAction()),
                $rules
            );
        }

        return $rules;
    }

    /**
     * Index rules
     */
    protected function indexRules(): array
    {
        return [
            'sort' => ['string'],
            'per_page' => ['integer', 'min:1', 'max:100'],
            'with_pages' => ['boolean'],
        ];
    }

    /**
     * Guess the method to trigger
     */
    protected function guessMethodFor(string $ending): string
    {
        return $this->getRouteAction().$ending;
    }

    /**
     * Action
     */
    protected function getRouteAction(): string
    {
        return (string) Str::of(Route::currentRouteAction())->afterLast('@');
    }

    /**
     * All rules
     */
    abstract public function actionsRules(): array;

    /**
     * Gets specific action rules
     */
    protected function getActionRule(string $action): array
    {
        return $this->actionsRules()[$action] ?? [];
    }
}
