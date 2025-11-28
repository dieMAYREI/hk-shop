<?php
declare(strict_types=1);

namespace DieMayrei\CoverImageImport\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;

/**
 * Dynamic rows for magazine configuration in admin
 */
class Magazines extends AbstractFieldArray
{
    /**
     * Prepare rendering the new field by adding all the needed columns
     */
    protected function _prepareToRender(): void
    {
        $this->addColumn('name', [
            'label' => __('Name'),
            'class' => 'required-entry',
            'style' => 'width:200px'
        ]);
        $this->addColumn('cover_url', [
            'label' => __('Cover URL'),
            'class' => 'required-entry validate-url',
            'style' => 'width:400px'
        ]);

        $this->_addAfter = false;
        $this->_addButtonLabel = __('Zeitschrift hinzufügen');
    }
}
