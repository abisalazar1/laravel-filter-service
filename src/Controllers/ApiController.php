<?php

namespace Abix\DataFiltering\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Abix\DataFiltering\Transformers\BaseTransformer;

class ApiController
{
    /**
     * Status
     *
     * @var string
     */
    protected $status = 'success';

    /**
     * Status Code
     *
     * @var integer
     */
    protected $code = 200;

    /**
     * Data
     *
     * @var array
     */
    protected $data = [];

    /**
     * has Error
     *
     * @var boolean
     */
    protected $hasErrors = false;

    /**
     * Transformer
     *
     * @var string
     */
    protected $transformer = null;

    /**
     * Sets http code
     *
     * @param  integer $code
     * @param  string  $message
     * @return self
     */
    protected function setCode(int $code, ?string $message = null): self
    {
        $this->code = $code;

        if ($this->code >= 400) {
            $this->hasErrors = true;
            $this->status = 'error';
            $this->data['message'] = $this->getErrorMessage($message);
        }

        return $this;
    }

    /**
     * Sets the transformer
     *
     * @param string $transformer
     * @return self
     */
    protected function setTransformer(string $transformer): self
    {
        $this->transformer = $transformer;

        return $this;
    }

    /**
     * Sets the data
     *
     * @param  mix  $data
     * @return self
     */
    protected function setData($data, $method = null): self
    {
        $this->data = $this->guessTrasformer()
            ->transformData($data, $method);

        return $this;
    }

    /**
     * Sends the response
     *
     * @param  array|null $response
     * @return JsonResponse
     */
    protected function respond(?array $response = []): JsonResponse
    {
        $data = array_merge([
            'code' => $this->code,
            'status' => $this->status,
        ], $response, $this->data);

        return response()->json(
            $data,
            $this->code
        );
    }

    /**
     * Gets the status message
     *
     * @param  string $message
     * @return string
     */
    protected function getErrorMessage(?string $message): string
    {
        if ($message) {
            return $message;
        }

        return Response::$statusTexts[$this->code];
    }

    /**
     * Resolves the transformer
     *
     * @return void
     */
    protected function guessTrasformer(): BaseTransformer
    {
        if ($this->transformer) {
            return resolve($this->transformer);
        }

        $transformer = (string) Str::of(class_basename($this))
            ->prepend('App\Transformers\\')
            ->replace('Controller', 'Transformer');

        return resolve($transformer);
    }
}
