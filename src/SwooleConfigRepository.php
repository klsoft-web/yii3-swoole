<?php

declare(strict_types=1);

namespace Klsoft\Yii3Swoole;

final readonly class SwooleConfigRepository implements SwooleConfigRepositoryInterface
{
    public function __construct(
        private bool $enableSwooleSsl,
        private array $swooleServerSettings
    ) {}

    public function getEnableSwooleSsl(): bool
    {
        return $this->enableSwooleSsl;
    }

    public function getSwooleServerSettings(): array
    {
        return $this->swooleServerSettings;
    }
}
