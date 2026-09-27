<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

final class RequestContextFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null): void
    {
        $requestId = Services::requestContext()->initialize($request->getHeaderLine('X-Request-ID'));
        log_message('info', 'request.started {request_id} {method} {path}', [
            'request_id' => $requestId,
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath(),
        ]);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
        $requestId = Services::requestContext()->requestId();
        $response->setHeader('X-Request-ID', $requestId);
        log_message('info', 'request.completed {request_id} {status}', [
            'request_id' => $requestId,
            'status' => $response->getStatusCode(),
        ]);
    }
}
