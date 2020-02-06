<?php

/*
 * This file is part of the DriftPHP Project
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Feel free to edit as you please, and have fun.
 *
 * @author Marc Morera <yuhu@mmoreram.com>
 */

declare(strict_types=1);

namespace Drift\CommandBus\Console;

use Drift\CommandBus\Async\AsyncAdapter;
use Drift\CommandBus\Bus\CommandBus;
use Drift\Console\OutputPrinter;
use React\EventLoop\LoopInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class CommandConsumer.
 */
class CommandConsumerCommand extends Command
{
    /**
     * @var AsyncAdapter
     */
    private $asyncAdapter;

    /**
     * @var CommandBus
     */
    private $commandBus;

    /**
     * @var LoopInterface
     */
    private $loop;

    /**
     * ConsumeCommand constructor.
     *
     * @param AsyncAdapter  $asyncAdapter
     * @param CommandBus    $commandBus
     * @param LoopInterface $loop
     */
    public function __construct(
        AsyncAdapter $asyncAdapter,
        CommandBus $commandBus,
        LoopInterface $loop
    ) {
        parent::__construct();

        $this->asyncAdapter = $asyncAdapter;
        $this->commandBus = $commandBus;
        $this->loop = $loop;
    }

    /**
     * Configures the current command.
     */
    protected function configure()
    {
        $this->setDescription('Start consuming asynchronous commands from the command bus');
        $this->addOption(
            'limit',
            null,
            InputOption::VALUE_OPTIONAL,
            'Number of jobs to handle before dying',
            0
        );
    }

    /**
     * Executes the current command.
     *
     * This method is not abstract because you can use this class
     * as a concrete class. In this case, instead of defining the
     * execute() method, you set the code to execute by passing
     * a Closure to the setCode() method.
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $outputPrinter = new OutputPrinter($output);
        $adapterName = $this->asyncAdapter->getName();
        (new CommandBusHeaderMessage('', 'Consumer built'))->print($outputPrinter);
        (new CommandBusHeaderMessage('', 'Using adapter '.$adapterName))->print($outputPrinter);
        (new CommandBusHeaderMessage('', 'Started listening...'))->print($outputPrinter);

        $this
            ->asyncAdapter
            ->consume(
                $this->commandBus,
                \intval($input->getOption('limit')),
                $outputPrinter
            );

        return 0;
    }
}
