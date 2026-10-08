<?php

namespace App\Http\Middleware;

class AuthorizationException extends \Exception
{
    public int $statusCode;
    public array $response;

    public function __construct(string $message, int $statusCode = 403)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->response = [
            'success' => false,
            'status' => 'error',
            'message' => $message,
        ];
    }
}
