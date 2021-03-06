<?php

namespace Abix\DataFiltering\Controllers;

use Abix\DataFiltering\Repositories\BaseRepository;
use Abix\DataFiltering\Transformers\BaseTransformer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

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
     * Pagination
     *
     * @var array
     */
    protected $pagination = [];

    /**
     * Custom message for the status
     *
     * @var string
     */
    protected $statusMessage = '';

    /**
     * Transformer
     *
     * @var string
     */
    protected $transformer = null;

    /**
     * Repository
     *
     * @var BaseRepository
     */
    protected $repository = null;

    /**
     * Guesses the repository
     */
    public function __construct()
    {
        $this->repository = $this->guessRepository();
    }

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
            $this->status = 'error';
        }

        $this->statusMessage = $message;

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
     * Sets the main data
     *
     * @param Collection|Model $data
     * @param string $wrapper
     * @param string $method
     * @return self
     */
    protected function setData(
        $data,
        string $wrapper = null,
        string $format = null
    ): self {
        $transformer = $this->guessTransformer();

        if (!$wrapper) {
            $wrapper = $transformer->wrapper;
        }

        $this->data[$wrapper] = $transformer->transformData($data, $format);

        if ($data instanceof LengthAwarePaginator) {
            $this->pagination['pagination'] = $this->getPagination($data);
        }

        return $this;
    }

    /**
     * Sends the response
     *
     * @param array|null $response
     * @param boolean $overide
     * @return JsonResponse
     */
    protected function respond(
        ?array $response = [],
        bool $overide = true
    ): JsonResponse {
        $method = $overide ? 'array_merge' : 'array_merge_recursive';

        return response()->json(
            $method(
                [
                    'code' => $this->code,
                    'status' => $this->status,
                    'message' => $this->getStatusMessage(),
                ],
                $this->data,
                $response,
                $this->pagination
            ),
            $this->code
        );
    }

    /**
     * Gets the status message
     *
     * @return string
     */
    protected function getStatusMessage(): string
    {
        if ($this->statusMessage) {
            return $this->statusMessage;
        }

        return Response::$statusTexts[$this->code];
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
     * Resolves the transformer
     *
     * @return BaseTransformer
     */
    protected function guessTransformer(): BaseTransformer
    {
        if ($this->transformer) {
            return resolve($this->transformer);
        }

        $transformer = (string) Str::of(class_basename($this))
            ->prepend(config('apix.paths.transformers'))
            ->replace('Controller', 'Transformer');

        return resolve($transformer);
    }

    /**
     * Repository
     *
     * @return BaseRepository
     */
    protected function guessRepository(): BaseRepository
    {
        if ($this->repository) {
            return resolve($this->repository);
        }
        $repository = (string) Str::of(class_basename($this))
            ->prepend(config('apix.paths.repositories'))
            ->replace('Controller', 'Repository');

        return resolve($repository);
    }
}
