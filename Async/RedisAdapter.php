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

namespace Drift\CommandBus\Async;

use Clue\React\Redis\Client;
use Drift\CommandBus\Bus\CommandBus;
use Drift\CommandBus\Console\CommandBusLineMessage;
use Drift\CommandBus\Exception\InvalidCommandException;
use Drift\Console\OutputPrinter;
use function Clue\React\Block\await;
use React\EventLoop\LoopInterface;
use React\Promise\FulfilledPromise;
use React\Promise\PromiseInterface;

/**
 * Class RedisAdapter.
 */
class RedisAdapter extends AsyncAdapter
{
    /**
     * @var Client
     */
    private $redis;

    /**
     * @var LoopInterface
     */
    private $loop;

    /**
     * @var string
     */
    private $key;

    /**
     * RedisAdapter constructor.
     *
     * @param Client        $redis
     * @param LoopInterface $loop
     * @param string        $key
     */
    public function __construct(
        Client $redis,
        LoopInterface $loop,
        string $key
    ) {
        $this->redis = $redis;
        $this->loop = $loop;
        $this->key = $key;
    }

    /**
     * Get adapter name.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'Redis';
    }

    /**
     * Create infrastructure.
     *
     * @param OutputPrinter $outputPrinter
     *
     * @return PromiseInterface
     */
    public function createInfrastructure(OutputPrinter $outputPrinter): PromiseInterface
    {
        (new CommandBusLineMessage('List created properly. No need to be created previously'))->print($outputPrinter);

        return new FulfilledPromise();
    }

    /**
     * Drop infrastructure.
     *
     * @param OutputPrinter $outputPrinter
     *
     * @return PromiseInterface
     */
    public function dropInfrastructure(OutputPrinter $outputPrinter): PromiseInterface
    {
        return $this
            ->redis
            ->del($this->key);
    }

    /**
     * Check infrastructure.
     *
     * @param OutputPrinter $outputPrinter
     *
     * @return PromiseInterface
     */
    public function checkInfrastructure(OutputPrinter $outputPrinter): PromiseInterface
    {
        (new CommandBusLineMessage(sprintf(
            'List with name %s exists or can be automatically created with the first push',
            $this->key
        )))->print($outputPrinter);

        return new FulfilledPromise();
    }

    /**
     * {@inheritdoc}
     */
    public function enqueue($command): PromiseInterface
    {
        return $this
            ->redis
            ->rPush($this->key, serialize($command));
    }

    /**
     * Consume.
     *
     * @param CommandBus    $bus
     * @param int           $limit
     * @param OutputPrinter $outputPrinter
     *
     * @throws InvalidCommandException
     */
    public function consume(
        CommandBus $bus,
        int $limit,
        OutputPrinter $outputPrinter
    ) {
        $this->resetIterations($limit);

        while (true) {
            $promise = $this
                ->redis
                ->blPop($this->key, 0)
                ->then(function (array $job) use ($bus, $outputPrinter) {
                    return $this->executeCommand(
                        $bus,
                        unserialize($job[1]),
                        $outputPrinter,
                        function () {},
                        function () {},
                        function () {
                            return true;
                        }
                    );
                });

            $wasLastOne = await($promise, $this->loop);

            if ($wasLastOne) {
                return;
            }
        }
    }
}
