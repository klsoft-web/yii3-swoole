<?php

declare(strict_types=1);

namespace Klsoft\Yii3Swoole;

use RuntimeException;

final readonly class SwooleStateFileRepository implements SwooleStateRepositoryInterface
{
    public function __construct(private string $path) {}

    public function getSwooleState(): ?SwooleState
    {
        $data = is_readable($this->path)
            ? json_decode(file_get_contents($this->path), true)
            : [];

        if (
            isset($data['host']) &&
            isset($data['port']) &&
            isset($data['masterProcessId']) &&
            isset($data['managerProcessId'])
        ) {
            return new SwooleState(
                $data['host'],
                $data['port'],
                $data['masterProcessId'],
                $data['managerProcessId']
            );
        }

        return null;
    }

    public function setSwooleState(SwooleState $swooleState): void
    {
        if (!is_writable($this->path) && !is_writable(dirname($this->path))) {
            throw new RuntimeException('Unable to write to the file: ' . $this->path);
        }

        file_put_contents(
            $this->path,
            json_encode(
                [
                    'host' => $swooleState->host,
                    'port' => $swooleState->port,
                    'masterProcessId' => $swooleState->masterProcessId,
                    'managerProcessId' => $swooleState->managerProcessId
                ],
                JSON_PRETTY_PRINT
            )
        );
    }

    public function delete(): bool
    {
        if (is_writable($this->path)) {
            return unlink($this->path);
        }

        return false;
    }
}
