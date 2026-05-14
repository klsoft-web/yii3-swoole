<?php

declare(strict_types=1);

namespace Klsoft\Yii3Swoole;

final readonly class Message
{
    public function __construct(
        public MessageType $messageType,
        public string $value
    ) {}
}
