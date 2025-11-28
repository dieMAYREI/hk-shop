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
        'afz' => 'diemayrei/cover_import/afz_cover',
        'afz Digital' => 'diemayrei/cover_import/afz_digital_cover',
        'agrarheute' => 'diemayrei/cover_import/agrarheute_cover',
        'agrarheute Digital' => 'diemayrei/cover_import/agrarheute_digital_cover',
        'agrarheute ENERGIE' => 'diemayrei/cover_import/agrarheute_energie_cover',
        'agrarheute RIND' => 'diemayrei/cover_import/agrarheute_rind_cover',
        'agrarheute SCHWEIN' => 'diemayrei/cover_import/agrarheute_schwein_cover',
        'agrartechnik' => 'diemayrei/cover_import/agrartechnik_cover',
        'agrartechnik Digital' => 'diemayrei/cover_import/agrartechnik_digital_cover',
        'almbauer' => 'diemayrei/cover_import/almbauer_cover',
        'bauernzeitung' => 'diemayrei/cover_import/bauernzeitung_cover',
        'bauernzeitung Digital' => 'diemayrei/cover_import/bauernzeitung_digital_cover',
        'bayernspferde' => 'diemayrei/cover_import/bayernspferde_cover',
        'bergjagd' => 'diemayrei/cover_import/bergjagd_cover',
        'bergjagd Digital' => 'diemayrei/cover_import/bergjagd_digital_cover',
        'bienen und natur' => 'diemayrei/cover_import/bienen_und_natur_cover',
        'bienen und natur Digital' => 'diemayrei/cover_import/bienen_und_natur_digital_cover',
        'bienenjournal' => 'diemayrei/cover_import/bienenjournal_cover',
        'bienenjournal Digital' => 'diemayrei/cover_import/bienenjournal_digital_cover',
        'blw' => 'diemayrei/cover_import/blw_cover',
        'blw Digital' => 'diemayrei/cover_import/blw_digital_cover',
        'braunvieh' => 'diemayrei/cover_import/braunvieh_cover',
        'deutscher waldbesitzer' => 'diemayrei/cover_import/deutscher_waldbesitzer_cover',
        'deutscher waldbesitzer Digital' => 'diemayrei/cover_import/deutscher_waldbesitzer_digital_cover',
        'fleckvieh' => 'diemayrei/cover_import/fleckvieh_cover',
        'food and farm' => 'diemayrei/cover_import/food_and_farm_cover',
        'fut' => 'diemayrei/cover_import/fut_cover',
        'fut Digital' => 'diemayrei/cover_import/fut_digital_cover',
        'gemuese' => 'diemayrei/cover_import/gemuese_cover',
        'gemuese Digital' => 'diemayrei/cover_import/gemuese_digital_cover',
        'hcx' => 'diemayrei/cover_import/hcx_cover',
        'holsteininternational' => 'diemayrei/cover_import/holsteininternational_cover',
        'jgh' => 'diemayrei/cover_import/jgh_cover',
        'jgh Digital' => 'diemayrei/cover_import/jgh_digital_cover',
        'kur' => 'diemayrei/cover_import/kur_cover',
        'kur Digital' => 'diemayrei/cover_import/kur_digital_cover',
        'luf' => 'diemayrei/cover_import/luf_cover',
        'luf Digital' => 'diemayrei/cover_import/luf_digital_cover',
        'lw oesterreich' => 'diemayrei/cover_import/lw_oesterreich_cover',
        'lw oesterreich Digital' => 'diemayrei/cover_import/lw_oesterreich_digital_cover',
        'nj' => 'diemayrei/cover_import/nj_cover',
        'nj Digital' => 'diemayrei/cover_import/nj_digital_cover',
        'oldenburgerinternational' => 'diemayrei/cover_import/oldenburgerinternational_cover',
        'pirsch' => 'diemayrei/cover_import/pirsch_cover',
        'pirsch Digital' => 'diemayrei/cover_import/pirsch_digital_cover',
        'traction' => 'diemayrei/cover_import/traction_cover',
        'traction Digital' => 'diemayrei/cover_import/traction_digital_cover',
        'unsere jagd' => 'diemayrei/cover_import/unsere_jagd_cover',
        'unsere jagd Digital' => 'diemayrei/cover_import/unsere_lagd_digital_cover',
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
