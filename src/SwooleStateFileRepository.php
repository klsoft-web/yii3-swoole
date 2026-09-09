<?php

declare(strict_types=1);

namespace Klsoft\Yii3Swoole;

use RuntimeException;
use Yiisoft\Files\FileHelper;

final readonly class SwooleStateFileRepository implements SwooleStateRepositoryInterface
{
    public function __construct(private string $path)
    {
    }

    public function getSwooleState(): ?SwooleState
    {
        if (is_readable($this->path)) {
            $fileContent = file_get_contents($this->path);
            if ($fileContent) {
                $data = json_decode($fileContent, true);
                if (
                    isset($data['host']) &&
                    isset($data['port']) &&
                    isset($data['masterProcessId']) &&
                    isset($data['managerProcessId'])
                ) {
                    return new SwooleState(
                        (string)$data['host'],
                        (int)$data['port'],
                        (int)$data['masterProcessId'],
                        (int)$data['managerProcessId']
                    );
                }
            }
        }

        return null;
    }

    public function setSwooleState(SwooleState $swooleState): void
    {
        $statePath = dirname($this->path);
        if (!file_exists($statePath)) {
            FileHelper::ensureDirectory($statePath);
        }

        if (!is_writable($this->path) && !is_writable(dirname($this->path))) {
            throw new RuntimeException('Unable to write to the file: ' . $this->path);
        }

        $json = json_encode(
            [
                'host' => $swooleState->host,
                'port' => $swooleState->port,
                'masterProcessId' => $swooleState->masterProcessId,
                'managerProcessId' => $swooleState->managerProcessId
            ],
            JSON_PRETTY_PRINT
        );
        if ($json) {
            file_put_contents(
                $this->path,
                $json
            );
        }
    }

    public function delete(): bool
    {
        if (is_writable($this->path)) {
            return unlink($this->path);
        }

        return false;
    }
}
