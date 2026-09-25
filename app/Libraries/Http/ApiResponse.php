<?php

declare(strict_types=1);

namespace App\Libraries\Http;

use CodeIgniter\HTTP\ResponseInterface;

final class ApiResponse
{
    /** @param array<string, mixed> $data */
    public static function success(ResponseInterface $response, array $data, string $requestId, int $status = 200): ResponseInterface
    {
        return $response->setStatusCode($status)->setJSON([
            'success' => true,
            'data' => $data,
            'meta' => ['request_id' => $requestId],
            'error' => null,
        ]);
    }

    /** @param array<string, list<string>> $details */
    public static function error(ResponseInterface $response, string $code, string $message, string $requestId, int $status, array $details = []): ResponseInterface
    {
        return $response->setStatusCode($status)->setJSON([
            'success' => false,
            'data' => null,
            'meta' => ['request_id' => $requestId],
            'error' => ['code' => $code, 'message' => $message, 'details' => (object) $details],
        ]);
    }
}
