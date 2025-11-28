<?php
declare(strict_types=1);

namespace DieMayrei\CoverImageImport\Model;

/**
 * Central configuration for all available magazines.
 * Add or remove magazines here to automatically update:
 * - Admin dropdown options
 * - System configuration fields
 * - Cover import functionality
 */
class MagazineConfig
{
    /**
     * Magazine definitions with their configuration
     * Format: 'key' => ['label' => 'Display Name', 'has_digital' => true/false]
     */
    private const MAGAZINES = [
        'kaninchenzeitung' => [
            'label' => 'Kaninchenzeitung',
            'has_digital' => true
        ],
        'gefluegelzeitung' => [
            'label' => 'Geflügelzeitung',
            'has_digital' => true
        ],
    ];

    private const CONFIG_PATH_PREFIX = 'diemayrei/cover_import/';

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

        foreach (self::MAGAZINES as $key => $config) {
            $configPath = self::CONFIG_PATH_PREFIX . $key . '_cover';
            $options[] = [
                'value' => $configPath,
                'label' => __($config['label'])
            ];

            if ($config['has_digital']) {
                $digitalConfigPath = self::CONFIG_PATH_PREFIX . $key . '_digital_cover';
                $options[] = [
                    'value' => $digitalConfigPath,
                    'label' => __($config['label'] . ' Digital')
                ];
            }
        }

        return $options;
    }

    /**
     * Get config path to label mapping for the helper
     *
     * @return array
     */
    public function getConfigPathMapping(): array
    {
        $mapping = ['' => 'kein'];

        foreach (self::MAGAZINES as $key => $config) {
            $configPath = self::CONFIG_PATH_PREFIX . $key . '_cover';
            $mapping[$configPath] = $config['label'];

            if ($config['has_digital']) {
                $digitalConfigPath = self::CONFIG_PATH_PREFIX . $key . '_digital_cover';
                $mapping[$digitalConfigPath] = $config['label'] . ' Digital';
            }
        }

        return $mapping;
    }

    /**
     * Get label to config path mapping (inverse)
     *
     * @return array
     */
    public function getLabelToConfigMapping(): array
    {
        return array_flip($this->getConfigPathMapping());
    }

    /**
     * Get all magazine definitions for system.xml generation
     *
     * @return array
     */
    public function getMagazineDefinitions(): array
    {
        return self::MAGAZINES;
    }

    /**
     * Get config path prefix
     *
     * @return string
     */
    public function getConfigPathPrefix(): string
    {
        return self::CONFIG_PATH_PREFIX;
    }

    /**
     * Check if a config path is valid
     *
     * @param string $configPath
     * @return bool
     */
    public function isValidConfigPath(string $configPath): bool
    {
        if (empty($configPath)) {
            return true; // 'kein' is valid
        }

        return array_key_exists($configPath, $this->getConfigPathMapping());
    }
}
