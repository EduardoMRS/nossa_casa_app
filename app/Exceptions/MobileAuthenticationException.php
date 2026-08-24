<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class MobileAuthenticationException extends RuntimeException implements ShouldntReport
{
    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public function __construct(
        public readonly string $errorCode,
        public readonly string $messageKey,
        public readonly int $statusCode = 401,
        public readonly array $errors = [],
    ) {
        parent::__construct($messageKey);
    }

    public function render(Request $request): JsonResponse
    {
        $payload = [
            'message' => __($this->messageKey),
            'code' => $this->errorCode,
        ];

        if ($this->errors !== []) {
            $payload['errors'] = $this->errors;
        }

        return response()->json($payload, $this->statusCode);
    }
}
