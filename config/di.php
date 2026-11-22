<?php

declare(strict_types=1);

use Klsoft\Yii3Swoole\MessageQueueInterface;
use Klsoft\Yii3Swoole\MessageQueueFile;
use Klsoft\Yii3Swoole\PsrRequestConverterInterface;
use Klsoft\Yii3Swoole\PsrRequestConverter;
use Klsoft\Yii3Swoole\PsrResponseConverterInterface;
use Klsoft\Yii3Swoole\PsrResponseConverter;
use Klsoft\Yii3Swoole\SwooleStateRepositoryInterface;
use Klsoft\Yii3Swoole\SwooleStateFileRepository;
use Klsoft\Yii3Swoole\SwooleConfigRepositoryInterface;
use Klsoft\Yii3Swoole\SwooleConfigRepository;
use Klsoft\Yii3Swoole\SwooleRequestHandlerInterface;
use Klsoft\Yii3Swoole\SwooleRequestHandler;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Di\StateResetter;
use Yiisoft\ErrorHandler\Middleware\ErrorCatcher;
use Yiisoft\Yii\Http\Application;

/** @var array $params */

return [
    SwooleStateRepositoryInterface::class => static function (Aliases $aliases) {
        return new SwooleStateFileRepository($aliases->get('@runtime') . '/swoole/swoole.state');
    },
    MessageQueueInterface::class => static function (Aliases $aliases) {
        return new MessageQueueFile($aliases->get('@runtime') . '/swoole/swoole.messages');
    },
    PsrRequestConverterInterface::class => PsrRequestConverter::class,
    PsrResponseConverterInterface::class => PsrResponseConverter::class,
    SwooleConfigRepositoryInterface::class => [
        'class' => SwooleConfigRepository::class,
        '__construct()' => [
            'enableSwooleSsl' => $params['klsoft/yii3-swoole']['enableSwooleSsl'],
            'swooleServerSettings' => $params['klsoft/yii3-swoole']['swooleServerSettings']
        ],
    ],
    SwooleRequestHandlerInterface::class => static function (ContainerInterface $container) {
        return new SwooleRequestHandler(
            application: $container->get(Application::class),
            publicDir: $container->get(Aliases::class)->get('@public'),
            psrRequestConverter: $container->get(PsrRequestConverterInterface::class),
            psrResponseConverter: $container->get(PsrResponseConverterInterface::class),
            responseFactory: $container->get(ResponseFactoryInterface::class),
            errorCatcher: $container->get(ErrorCatcher::class),
            stateResetter: $container->get(StateResetter::class)
        );
    }
];
