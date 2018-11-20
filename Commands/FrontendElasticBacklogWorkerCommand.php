<?php

namespace FroshAMQP\Commands;

use FroshAMQP\Components\EsBacklogWriter;
use Shopware\Bundle\ESIndexingBundle\BacklogProcessorInterface;
use Shopware\Bundle\ESIndexingBundle\IdentifierSelector;
use Shopware\Bundle\ESIndexingBundle\IndexFactoryInterface;
use Shopware\Bundle\ESIndexingBundle\Struct\Backlog;
use Shopware\Bundle\ESIndexingBundle\Struct\ShopIndex;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FrontendElasticBacklogWorkerCommand extends AbstractWorkerCommand
{
    /**
     * @var IdentifierSelector
     */
    private $identifierSelector;

    /**
     * @var IndexFactoryInterface
     */
    private $indexFactory;

    /**
     * @var BacklogProcessorInterface
     */
    private $backlogProcessor;

    /**
     * @var ShopIndex[]
     */
    private $shopIndexes;

    /**
     * @var string
     */
    protected $queueName = EsBacklogWriter::QUEUE_NAME;

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->indexFactory = $this->container->get('shopware_elastic_search.index_factory');
        $this->identifierSelector = $this->container->get('shopware_elastic_search.identifier_selector');
        $this->backlogProcessor = $this->container->get('shopware_elastic_search.backlog_processor');

        $shops = $this->identifierSelector->getShops();

        foreach ($shops as $shop) {
            $this->shopIndexes[] = $this->indexFactory->createShopIndex($shop, '');
        }

        parent::execute($input, $output);
    }

    /**
     * @param $messageBody
     * @return void
     */
    public function processMessage($messageBody)
    {
        /** @var Backlog $backlog */
        $backlog = unserialize($messageBody);

        $this->output->writeln(sprintf('Process message from type "%s" with payload %s', $backlog->getEvent(), json_encode($backlog->getPayload())));

        foreach ($this->shopIndexes as $shopIndex) {
            $this->backlogProcessor->process($shopIndex, [$backlog]);
        }
    }
}