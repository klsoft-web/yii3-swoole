<?php

declare(strict_types=1);

use Klsoft\Yii3Swoole\PsrRequestConverterInterface;
use Klsoft\Yii3Swoole\PsrRequestConverter;
use Klsoft\Yii3Swoole\PsrResponseConverterInterface;
use Klsoft\Yii3Swoole\PsrResponseConverter;
use Klsoft\Yii3Swoole\SwooleConfigRepositoryInterface;
use Klsoft\Yii3Swoole\SwooleConfigRepository;
use Klsoft\Yii3Swoole\SwooleRequestHandlerInterface;
use Klsoft\Yii3Swoole\SwooleRequestHandler;
use Psr\Container\ContainerInterface;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Di\StateResetter;
use Yiisoft\ErrorHandler\Middleware\ErrorCatcher;
use Yiisoft\Yii\Http\Application;

/** @var array $params */

return [
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
            errorCatcher: $container->get(ErrorCatcher::class),
            stateResetter: $container->get(StateResetter::class)
        );
    }
];
