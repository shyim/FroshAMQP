<?php

namespace FroshAMQP\Components;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use PhpAmqpLib\Message\AMQPMessage;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Article\Article;
use Shopware\Models\Article\Detail as Variant;
use Shopware\Models\Article\Price;
use Shopware\Models\Customer\Address;
use Shopware\Models\Customer\Customer;
use Shopware\Models\Order\Billing;
use Shopware\Models\Order\Detail;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Shipping;
use Symfony\Component\DependencyInjection\ContainerInterface;

class BackendOrmBacklogSubscriber implements EventSubscriber
{
    const QUEUE_NAME = 'elastic_backend';

    /**
     * @var ContainerInterface
     */
    private $container;
    /**
     * @var SimpleMessagePublisherInterface
     */
    private $messagePublisher;

    /**
     * @param ContainerInterface $container
     * @param SimpleMessagePublisherInterface $messagePublisher
     */
    public function __construct(ContainerInterface $container, SimpleMessagePublisherInterface $messagePublisher)
    {
        $this->container = $container;
        $this->messagePublisher = $messagePublisher;
    }

    /**
     * {@inheritdoc}
     */
    public function getSubscribedEvents()
    {
        if (!$this->container->getParameter('shopware.es.backend.enabled')) {
            return [];
        }
        if (!$this->container->getParameter('shopware.es.backend.write_backlog')) {
            return [];
        }

        return [Events::onFlush];
    }

    /**
     * {@inheritdoc}
     */
    public function onFlush(OnFlushEventArgs $eventArgs)
    {
        try {
            $this->trace($eventArgs);
        } catch (\Exception $e) {
            $this->container->get('corelogger')->error($e->getMessage());
        }
    }

    /**
     * @param mixed $entity
     *
     * @return array|null
     */
    private function getBacklog($entity)
    {
        switch (true) {
            // Article changes
            case $entity instanceof Article:
                return ['entity' => Article::class, 'entity_id' => $entity->getId()];

            // Variant changes
            case $entity instanceof Price:
                return ['entity' => Variant::class, 'entity_id' => $entity->getDetail()->getNumber()];
            case $entity instanceof Variant:
                return ['entity' => Variant::class, 'entity_id' => $entity->getNumber()];

            // Order changes
            case $entity instanceof Order:
                return ['entity' => Order::class, 'entity_id' => $entity->getId()];
            case $entity instanceof Detail:
                return ['entity' => Order::class, 'entity_id' => $entity->getOrder()->getId()];
            case $entity instanceof Billing:
                return ['entity' => Order::class, 'entity_id' => $entity->getOrder()->getId()];
            case $entity instanceof Shipping:
                return ['entity' => Order::class, 'entity_id' => $entity->getOrder()->getId()];

            // Customer changes
            case $entity instanceof Customer:
                return ['entity' => Customer::class, 'entity_id' => $entity->getId()];
            case $entity instanceof Address:
                return ['entity' => Customer::class, 'entity_id' => $entity->getCustomer()->getId()];
        }

        return null;
    }

    /**
     * @param OnFlushEventArgs $eventArgs
     */
    private function trace(OnFlushEventArgs $eventArgs)
    {
        /** @var ModelManager $em */
        $em = $eventArgs->getEntityManager();
        $uow = $em->getUnitOfWork();

        $queue = [];
        // Entity deletions
        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            $backlog = $this->getBacklog($entity);
            if (!$backlog) {
                continue;
            }
            $queue[] = $backlog;
        }

        // Entity Insertions
        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            $backlog = $this->getBacklog($entity);
            if (!$backlog) {
                continue;
            }
            $queue[] = $backlog;
        }

        // Entity updates
        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            $backlog = $this->getBacklog($entity);
            if (!$backlog) {
                continue;
            }
            $queue[] = $backlog;
        }

        $time = (new \DateTime())->format('Y-m-d H:i:s');
        foreach ($queue as $row) {
            $row['time'] = $time;

            $message = new AMQPMessage(serialize($row), [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
            ]);

            $this->messagePublisher->publish(self::QUEUE_NAME, $message);
        }
    }
}