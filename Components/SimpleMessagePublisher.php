<?php

namespace FroshAMQP\Components;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Class SimpleMessagePublisher
 * @package FroshRabbitMQ\Components
 */
class SimpleMessagePublisher implements SimpleMessagePublisherInterface
{
    /**
     * @var array
     */
    private $queueRegistered = [];

    /**
     * @var AMQPStreamConnection
     */
    private $connection;

    /**
     * SimpleMessagePublisher constructor.
     * @param AMQPStreamConnection $connection
     */
    public function __construct(AMQPStreamConnection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param string $queueName
     * @param AMQPMessage $message
     */
    public function publish(string $queueName, AMQPMessage $message)
    {
        $channel = $this->connection->channel();

        if (!isset($this->queueRegistered[$queueName])) {
            $channel->queue_declare($queueName, false, true, false, false);
            $this->queueRegistered[$queueName] = true;
        }

        $channel->basic_publish($message, '', $queueName);
    }
}