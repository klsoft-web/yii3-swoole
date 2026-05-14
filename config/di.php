<?php

declare(strict_types=1);

use Klsoft\Yii3Swoole\MessageQueueInterface;
use Klsoft\Yii3Swoole\MessageQueueFile;
use Klsoft\Yii3Swoole\SwooleStateRepositoryInterface;
use Klsoft\Yii3Swoole\SwooleStateFileRepository;
use Yiisoft\Aliases\Aliases;

/** @var array $params */

return [
    SwooleStateRepositoryInterface::class => static function (Aliases $aliases) {
        return new SwooleStateFileRepository($aliases->get('@runtime') . '/swoole.state');
    },
    MessageQueueInterface::class => static function (Aliases $aliases) {
        return new MessageQueueFile($aliases->get('@runtime') . '/swoole.messages');
    }
];
