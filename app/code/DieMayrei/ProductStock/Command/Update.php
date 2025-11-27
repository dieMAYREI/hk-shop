<?php


namespace DieMayrei\ProductStock\Command;

use DieMayrei\ProductStock\Console\ConsoleLoggerTrait;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\File\Csv;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Catalog\Model\ResourceModel\Product\Action;

class Update extends Command
{
    use ConsoleLoggerTrait;

    private const BATCH_SIZE = 100;

    public const MELDECODE_LIEFERUNG_14_TAGE = '01';
    public const MELDECODE_VERGRIFFEN_KEINE_NEUAUFLAGE = '07';
    public const MELDECODE_ERSCHEINUNG_IN_KUERZE = '11';

    public const ALLOWED_ATTRIBUTE_SETS = [
        4, // Zeitschrift
        9, // Sonderprodukt
    ];

    /**
     * @var Csv
     */
    protected $_csv;

    /**
     * @var State
     */
    private $state;

    /**
     * @var ProductRepositoryInterface
     */
    private $_productRepository;

    /**
     * @var ProductCollectionFactory
     */
    private ProductCollectionFactory $productCollectionFactory;

    /**
     * @var array
     */
    private array $pendingAttributeUpdates = [];

    /**
     * @var array
     */
    private array $pendingStockUpdates = [];

    /**
     * @var ObjectManager
     */
    private $_objectManager;

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    private $_connection;

    /**
     * @var Action
     */
    private $productAction;


    /**
     * Update constructor.
     * Initializes the class properties.
     * @param Csv $csv
     * @param State $state
     * @param ProductRepositoryInterface $productRepository
     * @param Action $productAction
     * @param \Magento\Framework\App\ResourceConnection $connection
     */
    public function __construct(
        Csv $csv,
        State $state,
        ProductRepositoryInterface $productRepository,
        Action $productAction,
        \Magento\Framework\App\ResourceConnection $connection,
        ProductCollectionFactory $productCollectionFactory = null
    ) {
        $this->_csv = $csv;
        $this->state = $state;
        $this->_objectManager = ObjectManager::getInstance();
        $this->_productRepository = $productRepository;
        $this->_connection = $connection;
        $this->productAction = $productAction;
        $this->productCollectionFactory = $productCollectionFactory ?: ObjectManager::getInstance()->get(ProductCollectionFactory::class);
        $this->initOutput(null);
        parent::__construct();
    }

    /**
     * Configures the command name.
     */
    protected function configure()
    {
        $this->setName('diemayrei:stockupdate');
        parent::configure();
    }

    /**
     * Executes the command.
     * It checks if the application is in production mode, sets the area code and runs the import.
     * @param  InputInterface  $input
     * @param  OutputInterface  $output
     * @return int|void|null
     * @throws LocalizedException
     * @throws \Exception
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initOutput($output);
        $this->logInfo('Starte Lagerbestands-Import.');

        $this->state->setAreaCode(Area::AREA_GLOBAL);
        $this->runImport();

        $this->logInfo('Lagerbestands-Import abgeschlossen.');
        return 0;
    }

    /**
     * Runs the import process.
     * It reads the CSV file and updates the products accordingly.
     */
    public function runImport()
    {
        $file_to_import = $this->getNewestFile();
        $filename = strrchr($file_to_import, '/');
        $filename = $filename ? substr($filename, 1) : $file_to_import;
        $this->logInfo(sprintf('Verarbeite Import-Datei %s.', $filename));
        $csvRows = $this->readCsvFile($file_to_import);

        if (empty($csvRows)) {
            $this->logWarning('CSV-Datei enthält keine Daten.');
            return;
        }

        $batches = array_chunk($csvRows, self::BATCH_SIZE);

        foreach ($batches as $batchIndex => $batchRows) {
            $skuMap = $this->buildSkuMap($batchRows);
            if (empty($skuMap)) {
                continue;
            }

            $baseSkus = array_keys($skuMap);
            $products = $this->loadProductsForBaseSkus($baseSkus);
            $indexedProducts = $this->indexProductsByBaseSku($products, $baseSkus);

            foreach ($skuMap as $baseSku => $rows) {
                $products = $this->resolveProductsFromCandidates($indexedProducts[$baseSku] ?? null);

                if (empty($products)) {
                    $this->logWarning(sprintf(
                        '%s (Batch %d): nicht gefunden.',
                        $baseSku,
                        $batchIndex + 1
                    ));
                    continue;
                }

                foreach ($rows as $row) {
                    foreach ($products as $product) {
                        if (in_array($product->getAttributeSetId(), self::ALLOWED_ATTRIBUTE_SETS) && !$this->isDigitalProduct($product)) {
                            $meldecode = isset($row[21]) ? trim($row[21]) : '';
                            $saved = $this->updateProduct($product, $row[1], $row[2], $meldecode);

                            if ($saved) {
                                $this->logInfo(sprintf(
                                    '%s (Batch %d): Bestand=%d, Meldecode=%s für %s aktualisiert.',
                                    $product->getSku(),
                                    $batchIndex + 1,
                                    $row[1],
                                    $meldecode,
                                    $product->getId()
                                ));
                            }
                        }
                    }
                }
            }

            $this->flushAttributeUpdates();
            $this->flushStockUpdates();
        }

        $this->flushAttributeUpdates();
        $this->flushStockUpdates();
    }

    /**
     * @param Product $product
     * @param int $quantity
     * @param string $verlag
     * @param string $meldecode
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\StateException
     */
    protected function updateProduct(Product $product, int $quantity, $verlag, $meldecode = ''): bool
    {
        // CSV ist ISO-8859-1 kodiert, konvertiere nach UTF-8 für Magento
        $verlag = mb_convert_encoding($verlag, 'UTF-8', 'ISO-8859-1');
        $meldecode = mb_convert_encoding((string)$meldecode, 'UTF-8', 'ISO-8859-1');

        try {
            $attributeUpdates = [
                'verlag' => $verlag,
                'meldecode' => $meldecode
            ];

            $meldecodeChanged = (string)$product->getData('meldecode') !== (string)$meldecode;
            if ($meldecodeChanged) {
                $attributeUpdates['meldecode_since'] = (new \DateTime())->format('Y-m-d H:i:s');
            }

            $stockData = [
                'use_config_manage_stock' => 0,
                'manage_stock' => 1,
                'qty' => $quantity,
                'is_in_stock' => $quantity > 0,
                'use_config_notify_stock_qty' => 1,
            ];

            if (in_array($meldecode, [self::MELDECODE_LIEFERUNG_14_TAGE, self::MELDECODE_ERSCHEINUNG_IN_KUERZE])) {
                $stockData = array_merge($stockData, [
                    'is_in_stock' => 1,
                    'use_config_backorders' => 0,
                    'backorders' => \Magento\CatalogInventory\Model\Stock::BACKORDERS_YES_NONOTIFY,
                ]);
            }

            if ($meldecode !== self::MELDECODE_ERSCHEINUNG_IN_KUERZE) {
                $attributeUpdates['erscheinungsdatum'] = '';
            }

            if ($quantity > 0) {
                $attributeUpdates['status'] = \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED;
                $attributeUpdates['lieferzeit'] = 'ca. 5–7 Werktage';
            }

            if (isset($attributeUpdates['status']) && (int)$product->getStatus() === (int)$attributeUpdates['status']) {
                unset($attributeUpdates['status']);
            }

            if (!$meldecodeChanged) {
                unset($attributeUpdates['meldecode']);
                unset($attributeUpdates['meldecode_since']);
            }

            if ((string)$product->getData('verlag') === $verlag) {
                unset($attributeUpdates['verlag']);
            }

            $changesApplied = false;

            if (!empty($attributeUpdates)) {
                $this->queueAttributeUpdate(0, $product->getId(), $attributeUpdates);
                $changesApplied = true;
            }

            if ($stockData !== null) {
                $this->queueStockUpdate($product->getId(), $stockData);
                $changesApplied = true;
            }

            return $changesApplied;
        } catch (\Exception $e) {
            $this->logError(sprintf(
                'Fehler beim Aktualisieren von %s (ID %s): %s',
                $product->getSku(),
                $product->getId(),
                $e->getMessage()
            ));
        }

        return false;
    }

    /**
     * Aktualisiert die Stock-Daten ohne vollständigen Produkt-Save.
     *
     * @param int $productId
     * @param array{use_config_manage_stock:int,is_in_stock:int,qty:int|float,manage_stock:int,use_config_notify_stock_qty:int} $stockData
     * @return bool
     */
    private function queueAttributeUpdate(int $storeId, int $productId, array $attributes): void
    {
        if (empty($attributes)) {
            return;
        }

        ksort($attributes);
        $hash = hash('sha256', json_encode($attributes));

        if (!isset($this->pendingAttributeUpdates[$storeId][$hash])) {
            $this->pendingAttributeUpdates[$storeId][$hash] = [
                'attributes' => $attributes,
                'product_ids' => [],
            ];
        }

        $this->pendingAttributeUpdates[$storeId][$hash]['product_ids'][] = $productId;
    }

    private function flushAttributeUpdates(): void
    {
        foreach ($this->pendingAttributeUpdates as $storeId => $groups) {
            foreach ($groups as $group) {
                $this->productAction->updateAttributes($group['product_ids'], $group['attributes'], $storeId);
            }
        }

        $this->pendingAttributeUpdates = [];
    }

    private function queueStockUpdate(int $productId, array $stockData): void
    {
        if (empty($stockData)) {
            return;
        }

        $this->pendingStockUpdates[$productId] = array_merge(
            $this->pendingStockUpdates[$productId] ?? [],
            $stockData
        );
    }

    private function flushStockUpdates(): void
    {
        if (empty($this->pendingStockUpdates)) {
            return;
        }

        $connection = $this->_connection->getConnection();
        $stockItemTable = $connection->getTableName('cataloginventory_stock_item');

        $groups = [];
        foreach ($this->pendingStockUpdates as $productId => $data) {
            ksort($data);
            $key = implode('|', array_keys($data));
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'columns' => array_keys($data),
                    'rows' => [],
                ];
            }

            $row = [
                'product_id' => $productId,
                'stock_id' => 1,
            ];

            foreach ($data as $field => $value) {
                $row[$field] = $this->normalizeStockFieldValue($field, $value);
            }

            $groups[$key]['rows'][] = $row;
        }

        foreach ($groups as $group) {
            $columns = array_merge(['product_id', 'stock_id'], $group['columns']);
            $rows = [];

            foreach ($group['rows'] as $row) {
                $rows[] = $row;
            }

            $updateColumns = array_diff($columns, ['product_id']);
            $connection->insertOnDuplicate($stockItemTable, $rows, $updateColumns);
        }

        $this->pendingStockUpdates = [];
    }

    private function normalizeStockFieldValue(string $field, $value)
    {
        return match ($field) {
            'qty' => (float)$value,
            'is_in_stock',
            'use_config_manage_stock',
            'manage_stock',
            'use_config_notify_stock_qty',
            'use_config_backorders',
            'backorders' => (int)$value,
            default => $value,
        };
    }

    private function buildSkuMap(array $csvRows): array
    {
        $skuMap = [];
        foreach ($csvRows as $row) {
            if (empty($row[0])) {
                continue;
            }
            $normalizedSku = trim($row[0]);
            $skuMap[$normalizedSku][] = $row;
        }

        return $skuMap;
    }

    /**
     * @param string[] $baseSkus
     * @return Product[]
     */
    private function loadProductsForBaseSkus(array $baseSkus): array
    {
        if (empty($baseSkus)) {
            return [];
        }

        $conditions = [];
        foreach ($baseSkus as $baseSku) {
            $conditions[] = ['attribute' => 'sku', 'like' => $baseSku . '%'];
            $conditions[] = ['attribute' => 'sku', 'eq' => $baseSku];
        }

        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect([
            'attribute_set_id',
            'verlag',
            'status',
            'meldecode',
            'erscheinungsdatum',
        ]);

        if (!empty($conditions)) {
            $collection->addAttributeToFilter($conditions);
        }

        return iterator_to_array($collection, false);
    }

    /**
     * @param Product[] $products
     * @param string[] $baseSkus
     * @return array<string, array{primary: Product[], secondary: Product[]}>
     */
    private function indexProductsByBaseSku(array $products, array $baseSkus): array
    {
        $index = [];
        foreach ($baseSkus as $baseSku) {
            $index[$baseSku] = ['primary' => [], 'secondary' => []];
        }

        foreach ($products as $product) {
            $sku = $product->getSku();
            foreach ($baseSkus as $baseSku) {
                if (strncmp($sku, $baseSku, strlen($baseSku)) !== 0) {
                    continue;
                }

                if (strpos($sku, $baseSku . '-') === 0) {
                    $index[$baseSku]['primary'][] = $product;
                } else {
                    $index[$baseSku]['secondary'][] = $product;
                }
            }
        }

        return $index;
    }

    private function resolveProductsFromCandidates(?array $candidates): array
    {
        if (!$candidates) {
            return [];
        }

        $products = [];

        foreach ($candidates['primary'] ?? [] as $product) {
            $products[$product->getId()] = $product;
        }

        foreach ($candidates['secondary'] ?? [] as $product) {
            $products[$product->getId()] = $product;
        }

        return array_values($products);
    }

    protected function readCsvFile($csv_file): array
    {
        $csv_data_array = [];
        $this->_csv->setDelimiter(';');
        try {
            $csv_data_array = $this->_csv->getData($csv_file);
        } catch (\Exception $e) {
        }

        // Remove header row
        $csv_data_array = array_slice($csv_data_array, 1);

        return $csv_data_array;
    }

    protected function getNewestFile(): string
    {

        $directory = $this->_objectManager->get('\Magento\Framework\Filesystem\DirectoryList');
        $rootPath = $directory->getRoot();
        $importFolder = $rootPath . '/productupload/';

        $files = scandir($importFolder, SCANDIR_SORT_DESCENDING);
        $newest_file = $files[0];

        return $importFolder . $newest_file;
    }

    protected function getLogPrefix(): string
    {
        return '[Stock Update]';
    }

    /**
     * Prüft, ob es sich um ein digitales Produkt handelt.
     * Digitale Produkte haben keinen physischen Bestand.
     *
     * - virtual: Virtuelle Produkte (inkl. "kein Gewicht" im Admin)
     * - downloadable: Download-Produkte
     *
     * @param Product $product
     * @return bool
     */
    private function isDigitalProduct(Product $product): bool
    {
        $digitalTypes = [
            \Magento\Catalog\Model\Product\Type::TYPE_VIRTUAL,
            \Magento\Downloadable\Model\Product\Type::TYPE_DOWNLOADABLE,
        ];

        return in_array($product->getTypeId(), $digitalTypes, true);
    }
}
