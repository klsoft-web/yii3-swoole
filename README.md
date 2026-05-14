# YII3-SWOOLE

The package provides the Swoole HTTP server for the [Yii 3](https://yii3.yiiframework.com) application.

## Requirement

- PHP 8.2 or higher.
- [Swoole](https://github.com/swoole/swoole-src) PHP extension 6.2.0 or higher.

## Installation

```bash
composer require klsoft/yii3-swoole
```

## How to use

Configure the [resetting of service states](https://github.com/yiisoft/di#resetting-services-state).

For web applications that use `AssetManager`, add the following to the `config/web/di/application.php` file:

```php
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\AssetManager;
use Yiisoft\Assets\AssetLoaderInterface;
use Yiisoft\Assets\AssetConverterInterface;
use Yiisoft\Assets\AssetRegistrar;

return [
    // ...
    AssetManager::class => [
        'definition' =>  static function (ContainerInterface $container) use ($params): AssetManager {
            $assetManager = new AssetManager(
                $container->get(Aliases::class),
                $container->get(AssetLoaderInterface::class),
                $params['yiisoft/assets']['assetManager']['allowedBundleNames'],
                $params['yiisoft/assets']['assetManager']['customizedBundles'],
            );

            $assetManager = $assetManager
                ->withConverter($container->get(AssetConverterInterface::class));

            if ($params['yiisoft/assets']['assetManager']['publisher'] !== null) {
                $assetManager = $assetManager->withPublisher(
                    $container->get($params['yiisoft/assets']['assetManager']['publisher'])
                );
            }

            $assetManager->registerMany($params['yiisoft/assets']['assetManager']['register']);
            return $assetManager;
        },
        'reset' => function (ContainerInterface $container) {
            $this->registrar = new AssetRegistrar($container->get(Aliases::class), $container->get(AssetLoaderInterface::class));
        },
    ],
];
```

Start the Swoole HTTP server:
```bash
./yii swoole start
```

Start the Swoole HTTP server using the specified options:
```bash
./yii swoole start --address=127.0.0.1 --port=9501
```

Restart the Swoole HTTP server:
```bash
./yii swoole restart
```

[Restart](https://wiki.swoole.com/en/#/server/methods?id=reload) the Swoole HTTP server worker processes:
```bash
./yii swoole reload
```

Shutdown the Swoole HTTP server:
```bash
./yii swoole shutdown
```

### Configuring the Swoole HTTP server.

Add the Swoole HTTP server [settings](https://wiki.swoole.com/en/#/server/setting) to the `config/web/params.php` file and then restart the server:

Example:
```php
return [
    // ...
    'klsoft/yii3-swoole' => [
        'swooleServerSettings' => [
             'log_file'   => __DIR__ . '/../../runtime/logs/swoole.log'
        ]
    ],
];
```

Enable SSL:
```php
return [
    // ...
    'klsoft/yii3-swoole' => [
        'enableSwooleSsl' => true,
        'swooleServerSettings' => [
            'ssl_cert_file' => __DIR__ . '/../ssl/domain.crt',
            'ssl_key_file' => __DIR__ . '/../ssl/domain.key'
        ]
    ],
];
```

### Configuring the SwooleRequestHandlerInterface.

Configure your own version of the `SwooleRequestHandlerInterface` in the `config/web/di/application.php` file:

```php
use Klsoft\Yii3Swoole\SwooleRequestHandlerInterface;
use Psr\Container\ContainerInterface;

return [
    // ...
    SwooleRequestHandlerInterface::class => static function (ContainerInterface $container) {
        return new MySwooleRequestHandler(
            // ...
        );
    }
];
```
