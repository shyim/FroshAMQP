<?php

namespace FroshAMQP\Commands;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Shopware\Commands\ShopwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractWorkerCommand extends ShopwareCommand
{
    /**
     * @var AMQPStreamConnection
     */
    protected $connection;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var string
     */
    protected $queueName = '';

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws \Doctrine\DBAL\DBALException
     * @throws \ErrorException
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->connection = $this->container->get('php_amqp_lib.connection.amqpstream_connection');
        $this->output = $output;
        $channel = $this->connection->channel();

        $channel->basic_consume($this->queueName, null, false, false, false, false, [$this, 'prepareMessage']);
        register_shutdown_function([$this, 'shutdown'], $channel, $this->connection);

        while (count($channel->callbacks)) {
            $this->wait($channel);
        }
    }

    /**
     * @param \PhpAmqpLib\Channel\AMQPChannel $channel
     * @param \PhpAmqpLib\Connection\AbstractConnection $connection
     */
    public function shutdown($channel, $connection)
    {
        $channel->close();
        $connection->close();
    }

    /**
     * @param AMQPMessage $message
     */
    public function prepareMessage(AMQPMessage $message)
    {
        $this->processMessage($message->body);

        $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
    }

    /**
     * @param $messageBody
     * @return void
     */
    abstract public function processMessage($messageBody);

    /**
     * @param AMQPChannel $channel
     * @throws \Doctrine\DBAL\DBALException
     * @throws \ErrorException
     */
    private function wait(AMQPChannel $channel)
    {
        $this->container->get('dbal_connection')->executeQuery('SELECT 1');
        $channel->wait();
    }
}