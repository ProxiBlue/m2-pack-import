<?php

declare(strict_types=1);

namespace ProxiBlue\PackImport\Observer;

use Magento\Catalog\Model\Product;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Nanobots\ProductPack\Api\Data\PackOptionInterface;
use Nanobots\ProductPack\Api\PackOptionRepositoryInterface;

class Import implements ObserverInterface
{
    protected $currentPackProducts;
    protected $tableName;

    /**
     * @param \Magento\Framework\App\ResourceConnection $connection
     * @param \Magento\Catalog\Model\Product $product
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        protected ResourceConnection $resourceConnection,
        protected Product $product,
        protected PackOptionRepositoryInterface $packOptionRepository,
        protected SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->currentPackProducts = [];
        $this->searchCriteriaBuilder->addFilter(PackOptionInterface::PRODUCT_ID, '-1', 'neq');
        $packOptions = $this->packOptionRepository->getList($this->searchCriteriaBuilder->create())->getItems();
        foreach ($packOptions as $packOption) {
            $this->currentPackProducts[] = $packOption->getProductId();
        }
        $this->tableName = $this->resourceConnection->getTableName('nanobots_productpack_packoption');
    }

    public function execute(Observer $observer): void
    {
        $adapter = $observer->getEvent()->getAdapter();
        $bunch = $observer->getEvent()->getBunch();
        foreach ($bunch as $row) {
            $row['product_id'] = (int) $this->product->getIdBySku($row['sku']);
            if (isset($row['pack_data']) && $row['product_type'] == \Nanobots\ProductPack\Model\Product\Type\Pack::TYPE_CODE) {
                $this->writePackOption($row);
                $adapter->addLogWriteln(
                    sprintf(
                        'Pack data was added/updated for product with sku %s',
                        $row['sku']
                    )
                );
            } elseif (isset($row['pack_data']) && $row['product_type'] != \Nanobots\ProductPack\Model\Product\Type\Pack::TYPE_CODE) {
                $adapter->addLogWriteln(
                    sprintf(
                        'Product %s is of type %s, but has pack data. Pack data will be ignored until product type is set to "%s"',
                        $row['sku'],
                        $row['product_type'],
                        \Nanobots\ProductPack\Model\Product\Type\Pack::TYPE_CODE
                    ),
                    null,
                    'error'
                );
            } elseif ((isset($row['pack_data'])
                && empty($row['pack_data'])
                && $row['product_type'] == \Nanobots\ProductPack\Model\Product\Type\Pack::TYPE_CODE)
            || (!isset($row['pack_data'])
                && $row['product_type'] == \Nanobots\ProductPack\Model\Product\Type\Pack::TYPE_CODE)) {
                $adapter->addLogWriteln(
                    sprintf(
                        'Product %s is of type %s, but has no pack data',
                        $row['sku'],
                        $row['product_type']
                    ),
                    null,
                    'warning'
                );
            } elseif ($row['product_type'] == \Magento\Catalog\Model\Product\Type::TYPE_SIMPLE
                && in_array($row['product_id'], $this->currentPackProducts)) {
                $this->resourceConnection->getConnection()->delete(
                    $this->tableName,
                    ['product_id = ?' => $row['product_id']]
                );
                $adapter->addLogWriteln(
                    sprintf(
                        'Product %s is of type %s, has no import pack data, but has saved pack data. Pack data was removed from database.',
                        $row['sku'],
                        $row['product_type']
                    ),
                    null,
                    'warning'
                );
            }
        }
    }

    /**
     * @param $row
     */
    public function writePackOption($row)
    {
        $packData = json_decode((string) $row['pack_data'], true);
        foreach ($packData as $packOption) {
            $packOption['product_id'] = $row['product_id'];
            $this->resourceConnection->getConnection()
                ->insertOnDuplicate(
                    $this->tableName,
                    $packOption,
                    array_keys($packOption)
                );
        }
    }
}
