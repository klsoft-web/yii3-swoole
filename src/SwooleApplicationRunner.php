<?php

declare(strict_types=1);

namespace Klsoft\Yii3Swoole;

use ErrorException;
use Exception;
use Klsoft\Yii3Swoole\MessageQueueInterface;
use Klsoft\Yii3Swoole\SwooleRequestHandlerInterface;
use Klsoft\Yii3Swoole\SwooleStateRepositoryInterface;
use Klsoft\Yii3Swoole\SwooleState;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Swoole\Http\Server;
use Swoole\HTTP\Request;
use Swoole\HTTP\Response;
use Yiisoft\Definitions\Exception\CircularReferenceException;
use Yiisoft\Definitions\Exception\InvalidConfigException;
use Yiisoft\Definitions\Exception\NotInstantiableException;
use Yiisoft\Di\NotFoundException;
use Yiisoft\ErrorHandler\ErrorHandler;
use Yiisoft\ErrorHandler\Renderer\HtmlRenderer;
use Yiisoft\Yii\Http\Application;
use Yiisoft\Yii\Runner\ApplicationRunner;
use Yiisoft\Yii\Runner\Http\Exception\HeadersHaveBeenSentException;

/**
 * `SwooleApplicationRunner` runs the Yii HTTP application.
 */
final class SwooleApplicationRunner extends ApplicationRunner
{
    /**
     * @param string $rootPath The absolute path to the project root.
     * @param string $address The Swoole HTTP server specifies the IP address to listen on.
     * @param int $port The Swoole HTTP server specifies the port to listen on.
     * @param bool $debug Whether the debug mode is enabled.
     * @param bool $checkEvents Whether to check events' configuration.
     * @param string|null $environment The environment name.
     * @param string $bootstrapGroup The bootstrap configuration group name.
     * @param string $eventsGroup The events' configuration group name.
     * @param string $diGroup The container definitions' configuration group name.
     * @param string $diProvidersGroup The container providers' configuration group name.
     * @param string $diDelegatesGroup The container delegates' configuration group name.
     * @param string $diTagsGroup The container tags' configuration group name.
     * @param string $paramsGroup The configuration parameters group name.
     * @param array $nestedParamsGroups Configuration group names that are included into configuration parameters group.
     * This is needed for recursive merging of parameters.
     * @param array $nestedEventsGroups Configuration group names that are included into events' configuration group.
     * This is needed for reverse and recursive merge of events' configurations.
     * @param object[] $configModifiers Modifiers for {@see Config}.
     * @param string $configDirectory The relative path from {@see $rootPath} to the configuration storage location.
     * @param string $vendorDirectory The relative path from {@see $rootPath} to the vendor directory.
     * @param string $configMergePlanFile The relative path from {@see $configDirectory} to merge plan.
     * @param LoggerInterface|null $logger (deprecated) The logger to collect errors while container is building.
     * @param ErrorHandler|null $temporaryErrorHandler The temporary error handler instance that used to handle
     * the creation of configuration and container instances, then the error handler configured in your application
     * configuration will be used.
     *
     * @psalm-param list<string> $nestedParamsGroups
     * @psalm-param list<string> $nestedEventsGroups
     * @psalm-param list<object> $configModifiers
     */
    public function __construct(
        string                            $rootPath,
        private readonly string           $address,
        private readonly int              $port,
        bool                              $debug = false,
        bool                              $checkEvents = false,
        ?string                           $environment = null,
        string                            $bootstrapGroup = 'bootstrap-web',
        string                            $eventsGroup = 'events-web',
        string                            $diGroup = 'di-web',
        string                            $diProvidersGroup = 'di-providers-web',
        string                            $diDelegatesGroup = 'di-delegates-web',
        string                            $diTagsGroup = 'di-tags-web',
        string                            $paramsGroup = 'params-web',
        array                             $nestedParamsGroups = ['params'],
        array                             $nestedEventsGroups = ['events'],
        array                             $configModifiers = [],
        string                            $configDirectory = 'config',
        string                            $vendorDirectory = 'vendor',
        string                            $configMergePlanFile = '.merge-plan.php',
        private readonly ?LoggerInterface $logger = null,
        private ?ErrorHandler             $temporaryErrorHandler = null
    )
    {
        parent::__construct(
            $rootPath,
            $debug,
            $checkEvents,
            $environment,
            $bootstrapGroup,
            $eventsGroup,
            $diGroup,
            $diProvidersGroup,
            $diDelegatesGroup,
            $diTagsGroup,
            $paramsGroup,
            $nestedParamsGroups,
            $nestedEventsGroups,
            $configModifiers,
            $configDirectory,
            $vendorDirectory,
            $configMergePlanFile,
        );
    }

    /**
     * @param ErrorHandler $temporaryErrorHandler The temporary error handler instance.
     * @deprecated Use `$temporaryErrorHandler` constructor parameter instead.
     *
     * Returns a new instance with the specified temporary error handler instance {@see ErrorHandler}.
     *
     * A temporary error handler is needed to handle the creation of configuration and container instances,
     * then the error handler configured in your application configuration will be used.
     *
     */
    public function withTemporaryErrorHandler(ErrorHandler $temporaryErrorHandler): self
    {
        $new = clone $this;
        $new->temporaryErrorHandler = $temporaryErrorHandler;
        return $new;
    }

    /**
     * @throws CircularReferenceException|ErrorException|HeadersHaveBeenSentException|InvalidConfigException
     * @throws ContainerExceptionInterface|NotFoundException|NotFoundExceptionInterface|NotInstantiableException
     */
    public function run(): void
    {
        $container = $this->getContainer();
        /** @var MessageQueueInterface $messageQueue */
        $messageQueue = $container->get(MessageQueueInterface::class);

        try {
            // Register temporary error handler to catch error while container is building.
            $temporaryErrorHandler = $this->createTemporaryErrorHandler();
            $this->registerErrorHandler($temporaryErrorHandler);

            /**
             * Register error handler with real container-configured dependencies.
             * @var ErrorHandler $actualErrorHandler
             */
            $actualErrorHandler = $container->get(ErrorHandler::class);
            $this->registerErrorHandler($actualErrorHandler, $temporaryErrorHandler);

            $this->runBootstrap();
            $this->checkEvents();

            /** @var Application $application */
            $application = $container->get(Application::class);
            /** @var SwooleRequestHandlerInterface $swooleRequestHandler */
            $swooleRequestHandler = $container->get(SwooleRequestHandlerInterface::class);
            /** @var SwooleStateRepositoryInterface $swooleStateRepository */
            $swooleStateRepository = $container->get(SwooleStateRepositoryInterface::class);
            /** @var SwooleConfigRepositoryInterface $swooleConfigRepository */
            $swooleConfigRepository = $container->get(SwooleConfigRepositoryInterface::class);

            $server = new Server(
                $this->address,
                $this->port,
                SWOOLE_BASE,
                $swooleConfigRepository->getEnableSwooleSsl() ? SWOOLE_SOCK_TCP | SWOOLE_SSL : SWOOLE_SOCK_TCP
            );
            $server->set($swooleConfigRepository->getSwooleServerSettings());

            $server->on('Start', function (Server $server) use (
                $application,
                $swooleStateRepository,
                $messageQueue
            ) {
                $swooleStateRepository->setSwooleState(new SwooleState(
                    $server->host,
                    $server->port,
                    $server->getMasterPid(),
                    $server->getManagerPid()
                ));
                $application->start();

                $messageQueue->push([
                    new Message(MessageType::Event, 'Start'),
                    new Message(MessageType::Info, 'PHP: ' . PHP_VERSION),
                    new Message(MessageType::Info, 'Address: ' . $this->address . ':' . $this->port)
                ]);
            });

            $server->on('Request', function (Request $request, Response $response) use ($swooleRequestHandler) {
                $swooleRequestHandler->onRequest($request, $response);
            });

            $server->on('Shutdown', function (Server $server) use (
                $application,
                $messageQueue
            ) {
                $application->shutdown();
                $messageQueue->push([
                    new Message(MessageType::Event, 'Shutdown')
                ]);
            });

            $messageQueue->clear();
            $server->start();
            $messageQueue->push([
                new Message(MessageType::Event, 'ServerClose')
            ]);
        } catch (Exception $e) {
            $messageQueue->push([
                new Message(MessageType::Error, $e->getMessage())
            ]);
        }
    }

    private function createTemporaryErrorHandler(): ErrorHandler
    {
        return $this->temporaryErrorHandler ??
            new ErrorHandler(
                $this->logger ?? new NullLogger(),
                new HtmlRenderer(),
            );
    }

    /**
     * @throws ErrorException
     */
    private function registerErrorHandler(ErrorHandler $registered, ?ErrorHandler $unregistered = null): void
    {
        $unregistered?->unregister();

        if ($this->debug) {
            $registered->debug();
        }

        $registered->register();
    }
}
