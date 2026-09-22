<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class IdempotencyConflictException extends RuntimeException
{
    /**
     * Render the exception as an API conflict response.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function render()
    {
        return new JsonResponse([
            'message' => 'The idempotency key was already used with a different request.',
        ], 409);
    }
}
