<?php

declare(strict_types=1);

namespace App\Inventory;

use App\Entity\InventoryItem;
use App\Storage\DocumentStorage;
use App\Storage\File\BufferFile;
use App\Storage\ImageStorage;
use SimpleXMLElement;

final class Importer
{
    public function __construct(private readonly DocumentStorage $docs, private readonly ImageStorage $images)
    {
    }

    public function import(string $file)
    {
        $raw = file_get_contents($file);
        $parsed = new SimpleXMLElement($raw);
        foreach ($parsed as $element) {
            $item = $this->convertToItem($element);
            $this->docs->saveInventoryItem($item);
            $this->saveItemImages($item, $element->images);
        }
    }

    private function convertToItem(SimpleXMLElement $element): InventoryItem
    {
        $item = new InventoryItem((string) $element->attributes()['id']);
        $item->setName((string) $element->name);
        $item->setManufacturer((string) $element->manufacturer);
        $item->setModel((string) $element->model);
        $item->setSerialNumbers((string) $element->serialNumbers);
        $item->setUrl((string) $element->url);
        $item->setNotes((string) $element->notes);
        foreach ($element->locations as $location) {
            $value = (string) $location->location;
            if (!empty($value)) {
                $item->addLocation($value);
            }
        }
        foreach ($element->types as $type) {
            $value = (string) $type->type;
            if (!empty($value)) {
                $item->addType($value);
            }
        }
        if ('' !== (string) $element->purchasePrice) {
            $item->setPurchasePrice((string) $element->purchasePrice);
        }
        if ('' !== (string) $element->currentPriceValue) {
            $item->setValue((string) $element->currentPriceValue);
        }
        $item->setQuantity((int) $element->quantity);
        if ('' !== (string) $element->acquiredDate) {
            $item->setAcquiredDate((string) $element->acquiredDate);
        }

        $deleted = (string) $element->deleted;
        if ('1' === $deleted) {
            $item->setDeleted(true);
        }

        $archived = (string) $element->archived;
        $item->setArchived('1' === $archived);

        return $item;
    }

    private function saveItemImages(InventoryItem $item, SimpleXMLElement $images): void
    {
        foreach ($images as $image) {
            $value = (string) $image->image;
            if (!empty($value)) {
                $this->images->saveItemImages($item, [
                    new BufferFile((string)$image->image->attributes()['filename'], base64_decode($value))
                ]);
            }
        }
    }
}
