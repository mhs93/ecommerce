<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Http\Response;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Throwable $exception
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $exception)
    {
        // Handle known HTTP exceptions
        if ($this->isHttpException($exception)) {
            $statusCode = $exception instanceof HttpException ? $exception->getStatusCode() : 500;
            $title = $exception instanceof HttpException ? $exception->getMessage() : 'Internal Server Error';

            return response()->json([
                'status' => false,
                'code' => $statusCode,
                'error' => Response::$statusTexts[$statusCode],
                'msg' => $title,
            ], 200);
        }

        // Handle other exceptions
        return response()->json([
            'status' => false,
            'code' => 500,
            'error' => 'Internal Server Error',
            'msg' => $exception->getMessage() . ' in line ' . $exception->getLine(),
        ], 200);
    }
}
