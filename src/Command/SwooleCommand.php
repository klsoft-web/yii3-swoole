<?php

declare(strict_types=1);

namespace Klsoft\Yii3Swoole\Command;

use Klsoft\Yii3Swoole\MessageQueueInterface;
use Klsoft\Yii3Swoole\MessageType;
use Klsoft\Yii3Swoole\SwooleStateRepositoryInterface;
use Klsoft\Yii3Swoole\SwooleState;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('swoole', 'Runs Swoole HTTP Server')]
final class SwooleCommand extends Command
{
    private const SWOOLE_COMMAND_START = 'start';
    private const SWOOLE_COMMAND_RESTART = 'restart';
    private const SWOOLE_COMMAND_RELOAD = 'reload';
    private const SWOOLE_COMMAND_SHUTDOWN = 'shutdown';

    private const DEFAULT_ADDRESS = '127.0.0.1';
    private const DEFAULT_PORT = 9501;

    private const MESSAGE_LISTENER_TIMEOUT = 10;
    private int $startTimeMessageListener = 0;

    public function __construct(
        private readonly SwooleStateRepositoryInterface $swooleStateRepository,
        private readonly MessageQueueInterface          $messageQueue
    )
    {
        parent::__construct();
    }

    public function configure(): void
    {
        $this
            ->setHelp(
                'In order to access server from remote machines use 0.0.0.0:9501. That is especially useful when running server in a virtual machine.'
            )
            ->addArgument('swoole_command', InputArgument::REQUIRED, 'Swoole HTTP Server commands: ' . self::SWOOLE_COMMAND_START . '|' . self::SWOOLE_COMMAND_RESTART . '|' . self::SWOOLE_COMMAND_RELOAD . '|' . self::SWOOLE_COMMAND_SHUTDOWN)
            ->addOption('address', 'a', InputOption::VALUE_OPTIONAL, 'Host to serve at', self::DEFAULT_ADDRESS)
            ->addOption('port', 'p', InputOption::VALUE_OPTIONAL, 'Port to serve at', self::DEFAULT_PORT)
            ->addOption('same-process', null, InputOption::VALUE_NONE, 'Start the Swoole HTTP server in the same process');

    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('address')) {
            $suggestions->suggestValues(['127.0.0.1', '0.0.0.0']);
            return;
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $swooleCommand = (string)$input->getArgument('swoole_command');
        switch ($swooleCommand) {
            case self::SWOOLE_COMMAND_START:
                return $this->start((string)$input->getOption('address'), (int)$input->getOption('port'), $input, $output);
            case self::SWOOLE_COMMAND_RESTART:
                return $this->restart($input, $output);
            case self::SWOOLE_COMMAND_RELOAD:
                return $this->reload($input, $output);
            case self::SWOOLE_COMMAND_SHUTDOWN:
                return $this->shutdown(true, $input, $output);
            default:
                $symfonyStyle = new SymfonyStyle($input, $output);
                $symfonyStyle->error('The unknown swoole command: ' . $swooleCommand);
                $symfonyStyle->info('Run ./yii swoole --help');
                return Command::FAILURE;
        }
    }

    private function start(
        string          $host,
        int             $port,
        InputInterface  $input,
        OutputInterface $output
    ): int
    {
        $symfonyStyle = new SymfonyStyle($input, $output);
        $symfonyStyle->title('Swoole HTTP Server');

        $isSameProcess = $input->getOption('same-process');
        passthru('php "' . dirname(__DIR__) . '/swoole.php" --address=' . $host . ' --port=' . $port . ($isSameProcess ? '' : ' > /dev/null &'));

        $this->setStartTimeMessageListener();
        while (!$this->isMessageTimeoutExpired()) {
            usleep(10000);
            $message = $this->messageQueue->pop();
            if ($message !== null) {
                switch ($message->messageType) {
                    case MessageType::Event:
                        if (!$isSameProcess) {
                            if ($message->value === 'Start') {
                                $symfonyStyle->success('Swoole HTTP Server is started');
                                do {
                                    $message = $this->messageQueue->pop();
                                    if ($message !== null) {
                                        $output->writeln($message->value);
                                    }
                                } while ($message !== null);
                                return Command::SUCCESS;
                            }
                        }
                        break;
                    case MessageType::Info:
                        if (!$isSameProcess) {
                            $output->writeln($message->value);
                        }
                        break;
                    case MessageType::Error:
                        $symfonyStyle->error($message->value);
                        return Command::FAILURE;
                }
            }
        }

        return $isSameProcess ? Command::SUCCESS : Command::FAILURE;
    }

    private function setStartTimeMessageListener(): void
    {
        $this->startTimeMessageListener = time();
    }

    private function isMessageTimeoutExpired(): bool
    {
        return $this->startTimeMessageListener + self::MESSAGE_LISTENER_TIMEOUT < time();
    }

    private function restart(InputInterface $input, OutputInterface $output): int
    {
        if ($this->shutdown(false, $input, $output) === Command::SUCCESS) {
            $this->setStartTimeMessageListener();
            while (!$this->isMessageTimeoutExpired()) {
                usleep(10000);
                $message = $this->messageQueue->pop();
                if (
                    $message !== null &&
                    $message->messageType === MessageType::Event &&
                    $message->value === 'ServerClose'
                ) {
                    $swooleState = $this->swooleStateRepository->getSwooleState();
                    if ($swooleState !== null) {
                        return $this->start($swooleState->host, $swooleState->port, $input, $output);
                    } else {
                        $symfonyStyle = new SymfonyStyle($input, $output);
                        $symfonyStyle->error('The Swoole HTTP server is not running.');
                        break;
                    }
                }
            }
        }

        return Command::FAILURE;
    }

    private function reload(InputInterface $input, OutputInterface $output): int
    {
        $symfonyStyle = new SymfonyStyle($input, $output);
        $symfonyStyle->title('Swoole HTTP Server');
        $swooleState = $this->swooleStateRepository->getSwooleState();
        if ($swooleState !== null) {
            if ($swooleState->managerProcessId !== 0) {
                passthru('kill -USR1 ' . $swooleState->managerProcessId, $resultCode);
                if ($resultCode === 0) {
                    $output->writeln('The Swoole HTTP server worker processes have been restarted.');
                } else {
                    $symfonyStyle->error('No such process.');
                }

                return $resultCode;
            } else {
                $symfonyStyle->error('The Swoole HTTP server reload command is available when worker_num is greater than 1.');
                return Command::FAILURE;
            }
        }

        $symfonyStyle->error('The Swoole HTTP server is not running.');
        return Command::FAILURE;
    }

    private function shutdown(bool $deleteSwooleStateRepository, InputInterface $input, OutputInterface $output): int
    {
        $symfonyStyle = new SymfonyStyle($input, $output);
        $symfonyStyle->title('Swoole HTTP Server');
        $swooleState = $this->swooleStateRepository->getSwooleState();
        if ($swooleState !== null) {
            passthru('kill -15 ' . ($swooleState->managerProcessId !== 0 ? $swooleState->managerProcessId : $swooleState->masterProcessId), $resultCode);
            if ($resultCode === 0) {
                $this->setStartTimeMessageListener();
                while (!$this->isMessageTimeoutExpired()) {
                    usleep(10000);
                    $message = $this->messageQueue->pop();
                    if (
                        $message !== null &&
                        $message->messageType === MessageType::Event &&
                        $message->value === 'Shutdown'
                    ) {
                        if ($deleteSwooleStateRepository) {
                            $this->swooleStateRepository->delete();
                        }
                        $output->writeln('The Swoole HTTP server has been shut down.');
                        return Command::SUCCESS;
                    }
                }
            } else {
                $symfonyStyle->error('No such process.');
            }

            return $resultCode;
        }

        $symfonyStyle->error('The Swoole HTTP server is not running.');
        return Command::FAILURE;
    }
}
