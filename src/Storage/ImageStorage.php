<?php

declare(strict_types=1);

namespace App\Storage;

use App\Entity\InventoryItem;
use App\Storage\File\FileInterface;
use DirectoryIterator;
use Gumlet\ImageResize;
use Symfony\Component\Filesystem\Filesystem;

final class ImageStorage
{
    private const WIDTH_SMALL = 200;
    private const HEIGHT_SMALL = 200;

    /**
     * Constructor
     * 
     * @param string $basePath See services.yaml
     */
    public function __construct(private string $basePath)
    {
    }

    public function getItemImagePath(InventoryItem $item): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . $item->getId();
    }

    /**
     * Save images and their resized versions during upload.
     * 
     * @param InventoryItem $item
     * @param FileInterface[] $files
     */
    public function saveItemImages(InventoryItem $item, array $files)
    {
        $fs = new Filesystem();
        $itemPath = $this->getItemImagePath($item);
        if (!$fs->exists($itemPath)) {
            $fs->mkdir($itemPath);
        }
        foreach ($files as $file) {
            $file->upload($itemPath);
            $this->resizeToWidth($item, $file->getFilename(), self::WIDTH_SMALL);
            $this->resizeToWidthAndHeight($item, $file->getFilename(), self::WIDTH_SMALL, self::HEIGHT_SMALL);
        }
    }

    /**
     * Get image file names associated with an item. This returns only the unscaled files.
     * 
     * @param InventoryItem $item
     * @return string[] Array of image file names (excluding path)
     */
    public function getItemImages(InventoryItem $item) : array
    {
        $images = [];
        $path = $this->getItemImagePath($item);
        if (file_exists($path)) {
            $iter = new DirectoryIterator($path);
            foreach ($iter as $file) {
                if (!$file->isDot()) {
                    $name = $file->getFilename();
                    $nameParts = explode('.', $name);
                    if (!str_contains($nameParts[0], 'w')) {
                        $images[] = $name;
                    }
                }
            }
        }
        
        return $images;
    }

    /**
     * Get the full path to an item image file. Generate scaled image as needed.
     * 
     * @param InventoryItem $item
     * @param string $filename The file name of the unscaled image
     * @param int|null $width
     * @param int|null $height
     * @return string
     */
    public function getFilePath(InventoryItem $item, string $filename, int $width = null, int $height = null): string
    {
        $unscaledFilename = $filename;
        if ($width && $height) {
            $filename = $this->getFilenameWidthHeight($unscaledFilename, $width, $height);
        } elseif ($width) {
            $filename = $this->getFilenameWidth($filename, $width);
        }
        $path = $this->getItemImagePath($item) . DIRECTORY_SEPARATOR . $filename;
        if (!file_exists($path)) {
            if ($width && $height) {
                $this->resizeToWidthAndHeight($item, $unscaledFilename, $width, $height);
            } elseif ($width) {
                $this->resizeToWidth($item, $unscaledFilename, $width);
            } else {
                return '';
            }
        }
        return $path;
    }

    /**
     * Remove an item's image from storage
     * 
     * @param InventoryItem $item
     * @param string $filename
     */
    public function deleteItemImage(InventoryItem $item, string $filename): void
    {
        $path = $this->getItemImagePath($item);
        $files = [$filename];
        // Also delete any scaled images
        $files[] = $this->getFilenameWidth($filename, self::WIDTH_SMALL);

        foreach ($files as $filename) {
            if (file_exists($path . DIRECTORY_SEPARATOR . $filename)) {
                unlink($path . DIRECTORY_SEPARATOR . $filename);
            }
        }
    }

    public function deleteItemImages(InventoryItem $item): void
    {
        $path = $this->getItemImagePath($item);
        $fs = new Filesystem();
        $fs->remove($path);
    }

    public function getImageBase64(InventoryItem $item, string $filename, int $width = null, int $height = null): string
    {
        $path = $this->getFilePath($item, $filename, $width, $height);
        if (empty($path)) {
            return '';
        }
        $data = file_get_contents($path);
        if (empty($data)) {
            return '';
        }
        return base64_encode($data);
    }

    /**
     * Resize an unscaled image to a width.
     * 
     * @param InventoryItem $item
     * @param string $filename
     * @param int $width
     */
    private function resizeToWidth(InventoryItem $item, string $filename, int $width): void
    {
        $itemPath = $this->getItemImagePath($item);
        $resizer = new ImageResize($itemPath . DIRECTORY_SEPARATOR . $filename);
        $resizer->resizeToWidth($width, true);
        $resizer->save(
            $itemPath . DIRECTORY_SEPARATOR . $this->getFilenameWidth($filename, $width)
        );
    }

    /**
     * Resize an unscaled image to a width and height. Image will be cropped to fit in the box.
     * 
     * @param InventoryItem $item
     * @param string $filename
     * @param int $width
     * @param int $height
     */
    private function resizeToWidthAndHeight(InventoryItem $item, string $filename, int $width, int $height): void
    {
        $itemPath = $this->getItemImagePath($item);
        $resizer = new ImageResize($itemPath . DIRECTORY_SEPARATOR . $filename);
        $resizer->crop($width, $height);
        $resizer->save(
            $itemPath . DIRECTORY_SEPARATOR . $this->getFilenameWidthHeight($filename, $width, $height)
        );
    }

    /**
     * Get a filename for a width based on the original filename
     */
    private function getFilenameWidth(string $filename, int $width): string
    {
        $fileparts = explode('.', $filename);
        return $fileparts[0] . 'w' . $width . '.' . $fileparts[1];
    }

    /**
     * Get a filename for a width and height based on the original filename
     */
    private function getFilenameWidthHeight(string $filename, int $width, int $height): string
    {
        $fileparts = explode('.', $filename);
        return $fileparts[0] . 'w' . $width . 'h' . $height . '.' . $fileparts[1];
    }
}
