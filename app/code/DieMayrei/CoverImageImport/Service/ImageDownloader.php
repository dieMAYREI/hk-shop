<?php
declare(strict_types=1);

namespace DieMayrei\CoverImageImport\Service;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Io\File as IoFile;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class ImageDownloader
{
    private const TARGET_DIR = 'catalog/category/import';
    private const API_QUALITY = 'w=1024';
    private const RESIZE_WIDTH = 600;

    private Filesystem $filesystem;
    private StoreManagerInterface $storeManager;
    private LoggerInterface $logger;

    public function __construct(
        Filesystem $filesystem,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->filesystem = $filesystem;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    /**
     * Download image and resize it
     *
     * @param string $url
     * @param array|null $existingRecord
     * @return array|null Returns null if no update needed, otherwise ['path' => ..., 'category_url' => ...]
     */
    public function downloadAndResize(string $url, ?array $existingRecord): ?array
    {
        $imageContent = @file_get_contents($url . '?' . self::API_QUALITY);

        if ($imageContent === false) {
            throw new \RuntimeException('Failed to download image from: ' . $url);
        }

        // Check if update is needed
        if ($existingRecord && file_exists($existingRecord['imported'])) {
            if (strlen($imageContent) === filesize($existingRecord['imported'])) {
                return null; // No update needed
            }
            // Delete old file
            @unlink($existingRecord['imported']);
        }

        $mediaPath = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath();
        $targetPath = $mediaPath . self::TARGET_DIR;

        // Ensure directory exists
        $this->ensureDirectoryExists($targetPath);

        // Generate unique filename
        $ext = pathinfo($url, PATHINFO_EXTENSION) ?: 'jpg';
        $baseName = basename($url, '.' . $ext);
        $fileName = $baseName . '-' . time() . '.' . $ext;
        $fullPath = $targetPath . '/' . $fileName;

        // Save original image
        $ioFile = new IoFile();
        $ioFile->open(['path' => $targetPath]);
        $ioFile->write($fileName, $imageContent);

        $this->logger->info('CoverImageImport: Downloaded image: ' . $fileName);

        // Resize image
        $resizedPath = $this->resize($fullPath, self::RESIZE_WIDTH);

        return [
            'path' => $resizedPath,
            'category_url' => $this->getCategoryUrl($fileName)
        ];
    }

    private function resize(string $imagePath, int $width): string
    {
        $mediaPath = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath();
        $resizedDir = $mediaPath . self::TARGET_DIR . '/' . $width;

        $this->ensureDirectoryExists($resizedDir);

        $fileName = basename($imagePath);
        $resizedPath = $resizedDir . '/' . $fileName;

        $image = new \Imagick($imagePath);
        $image->setImageUnits(\Imagick::RESOLUTION_PIXELSPERINCH);
        $image->setImageResolution(72, 72);
        $image->resizeImage($width, $width, \Imagick::FILTER_LANCZOS, 0.9, true);
        $image->setCompressionQuality(70);
        $image->writeImage($resizedPath);

        return $resizedPath;
    }

    private function getCategoryUrl(string $fileName): string
    {
        $baseUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
        return $baseUrl . self::TARGET_DIR . '/' . self::RESIZE_WIDTH . '/' . $fileName;
    }

    private function ensureDirectoryExists(string $path): void
    {
        if (!is_dir($path)) {
            $ioFile = new IoFile();
            $ioFile->mkdir($path, 0775);
        }
    }

    public function getTargetDir(): string
    {
        return self::TARGET_DIR;
    }

    public function getResizeWidth(): int
    {
        return self::RESIZE_WIDTH;
    }
}
