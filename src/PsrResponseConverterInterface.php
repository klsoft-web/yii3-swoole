<?php

declare(strict_types=1);

namespace Klsoft\Yii3Swoole;

use Psr\Http\Message\ResponseInterface;
use Swoole\HTTP\Response;

interface PsrResponseConverterInterface
{
    public function emit(Response $response, ResponseInterface $psrResponse): void;
}
