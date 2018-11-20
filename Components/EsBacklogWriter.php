<?php

namespace FroshAMQP\Components;

use PhpAmqpLib\Message\AMQPMessage;
use Shopware\Bundle\ESIndexingBundle\BacklogProcessorInterface;
use Shopware\Bundle\ESIndexingBundle\Struct\Backlog;
use Shopware\Bundle\ESIndexingBundle\Struct\ShopIndex;

class EsBacklogWriter implements BacklogProcessorInterface
{
    const QUEUE_NAME = 'elastic_frontend';

    /**
     * @var BacklogProcessorInterface
     */
    private $parentService;

    /**
     * @var SimpleMessagePublisherInterface
     */
    private $messagePublisher;

    /**
     * @param BacklogProcessorInterface $parentService
     * @param SimpleMessagePublisherInterface $messagePublisher
     */
    public function __construct(BacklogProcessorInterface $parentService, SimpleMessagePublisherInterface $messagePublisher)
    {
        $this->parentService = $parentService;
        $this->messagePublisher = $messagePublisher;
    }

    /**
     * @param Backlog[] $backlogs
     */
    public function add($backlogs)
    {
        if (empty($backlogs)) {
            return;
        }

        foreach ($backlogs as $backlog) {
            $message = new AMQPMessage(serialize($backlog), [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
            ]);

            $this->messagePublisher->publish(self::QUEUE_NAME, $message);
        }
    }

    /**
     * @param ShopIndex $shopIndex
     * @param Backlog[] $backlogs
     */
    public function process(ShopIndex $shopIndex, $backlogs)
    {
        return $this->parentService->process($shopIndex, $backlogs);
    }
}