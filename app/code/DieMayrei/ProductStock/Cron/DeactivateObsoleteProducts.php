<?php

namespace DieMayrei\ProductStock\Cron;

use DieMayrei\ProductStock\Command\Update;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\Catalog\Model\ResourceModel\Product\Action as ProductAction;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Psr\Log\LoggerInterface;

class DeactivateObsoleteProducts
{
    private const MELDECODE_ATTRIBUTE = 'meldecode';
    private const MELDECODE_SINCE_ATTRIBUTE = 'meldecode_since';
    private const DISABLE_DELAY_WEEKS = 8;

    public function __construct(
        private readonly CollectionFactory $productCollectionFactory,
        private readonly ProductAction $productAction,
        private readonly TimezoneInterface $timezone,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Disable products that were marked as "vergriffen, keine Neuauflage" for > 8 weeks.
     */
    public function execute(): void
    {
        try {
            $cutoffDate = $this->timezone
                ->date(new \DateTime(sprintf('-%d weeks', self::DISABLE_DELAY_WEEKS)))
                ->format('Y-m-d H:i:s');

            $collection = $this->productCollectionFactory->create();
            $collection->addAttributeToSelect(['sku', 'name']);
            $collection->addAttributeToFilter(self::MELDECODE_ATTRIBUTE, ['eq' => Update::MELDECODE_VERGRIFFEN_KEINE_NEUAUFLAGE]);
            $collection->addAttributeToFilter(self::MELDECODE_SINCE_ATTRIBUTE, ['notnull' => true]);
            $collection->addAttributeToFilter(self::MELDECODE_SINCE_ATTRIBUTE, ['lteq' => $cutoffDate]);
            $collection->addAttributeToFilter('status', ['neq' => ProductStatus::STATUS_DISABLED]);

            $productIds = $collection->getAllIds();

            if (empty($productIds)) {
                $this->logger->debug('[Stock Update] Keine Produkte zum Deaktivieren gefunden.');
                return;
            }

            $this->productAction->updateAttributes(
                $productIds,
                ['status' => ProductStatus::STATUS_DISABLED],
                0
            );

            $this->logger->info(sprintf(
                '[Stock Update] %d Produkte mit Meldecode %s wurden nach %d Wochen deaktiviert.',
                count($productIds),
                Update::MELDECODE_VERGRIFFEN_KEINE_NEUAUFLAGE,
                self::DISABLE_DELAY_WEEKS
            ));
        } catch (LocalizedException|\Exception $exception) {
            $this->logger->error(sprintf(
                '[Stock Update] Fehler beim automatischen Deaktivieren von Produkten: %s',
                $exception->getMessage()
            ));
        }
    }
}
