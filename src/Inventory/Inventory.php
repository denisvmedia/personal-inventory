<?php

declare(strict_types=1);

namespace App\Inventory;

use App\Entity\InventoryItem;
use App\Inventory\DTO\InventoryListResponse;
use App\Inventory\DTO\Query;
use App\Storage\DocumentStorage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class Inventory
{
    public function __construct(private DocumentStorage $docs)
    {
    }

    public function listItems(Query $query): InventoryListResponse
    {
        if (!$this->isNullOrEmpty($query->category) && !$this->isNullOrEmpty($query->tag)) {
            $items = $this->docs->getInventoryItemsByTag($query->category, $query->tag);
            $breadcrumb = $query->tag;

            return new InventoryListResponse($items, $breadcrumb);
        }

        if (null !== $query->query) {
            $items = $this->docs->searchInventoryItems($query->query);
            $breadcrumb = $query->query;

            return new InventoryListResponse($items, $breadcrumb);
        }

        $items = $this->docs->getInventoryItems();

        return new InventoryListResponse($items);
    }

    public function getItem(string $id): InventoryItem
    {
        $item = $this->docs->getInventoryItem($id);
        if (!$item) {
            throw new NotFoundHttpException('Item not found');
        }
        return $item;
    }

    public function getOrCreateItem(?string $id = null): InventoryItem
    {
        if (null !== $id) {
            return $this->getItem($id);
        }
        return new InventoryItem();
    }

    public function delete(?string $id = null): void
    {
        $item = $this->getOrCreateItem($id);
        $this->docs->deleteInventoryItem($item);
    }

    private function isNullOrEmpty(?string $val): bool
    {
        if (null === $val) {
            return true;
        }

        if ('' === $val) {
            return true;
        }

        return false;
    }
}