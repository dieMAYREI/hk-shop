<?php

namespace DieMayrei\EmailNotice\Traits;

trait FormatEmailVars
{

    /**
     * @param  int  $value
     * @return string
     */
    public function getAnrede($value)
    {
        if (in_array($value, [
            'Herr',
            'Frau',
            'Divers',
            'Firma',
            'Familie',
        ])) {
            return $value;
        };

        $prefix = '';
        switch ($value) {
            case '0':
                $prefix = 'Herr';
                break;
            case '1':
                $prefix = 'Frau';
                break;
            case '2':
                $prefix = 'Divers';
                break;
            case '3':
                $prefix = 'Firma';
                break;
            case '4':
                $prefix = 'Familie';
                break;
        }

        return $prefix;
    }

    public function getTitle($value)
    {

        if (in_array($value, [
            'Dipl.-Energiew.',
            'Dipl.-Ing.',
            'Dipl.-Ing. (FH)',
            'Dipl.-Ing. agrar',
            'Dipl.-Forsting.',
            'Dipl. Gartenbau-Ing.',
            'Dipl.-Betriebsw.',
            'Dipl.-Forstw.',
            'Dipl.-Agrarw.',
            'Dipl.-Biol.',
            'Dipl-Kfm.',
            'Dipl.-Kff.',
            'Dr. med.',
            'Dr. med. dent.',
            'Dr. med. vet.',
            'Direktor',
            'Dr.',
            'Mag.',
            'Rechtsanwalt',
            'Rechtsanwältin',
            'Dr. mult.',
            'Dr. h. c.',
            'Professor',
            'Prof. Dr.',
            'Dr. jur.',
            'Dr. Dr.',
            'Ing.',
            'Landrat',
            'Staatssekretär'
        ])) {
            return $value;
        };

        $prefix = '';
        switch ($value) {
            case '0':
                $prefix = ' ';
                break;
            case '1':
                $prefix = 'Dipl.-Energiew.';
                break;
            case '2':
                $prefix = 'Dipl.-Ing.';
                break;
            case '3':
                $prefix = 'Dipl.-Ing. (FH)';
                break;
            case '4':
                $prefix = 'Dipl.-Ing. agrar';
                break;
            case '5':
                $prefix = 'Dipl.-Forsting.';
                break;
            case '6':
                $prefix = 'Dipl. Gartenbau-Ing.';
                break;
            case '7':
                $prefix = 'Dipl.-Betriebsw.';
                break;
            case '8':
                $prefix = 'Dipl.-Forstw.';
                break;
            case '9':
                $prefix = 'Dipl.-Agrarw.';
                break;
            case '10':
                $prefix = 'Dipl.-Biol.';
                break;
            case '11':
                $prefix = 'Dipl-Kfm.';
                break;
            case '12':
                $prefix = 'Dipl.-Kff.';
                break;
            case '13':
                $prefix = 'Dr. med.';
                break;
            case '14':
                $prefix = 'Dr. med. dent.';
                break;
            case '15':
                $prefix = 'Dr. med. vet.';
                break;
            case '16':
                $prefix = 'Direktor';
                break;
            case '17':
                $prefix = 'Dr.';
                break;
            case '18':
                $prefix = 'Mag.';
                break;
            case '19':
                $prefix = 'Rechtsanwalt';
                break;
            case '20':
                $prefix = 'Rechtsanwältin';
                break;
            case '21':
                $prefix = 'Dr. mult.';
                break;
            case '22':
                $prefix = 'Dr. h. c.';
                break;
            case '23':
                $prefix = 'Professor';
                break;
            case '24':
                $prefix = 'Prof. Dr.';
                break;
            case '25':
                $prefix = 'Dr. jur.';
                break;
            case '26':
                $prefix = 'Dr. Dr.';
                break;
            case '27':
                $prefix = 'Ing.';
                break;
            case '28':
                $prefix = 'Landrat';
                break;
            case '29':
                $prefix = 'Staatssekretär';
                break;
        }
        return $prefix;
    }

    /**
     * @param $title
     * @return string
     */
    protected function getTitleCode($title)
    {
        $title_codes = [
            'Dipl.-Energiew.'  => 'DIPEW',
            'Dipl.-Ing.'  => 'DIP',
            'Dipl.-Ing. (FH)'  => 'DIPFH',
            'Dipl.-Ing. agrar'  => 'DIPA',
            'Dipl.-Forsting.'  => 'DIPFO',
            'Dipl. Gartenbau-Ing.'  => 'DIPGA',
            'Dipl.-Betriebsw.'  => 'DIPBW',
            'Dipl.-Forstw.'  => 'DIPFOW',
            'Dipl.-Agrarw.'  => 'DIPAW',
            'Dipl.-Biol.'  => 'DIPBIO',
            'Dipl-Kfm.'  => 'DIPKFM',
            'Dipl.-Kff.'  => 'DIPKFF',
            'Dr. med.'  => 'DRMED',
            'Dr. med. dent.'  => 'DRDENT',
            'Dr. med. vet.'  => 'DRVET',
            'Direktor'  => 'DIR',
            'Dr.'  => 'DR',
            'Mag.'  => 'MAG',
            'Rechtsanwalt'  => 'RA',
            'Rechtsanwältin'  => 'RAI',
            'Dr. mult.'  => 'DRMULT',
            'Dr. h. c.'  => 'DRHC',
            'Professor'  => 'PROF',
            'Prof. Dr.'  => 'PDR',
            'Dr. jur.'  => 'DRJ',
            'Dr. Dr.'  => 'DRDR',
            'Ing.'  => 'ING',
            'Landrat'  => 'LR',
            'Staatssekretär'  => 'STAAT'
        ];


        if (array_key_exists($title, $title_codes)) {
            return $title_codes[$title];
        }

        return $title;
    }

    public function getMyCustomBillingAddress(
        \Magento\Sales\Api\Data\OrderAddressInterface $billingAddress,
        $type = 'billing'
    ) {
        $address = '';
        switch ($billingAddress->getPrefix()) {
            case 2:
                if ($billingAddress->getSuffix()) {
                    $address .= $billingAddress->getSuffix()." ";
                }
                if ($billingAddress->getFirstname()) {
                    $address .= $billingAddress->getFirstname().' ';
                }
                if ($billingAddress->getMiddlename()) {
                    $address .= $billingAddress->getMiddlename().' ';
                }
                if ($billingAddress->getLastname()) {
                    $address .= $billingAddress->getLastname()."\r\n";
                }
                if ($billingAddress->getCompany()) {
                    $address .= $billingAddress->getCompany()."\r\n";
                }
                break;
                break;
            case 3:
                $address .= 'Firma ';
                if ($billingAddress->getCompany()) {
                    $address .= $billingAddress->getCompany()."\r\n";
                }
                if ($billingAddress->getSuffix()) {
                    $address .= $billingAddress->getSuffix().'';
                }
                if ($billingAddress->getFirstname()) {
                    $address .= $billingAddress->getFirstname().' ';
                }
                if ($billingAddress->getMiddlename()) {
                    $address .= $billingAddress->getMiddlename().' ';
                }
                if ($billingAddress->getLastname()) {
                    $address .= $billingAddress->getLastname()."\r\n";
                }

                break;
                break;
            case 4:
                $address .= 'Familie ';
                if ($billingAddress->getSuffix()) {
                    $address .= $billingAddress->getSuffix()."";
                }
                if ($billingAddress->getMiddlename()) {
                    $address .= $billingAddress->getMiddlename().' ';
                }
                if ($billingAddress->getLastname()) {
                    $address .= $billingAddress->getLastname()."\r\n";
                }
                if ($billingAddress->getCompany()) {
                    $address .= $billingAddress->getCompany()."\r\n";
                }
                break;
            case '0':
                $address .= 'Herr ';
                if ($billingAddress->getSuffix()) {
                    $address .= $billingAddress->getSuffix()."\r\n";
                }
                if ($billingAddress->getFirstname()) {
                    $address .= $billingAddress->getFirstname().' ';
                }
                if ($billingAddress->getMiddlename()) {
                    $address .= $billingAddress->getMiddlename().' ';
                }
                if ($billingAddress->getLastname()) {
                    $address .= $billingAddress->getLastname()."\r\n";
                }
                if ($billingAddress->getCompany()) {
                    $address .= $billingAddress->getCompany()."\r\n";
                }
                break;
            case 1:
                $address .= 'Frau ';
                if ($billingAddress->getSuffix()) {
                    $address .= $billingAddress->getSuffix()."\r\n";
                }
                if ($billingAddress->getFirstname()) {
                    $address .= $billingAddress->getFirstname().' ';
                }
                if ($billingAddress->getMiddlename()) {
                    $address .= $billingAddress->getMiddlename().' ';
                }
                if ($billingAddress->getLastname()) {
                    $address .= $billingAddress->getLastname()."\r\n";
                }
                if ($billingAddress->getCompany()) {
                    $address .= $billingAddress->getCompany()."\r\n";
                }
                break;
            default:
                if ($billingAddress->getPrefix()) {
                    $address .= $billingAddress->getPrefix().' ';
                }
                if ($billingAddress->getSuffix()) {
                    $address .= $billingAddress->getSuffix()."\r\n";
                }
                if ($billingAddress->getFirstname()) {
                    $address .= $billingAddress->getFirstname().' ';
                }
                if ($billingAddress->getMiddlename()) {
                    $address .= $billingAddress->getMiddlename().' ';
                }
                if ($billingAddress->getLastname()) {
                    $address .= $billingAddress->getLastname()."\r\n";
                }
                if ($billingAddress->getCompany()) {
                    $address .= $billingAddress->getCompany()."\r\n";
                }
                break;
        }

        if ($billingAddress->getStreet()) {
            $streetLines = $billingAddress->getStreet();
            $counter = 0;
            foreach ($streetLines as $lineNumber => $lineValue) {
                if ($lineValue != '') {
                    $address .= $lineValue.' ';
                    if ($counter == 1) {
                        $address .= "\r\n";
                    }
                    if ($counter == 3) {
                        $address .= "\r\n";
                    }
                }
                $counter++;
            }
        }
        if ($billingAddress->getCity()) {
            $address .= $billingAddress->getCity().', ';
        }
        if ($billingAddress->getRegion()) {
            $address .= $billingAddress->getRegion().', ';
        }
        if ($billingAddress->getPostcode()) {
            $address .= $billingAddress->getPostcode()."\r\n";
        }
        if ($billingAddress->getCountryId()) {
            $country = $this->objectManager->create('\Magento\Directory\Model\Country')->load($billingAddress->getCountryId())->getName();
            $address .= $country."\r\n";
        }
        if ($type == 'billing') {
            if ($billingAddress->getTelephone()) {
                $address .= "T:  <a href=\"tel:".$billingAddress->getTelephone()."\">".$billingAddress->getTelephone()."</a>\r\n";
            }
            if ($billingAddress->getFax()) {
                $address .= "F: ".$billingAddress->getFax()."\r\n";
            }
            if ($billingAddress->getVatId()) {
                $address .= "VAT: ".$billingAddress->getVatId()."\r\n";
            }
        }
        return $address;
    }
}
