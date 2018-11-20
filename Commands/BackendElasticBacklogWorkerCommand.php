<?php

namespace FroshAMQP\Commands;

use FroshAMQP\Components\BackendOrmBacklogSubscriber;
use Shopware\Bundle\AttributeBundle\Repository\SearchCriteria;
use Shopware\Bundle\EsBackendBundle\EsBackendIndexer;
use Shopware\Bundle\ESIndexingBundle\LastIdQuery;
use Shopware\Models\Article\Article;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BackendElasticBacklogWorkerCommand extends AbstractWorkerCommand
{
    /**
     * @var string
     */
    protected $queueName = BackendOrmBacklogSubscriber::QUEUE_NAME;

    public function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
    }

    /**
     * @param $messageBody
     * @return void
     */
    public function processMessage($messageBody)
    {
        $backlog = unserialize($messageBody);

        $registry = $this->container->get('shopware_attribute.repository_registry');
        $indexer = $this->container->get('shopware_es_backend.indexer');

        $criteria = new SearchCriteria($backlog['entity']);

        $repository = $registry->getRepository($criteria);

        $this->output->writeln(sprintf('Sync %s with id %s', $backlog['entity'], $backlog['entity_id']));

        if ($backlog['entity'] === Article::class) {
            $this->indexArticle($backlog['entity_id']);
        } else {
            $index = $this->getIndexName($repository->getDomainName());
            $indexer->indexEntities($index, $repository, [$backlog['entity_id']]);
        }
    }

    private function indexArticle($id)
    {
        $query = $this->container->get('dbal_connection')->createQueryBuilder();
        $query = $query
            ->select(['products.id', 'products.ordernumber'])
            ->from('s_articles_details', 'products')
            ->andWhere('products.id > :lastId')
            ->andWhere('products.articleID = :article')
            ->setParameter(':lastId', 0)
            ->setParameter(':article', $id)
            ->addOrderBy('products.id')
            ->setMaxResults(50);

        $indexer = $this->container->get('shopware_es_backend.indexer');

        $query = new LastIdQuery($query);

        $repository = $this->container->get('shopware_attribute.product_repository');

        $index = $this->getIndexName($repository->getDomainName());

        while ($numbers = $query->fetch()) {
            $indexer->indexEntities($index, $repository, $numbers);
        }
    }

    private function getIndexName($domainName)
    {
        $client = $this->container->get('shopware_elastic_search.client');

        $alias = EsBackendIndexer::buildAlias($domainName);

        $exist = $client->indices()->getAlias(['name' => $alias]);

        $index = array_keys($exist);

        return array_shift($index);
    }
}