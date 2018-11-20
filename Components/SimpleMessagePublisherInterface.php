<?php

namespace FroshAMQP\Components;

use PhpAmqpLib\Message\AMQPMessage;

interface SimpleMessagePublisherInterface
{
    /**
     * @param string $queueName
     * @param AMQPMessage $message
     * @return mixed
     */
    public function publish(string $queueName, AMQPMessage $message);
}