<?php
/**
 * Created by PhpStorm.
 * User: christianblomann
 * Date: 2019-02-20
 * Time: 09:02
 */

namespace Diemayrei\CoverImageImport\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

class CoverImageImport extends AbstractHelper
{
    protected $short_to_config = [
        'kein' => '',
        'Kaninchenzeitung' => 'diemayrei/cover_import/kaninchenzeitung_cover',
        'Kaninchenzeitung Digital' => 'diemayrei/cover_import/kaninchenzeitung_digital_cover',
        'Gefluegelzeitung' => 'diemayrei/cover_import/gefluegelzeitung_cover',
        'Gefluegelzeitung Digital' => 'diemayrei/cover_import/gefluegelzeitung_digital_cover',
    ];

    public function getConfig($config_path)
    {
        return $this->scopeConfig->getValue(
            $config_path,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getCoverArray()
    {
        return array_flip($this->short_to_config);
    }
}
