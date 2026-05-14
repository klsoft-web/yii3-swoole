<?php

declare(strict_types=1);

namespace Klsoft\Yii3Swoole;

use Klsoft\Yii3Swoole\MessageQueueInterface;
use Klsoft\Yii3Swoole\PsrRequestConverter;
use Klsoft\Yii3Swoole\PsrResponseConverter;
use Swoole\HTTP\Request;
use Swoole\HTTP\Response;
use SplFileInfo;
use Throwable;
use Yiisoft\Di\StateResetter;
use Yiisoft\ErrorHandler\Middleware\ErrorCatcher;
use Yiisoft\Yii\Http\Application;
use Yiisoft\Yii\Http\Handler\ThrowableHandler;

final readonly class SwooleRequestHandler implements SwooleRequestHandlerInterface
{
    public function __construct(
        private Application                   $application,
        private string                        $publicDir,
        private PsrRequestConverterInterface  $psrRequestConverter,
        private PsrResponseConverterInterface $psrResponseConverter,
        private ErrorCatcher                  $errorCatcher,
        private StateResetter                 $stateResetter
    )
    {
    }

    public function onRequest(Request $request, Response $response): void
    {
        $path = $this->publicDir . $request->server['request_uri'];
        if ((new SplFileInfo($path))->isFile()) {
            $response->header('Last-Modified', gmdate('D, d M Y H:i:s \G\M\T', filemtime($path)));
            $response->sendfile($path);
        } else {
            $serverRequest = $this->psrRequestConverter->from($request);
            try {
                $psrResponse = $this->application->handle($serverRequest);
                $this->psrResponseConverter->emit($response, $psrResponse);
            } catch (Throwable $throwable) {
                $handler = new ThrowableHandler($throwable);
                $psrResponse = $this->errorCatcher->process($serverRequest, $handler);
                $this->psrResponseConverter->emit($response, $psrResponse);
            }
            $this->application->afterEmit($psrResponse);
            $this->stateResetter->reset();
        }
    }
}
