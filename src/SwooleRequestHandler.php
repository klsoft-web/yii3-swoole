<?php

declare(strict_types=1);

namespace Klsoft\Yii3Swoole;

use Klsoft\Yii3Swoole\MessageQueueInterface;
use Klsoft\Yii3Swoole\PsrRequestConverter;
use Klsoft\Yii3Swoole\PsrResponseConverter;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Swoole\HTTP\Request;
use Swoole\HTTP\Response;
use SplFileInfo;
use Throwable;
use Yiisoft\Di\StateResetter;
use Yiisoft\ErrorHandler\Middleware\ErrorCatcher;
use Yiisoft\Http\Status;
use Yiisoft\Yii\Http\Application;
use Yiisoft\Yii\Http\Handler\ThrowableHandler;

final readonly class SwooleRequestHandler implements SwooleRequestHandlerInterface
{
    public function __construct(
        private Application                   $application,
        private string                        $publicDir,
        private PsrRequestConverterInterface  $psrRequestConverter,
        private PsrResponseConverterInterface $psrResponseConverter,
        private ResponseFactoryInterface      $responseFactory,
        private ErrorCatcher                  $errorCatcher,
        private ContainerInterface $container
    )
    {
    }

    public function onRequest(Request $request, Response $response): void
    {
        /** @var array<string, mixed> $server */
        $server = $request->server ?? [];
        $path = $this->publicDir . (string)($server['request_uri'] ?? '');
        $fileInfo = new SplFileInfo($path);
        if ($fileInfo->isFile()) {
            if ($fileInfo->getExtension() === 'php') {
                $this->psrResponseConverter->emit($response, $this->responseFactory->createResponse()->withStatus(Status::BAD_REQUEST));
            } else {
                $fileTime = filemtime($path);
                if ($fileTime) {
                    $response->header('Last-Modified', gmdate('D, d M Y H:i:s \G\M\T', $fileTime));
                }
                $response->sendfile($path);
            }
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
            $this->container->get(StateResetter::class)->reset();
        }
    }
}
