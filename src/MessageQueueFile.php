<?php

declare(strict_types=1);

namespace Klsoft\Yii3Swoole;

use RuntimeException;

final class MessageQueueFile implements MessageQueueInterface
{
    private mixed $semaphore;

    public function __construct(private string $path)
    {
        $this->semaphore = sem_get(ftok(__FILE__, 'y'));
        if ($this->semaphore === false) {
            throw new RuntimeException("Initialization failed");
        }
    }

    public function push(array $messages): void
    {
        if (!is_writable($this->path) && !is_writable(dirname($this->path))) {
            throw new RuntimeException('Unable to write to the file: ' . $this->path);
        }

        sem_acquire($this->semaphore);

        try {
            $allMessages = $this->getAllMesages();
            foreach ($messages as $message) {
                $allMessages[] = [
                    'type' => $message->messageType->value,
                    'value' => $message->value
                ];
            }
            file_put_contents(
                $this->path,
                json_encode($allMessages, JSON_PRETTY_PRINT)
            );
        } finally {
            sem_release($this->semaphore);
        }
    }

    private function getAllMesages(): array
    {
        return is_readable($this->path)
            ? json_decode(file_get_contents($this->path), true)
            : [];
    }

    public function peek(): ?Message
    {
        return $this->getFirstMessage(false);
    }

    private function getFirstMessage(bool $remove): ?Message
    {
        sem_acquire($this->semaphore);

        try {
            $messages = $this->getAllMesages();
            if (empty($messages)) {
                return null;
            } else {
                $messageArr = $messages[0];
                $message = new Message(
                    MessageType::from($messageArr['type']),
                    $messageArr['value']
                );
                if ($remove) {
                    file_put_contents(
                        $this->path,
                        json_encode(array_slice($messages, 1), JSON_PRETTY_PRINT)
                    );
                }
                return $message;
            }
        } finally {
            sem_release($this->semaphore);
        }
    }

    public function pop(): ?Message
    {
        return $this->getFirstMessage(true);
    }

    public function clear(): void
    {
        if (!is_writable($this->path) && !is_writable(dirname($this->path))) {
            throw new RuntimeException('Unable to write to the file: ' . $this->path);
        }

        sem_acquire($this->semaphore);

        try {
            file_put_contents(
                $this->path,
                json_encode([], JSON_PRETTY_PRINT)
            );
        } finally {
            sem_release($this->semaphore);
        }
    }
}
