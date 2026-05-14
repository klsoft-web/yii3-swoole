<?php

declare(strict_types=1);

namespace Klsoft\Yii3Swoole;

interface SwooleConfigRepositoryInterface
{
    public function getEnableSwooleSsl(): bool;

    public function getSwooleServerSettings(): array;
}
