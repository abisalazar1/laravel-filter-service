<?php

namespace Abix\DataFiltering\Controllers;

use Illuminate\Http\Response;

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
     * @var Transfomer
     */
    protected $transformer = null;

    /**
     * Sets http code
     *
     * @param  integer $code
     * @param  string  $message
     * @return self
     */
    public function setCode(int $code, ?string $message = null)
    {
        $this->code = $code;

        if ($this->code >= 400) {
            $this->hasErrors = true;
            $this->status = 'error';

            $this->data = [
                'message' => $this->getErrorMessage($message),
            ];
        }

        return $this;
    }

    /**
     * Sets the transformer
     *
     * @param string $transformer
     * @return self
     */
    public function setTransformer(string $transformer)
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
    public function setData($data, $method = null)
    {
        $this->data = resolve($this->transformer)->transformData($data, $method);

        return $this;
    }

    /**
     * Sends the response
     *
     * @param  array|null $response
     * @return Response
     */
    public function respond(?array $response = [])
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
     * @return void
     */
    public function getErrorMessage(?string $message)
    {
        if ($message) {
            return $message;
        }

        return Response::$statusTexts[$this->code];
    }
}
