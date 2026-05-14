<?php

declare(strict_types=1);

use App\Environment;
use Klsoft\Yii3Swoole\Command\SwooleCommand;

return [
    'yiisoft/yii-console' => [
        'commands' => [
            'swoole' => SwooleCommand::class,
        ]
    ],
];
