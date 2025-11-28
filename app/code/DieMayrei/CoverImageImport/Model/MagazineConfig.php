<?php
declare(strict_types=1);

namespace DieMayrei\CoverImageImport\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

/**
 * Dynamic configuration for magazines.
 * All magazines are configured via Admin > Stores > Configuration > DieMayrei > Cover Import
 */
class MagazineConfig
{
    private const CONFIG_PATH_MAGAZINES = 'diemayrei/cover_import/magazines';

    private ScopeConfigInterface $scopeConfig;
    private Json $json;
    private LoggerInterface $logger;
    private ?array $magazinesCache = null;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Json $json,
        LoggerInterface $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->json = $json;
        $this->logger = $logger;
    }

    /**
     * Get all configured magazines from admin
     *
     * @return array
     */
    public function getMagazines(): array
    {
        if ($this->magazinesCache !== null) {
            return $this->magazinesCache;
        }

        $value = $this->scopeConfig->getValue(
            self::CONFIG_PATH_MAGAZINES,
            ScopeInterface::SCOPE_STORE
        );

        if (empty($value)) {
            $this->magazinesCache = [];
            return [];
        }

        try {
            if (is_string($value)) {
                $magazines = $this->json->unserialize($value);
            } else {
                $magazines = $value;
            }

            // Process and normalize magazines
            $processed = [];
            foreach ($magazines as $rowId => $row) {
                if (empty($row['name']) || empty($row['cover_url'])) {
                    continue;
                }

                // Auto-generate key from name
                $key = $this->generateKey($row['name']);
                
                $processed[$rowId] = [
                    'name' => $row['name'],
                    'key' => $key,
                    'cover_url' => $row['cover_url']
                ];
            }

            $this->magazinesCache = $processed;
            return $processed;

        } catch (\Exception $e) {
            $this->logger->error('CoverImageImport: Failed to parse magazines config', [
                'exception' => $e->getMessage()
            ]);
            $this->magazinesCache = [];
            return [];
        }
    }

    /**
     * Generate a URL-safe key from name
     *
     * @param string $name
     * @return string
     */
    private function generateKey(string $name): string
    {
        $key = mb_strtolower($name);
        
        // German umlauts
        $key = str_replace(
            ['ä', 'ö', 'ü', 'ß', 'Ä', 'Ö', 'Ü'],
            ['ae', 'oe', 'ue', 'ss', 'ae', 'oe', 'ue'],
            $key
        );
        
        // Replace spaces and special chars with underscore
        $key = preg_replace('/[^a-z0-9]+/', '_', $key);
        
        // Remove leading/trailing underscores
        return trim($key, '_');
    }

    /**
     * Get all magazine options for dropdowns (including 'kein')
     *
     * @return array
     */
    public function getAllOptions(): array
    {
        $options = [
            ['value' => '', 'label' => __('-- Kein Cover --')]
        ];

        foreach ($this->getMagazines() as $magazine) {
            $options[] = [
                'value' => $magazine['key'],
                'label' => __($magazine['name'])
            ];
        }

        return $options;
    }

    /**
     * Get cover URL for a specific magazine key
     *
     * @param string $coverKey
     * @return string|null
     */
    public function getCoverUrl(string $coverKey): ?string
    {
        if (empty($coverKey)) {
            return null;
        }

        foreach ($this->getMagazines() as $magazine) {
            if ($magazine['key'] === $coverKey) {
                return $magazine['cover_url'];
            }
        }

        return null;
    }

    /**
     * Get all configured cover URLs with their keys
     *
     * @return array ['key' => 'url', ...]
     */
    public function getAllCoverUrls(): array
    {
        $urls = [];

        foreach ($this->getMagazines() as $magazine) {
            $urls[$magazine['key']] = $magazine['cover_url'];
        }

        return $urls;
    }

    /**
     * Get label for a cover key
     *
     * @param string $coverKey
     * @return string
     */
    public function getLabel(string $coverKey): string
    {
        if (empty($coverKey)) {
            return 'kein';
        }

        foreach ($this->getMagazines() as $magazine) {
            if ($magazine['key'] === $coverKey) {
                return $magazine['name'];
            }
        }

        return $coverKey;
    }

    /**
     * Check if a cover key is valid
     *
     * @param string $coverKey
     * @return bool
     */
    public function isValidCoverKey(string $coverKey): bool
    {
        if (empty($coverKey)) {
            return true;
        }

        return $this->getCoverUrl($coverKey) !== null;
    }
}
