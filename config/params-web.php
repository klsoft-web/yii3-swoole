<?php

declare(strict_types=1);

use App\Environment;
use Klsoft\Yii3Swoole\Command\SwooleCommand;

return [
    'klsoft/yii3-swoole' => [
        'enableSwooleSsl' => false,
        'swooleServerSettings' => [
            'reactor_num' => swoole_cpu_num(), // number of threads
            'worker_num' => swoole_cpu_num() // number of processes
        ]
    ],
];
