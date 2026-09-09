<?php

declare(strict_types=1);

namespace Klsoft\Yii3Swoole;

final readonly class SwooleState
{
    public function __construct(
        public string $host,
        public int $port,
        public int $masterProcessId,
        public int $managerProcessId
    ) {}
}
