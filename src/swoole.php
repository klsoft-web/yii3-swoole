<?php

declare(strict_types=1);

use App\Environment;
use Klsoft\Yii3Swoole\SwooleApplicationRunner;
use Psr\Log\LogLevel;
use Yiisoft\ErrorHandler\ErrorHandler;
use Yiisoft\ErrorHandler\Renderer\PlainTextRenderer;
use Yiisoft\Log\Logger;
use Yiisoft\Log\StreamTarget;

$root = dirname(__DIR__, 4);

$options = getopt('', ['address::', 'port::']);
/** @var string $address */
$address = is_array($options) && array_key_exists('address', $options) && $options['address'] ? $options['address'] : '127.0.0.1';
/** @var int $port */
$port = is_array($options) && array_key_exists('port', $options) && $options['port'] ? intval($options['port']) : 9501;

require_once $root . '/src/bootstrap.php';

$runner = new SwooleApplicationRunner(
    rootPath: $root,
    address: $address,
    port: $port,
    debug: Environment::appDebug(),
    checkEvents: Environment::appDebug(),
    environment: Environment::appEnv(),
    temporaryErrorHandler: new ErrorHandler(
        new Logger(
            [
                (new StreamTarget())->setLevels([
                    LogLevel::EMERGENCY,
                    LogLevel::ERROR,
                    LogLevel::WARNING,
                ]),
            ],
        ),
        new PlainTextRenderer(),
    )
);
$runner->run();
