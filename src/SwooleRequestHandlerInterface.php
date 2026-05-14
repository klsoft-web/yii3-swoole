<?php

declare(strict_types=1);

namespace Klsoft\Yii3Swoole;

use Swoole\HTTP\Request;
use Swoole\HTTP\Response;

interface SwooleRequestHandlerInterface
{
    /**
     * Handle the Swoole HTTP server request event.
     *
     * @param Swoole\HTTP\Request $request the Swoole HTTP request object containing headers, GET/POST data, cookies, etc.
     * @param Swoole\HTTP\Response $response the Swoole HTTP response object enabling HTTP operations like cookies, headers, status, etc.
     */
    public function onRequest(Request $request, Response $response): void;
}
