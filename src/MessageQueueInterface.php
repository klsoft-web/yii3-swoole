<?php

declare(strict_types=1);

namespace Klsoft\Yii3Swoole;

interface MessageQueueInterface
{
    /**
     * @param array<Message> $messages
     */
    public function push(array $messages): void;

    public function peek(): ?Message;

    public function pop(): ?Message;

    public function clear(): void;
}
