<?php

declare(strict_types=1);

namespace DieMayrei\CustomBlocks\Block\Cms;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Search\Helper\Data as SearchHelper;
use Magento\Search\ViewModel\ConfigProvider as SearchConfig;

class Search extends Template
{
    private SearchHelper $searchHelper;

    private SearchConfig $searchConfig;

    public function __construct(
        Context $context,
        SearchHelper $searchHelper,
        SearchConfig $searchConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->searchHelper = $searchHelper;
        $this->searchConfig = $searchConfig;
    }

    public function getMinQueryLength(): int
    {
        return (int) $this->searchHelper->getMinQueryLength();
    }

    public function getMaxQueryLength(): int
    {
        return (int) $this->searchHelper->getMaxQueryLength();
    }

    public function getQueryParamName(): string
    {
        return $this->searchHelper->getQueryParamName();
    }

    public function getEscapedQueryText(): string
    {
        return $this->searchHelper->getEscapedQueryText();
    }

    public function getResultUrl(): string
    {
        return $this->searchHelper->getResultUrl();
    }

    public function isSuggestionsAllowed(): bool
    {
        return $this->searchConfig->isSuggestionsAllowed();
    }
}
