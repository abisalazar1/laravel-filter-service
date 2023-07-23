<?php

namespace Abix\DataFiltering\Exceptions;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthorisationException extends Exception
{
    /**
     * Message
     *
     * @var string
     */
    protected $message;

    /**
     * Status code
     *
     * @var int
     */
    protected $code;

    /**
     * Constructor
     */
    public function __construct(
        string $message = null,
        ?int $code = 403,
        ?string $view = 'error.error'
    ) {
        $this->message = $message;
        $this->code = $code;
        $this->view = $view;
    }

    /**
     * render the request
     *
     * @return Response
     */
    public function render(Request $request)
    {
        $message = $this->getErrorMessage($this->message);

        if (! $request->acceptsJson()) {
            abort_if(! $this->view, $this->code, $message);

            return view($this->view, [
                'code' => $this->code,
                'message' => $this->message,
            ]);
        }

        return response([
            'code' => $this->code,
            'status' => 'error',
            'message' => $message,
        ], $this->code);
    }

    /**
     * Gets the status text
     *
     * @param  string  $message
     */
    public function getErrorMessage(string $message = null): string
    {
        if ($message) {
            return $message;
        }

        return Response::$statusTexts[$this->code];
    }
}
