<?php

declare(strict_types=1);

namespace Klsoft\Yii3Swoole;

use RuntimeException;
use SysvSemaphore;
use Yiisoft\Files\FileHelper;

final class MessageQueueFile implements MessageQueueInterface
{
    private SysvSemaphore $semaphore;

    public function __construct(private string $path)
    {
        $semaphore = sem_get(ftok(__FILE__, 'y'));
        if ($semaphore === false) {
            throw new RuntimeException("Initialization failed");
        }
        $this->semaphore = $semaphore;
    }

    public function push(array $messages): void
    {
        $messagesPath = dirname($this->path);
        if (!file_exists($messagesPath)) {
            FileHelper::ensureDirectory($messagesPath);
        }

        if (!is_writable($this->path) && !is_writable(dirname($this->path))) {
            throw new RuntimeException('Unable to write to the file: ' . $this->path);
        }

        sem_acquire($this->semaphore);

        try {
            $allMessages = $this->getAllMessages();
            foreach ($messages as $message) {
                $allMessages[] = [
                    'type' => $message->messageType->value,
                    'value' => $message->value
                ];
            }
            $json = json_encode($allMessages, JSON_PRETTY_PRINT);
            if ($json) {
                file_put_contents(
                    $this->path,
                    $json
                );
            }
        } finally {
            sem_release($this->semaphore);
        }
    }

    /**
     * @return array<array<string, mixed>> $messages
     */
    private function getAllMessages(): array
    {
        if (is_readable($this->path)) {
            $fileContent = file_get_contents($this->path);
            if ($fileContent) {
                $json = json_decode($fileContent, true);
                if (is_array($json)) {
                    return $json;
                }
            }
        }

        return [];
    }

    public function peek(): ?Message
    {
        return $this->getFirstMessage(false);
    }

    private function getFirstMessage(bool $remove): ?Message
    {
        sem_acquire($this->semaphore);

        try {
            $messages = $this->getAllMessages();
            if (empty($messages)) {
                return null;
            } else {
                $messageArr = $messages[0];
                $message = new Message(
                    MessageType::from((string)$messageArr['type']),
                    (string)$messageArr['value']
                );
                if ($remove) {
                    $json = json_encode(array_slice($messages, 1), JSON_PRETTY_PRINT);
                    if ($json) {
                        file_put_contents(
                            $this->path,
                            $json
                        );
                    }
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
        if (!file_exists($this->path)) {
            return;
        }

        if (!is_writable($this->path) && !is_writable(dirname($this->path))) {
            throw new RuntimeException('Unable to write to the file: ' . $this->path);
        }

        sem_acquire($this->semaphore);

        try {
            $json = json_encode([], JSON_PRETTY_PRINT);
            if ($json) {
                file_put_contents(
                    $this->path,
                    $json
                );
            }
        } finally {
            sem_release($this->semaphore);
        }
    }
}
