<?php

declare(strict_types=1);

namespace Klsoft\Yii3Swoole;

interface SwooleStateRepositoryInterface
{
    public function getSwooleState(): ?SwooleState;

    public function setSwooleState(SwooleState $swooleState): void;

    public function delete(): bool;
}
