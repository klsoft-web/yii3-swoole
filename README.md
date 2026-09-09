# YII3-SWOOLE

The package provides the Swoole HTTP server for the [Yii 3](https://yii3.yiiframework.com) application.

See also:

- [YII3-SWOOLE-DOCKER-APP](https://github.com/klsoft-web/yii3-swoole-docker-app) - The repository contains the [YII3 application template](https://github.com/yiisoft/app), and the additional configuration required to run the  [Swoole](https://github.com/swoole/swoole-src) HTTP server on Docker.

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
use Psr\Container\ContainerInterface;
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

Add the Swoole HTTP server [settings](https://wiki.swoole.com/en/#/server/setting) to the `config/common/params.php` file and then restart the server:

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

Configure your own version of the `SwooleRequestHandlerInterface` in the `config/common/di/application.php` file:

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

### Use cron to start the Swoole HTTP server automatically.

Edit the crontab file:
```bash
crontab -e
```

Add the following line:
```bash
@reboot path-to-app/yii swoole start --address=127.0.0.1 --port=9501
```

## How to use with Docker

 1.  Add the package to the `composer.json`:
```json
{
    // ...
    "require": {
        // ...
         "klsoft/yii3-swoole": "^1.0"
    },
}
```

2. Add the [Dockerfile](https://github.com/klsoft-web/yii3-swoole-docker-app/blob/main/Dockerfile) and [docker-compose.yml](https://github.com/klsoft-web/yii3-swoole-docker-app/blob/main/docker-compose.yml) to the root directory of a Yii3 application.
3. Replace the [.dockerignore](https://github.com/klsoft-web/yii3-swoole-docker-app/blob/main/.dockerignore) file in the root directory of a Yii3 application.
4. Start the application: 
    ```bash 
    docker compose up -d 
    ```
    
Open your browser to the URL [http://localhost:9501](http://localhost:9501)

[Restart](https://wiki.swoole.com/en/#/server/methods?id=reload) the Swoole HTTP server worker processes:
```bash
docker compose exec app ./yii swoole reload
```

Shutdown the Swoole HTTP server:
```bash
docker compose exec app ./yii swoole shutdown
```

