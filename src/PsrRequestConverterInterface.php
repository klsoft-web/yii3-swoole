<?php

declare(strict_types=1);

namespace Klsoft\Yii3Swoole;

use Psr\Http\Message\ServerRequestInterface;
use Swoole\HTTP\Request;

interface PsrRequestConverterInterface
{
    public function from(Request $request): ServerRequestInterface;
}
