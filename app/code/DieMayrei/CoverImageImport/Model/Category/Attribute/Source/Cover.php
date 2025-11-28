<?php
namespace Diemayrei\CoverImageImport\Model\Category\Attribute\Source;

use Diemayrei\CoverImageImport\Helper\CoverImageImport;

class Cover extends \Magento\Eav\Model\Entity\Attribute\Source\AbstractSource
{
    /**
     * Catalog config
     *
     * @var \Magento\Catalog\Model\Config
     */
    protected $_catalogConfig;

    /**
     * @var CoverImageImport
     */
    protected $_coverImageImport;

    /**
     * Construct
     *
     * @param \Magento\Catalog\Model\Config $catalogConfig
     */
    public function __construct(
        \Magento\Catalog\Model\Config $catalogConfig,
        CoverImageImport $coverImageImport
    ) {
        $this->_catalogConfig = $catalogConfig;
        $this->_coverImageImport = $coverImageImport;
    }

    /**
     * Retrieve Catalog Config Singleton
     *
     * @return \Magento\Catalog\Model\Config
     */
    protected function _getCatalogConfig()
    {
        return $this->_catalogConfig;
    }

    /**
     * {@inheritdoc}
     */
    public function getAllOptions()
    {

        $options = [];
        foreach ($this->_coverImageImport->getCoverArray() as $key => $value) {
            $options[] = ['label' => $value, 'value' => $key];
        }
        return $options;
    }

    public function getOptionArray()
    {
        $options = [];
        foreach ($this->_coverImageImport->getCoverArray() as $key => $value) {
            $options[] = ['label' => $value, 'value' => $key];
        }

        return $options;
    }
}
