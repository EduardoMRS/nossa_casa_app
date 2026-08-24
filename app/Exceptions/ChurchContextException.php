<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class ChurchContextException extends RuntimeException implements ShouldntReport
{
    public function __construct(
        public readonly string $errorCode,
        public readonly string $messageKey,
        public readonly int $statusCode,
    ) {
        parent::__construct($messageKey);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => __($this->messageKey),
            'code' => $this->errorCode,
        ], $this->statusCode);
    }
}
