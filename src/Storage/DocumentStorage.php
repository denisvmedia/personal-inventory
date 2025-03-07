<?php

declare(strict_types=1);

namespace App\Storage;

use App\Entity\InventoryItem;
use App\Entity\Tag;
use MongoDB\BSON\ObjectId;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Driver\Cursor;
use RuntimeException;

final class DocumentStorage
{
    private bool $inited = false;

    public function __construct(private Client $mongo, private ImageStorage $imageStorage)
    {
    }

    private function getClient(): Client
    {
        if ($this->inited) {
            return $this->mongo;
        }

        // Create full text index if it doesn't already exist
        $inventory = $this->mongo->inventory->inventory;
        $exists = false;
        foreach ($inventory->listIndexes() as $index) {
            if ($index->isText()) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            // Note this is a blocking process, so running this on a collection with data may hinder performance
            $inventory->createIndex(['$**' => 'text']);
        }

        $this->inited = true;

        return $this->mongo;
    }

    private function getInventoryCollection(): Collection
    {
        return $this->getClient()->inventory->inventory;
    }

    private function getTagCollection(): Collection
    {
        return $this->getClient()->inventory->tags;
    }

    /**
     * @return Cursor<InventoryItem>
     */
    public function getInventoryItems(bool $withArchived = false): iterable
    {
        $query = ['deleted' => false];
        if (!$withArchived) {
            $query = [
                '$or' => [
                    $query + ['archived' => false],
                    $query + ['archived' => null],
                ],
            ];
        }

        return $this->getInventoryCollection()->find(
            $query,
            ['sort' => ['name' => 1]]
        );
    }

    /**
     * @return Cursor<InventoryItem>
     */
    public function getInventoryArchivedItems(): iterable
    {
        return $this->getInventoryCollection()->find(
            [
                'deleted' => false,
                'archived' => true,
            ],
            ['sort' => ['name' => 1]]
        );
    }

    public function searchInventoryItems(string $q, bool $archived): iterable
    {
        if ($archived) {
            $query = [
                '$text' => ['$search' => $q],
                'deleted' => false,
                'archived' => true,
            ];
        } else {
            $query = [
                '$text' => ['$search' => $q],
                'deleted' => false,
            ];
            $query = [
                '$or' => [
                    $query + ['archived' => false],
                    $query + ['archived' => null],
                ],
            ];
        }

        return $this->getInventoryCollection()->find($query);
    }

    public function getInventoryItem(string $id): ?InventoryItem
    {
        $item = $this->getInventoryCollection()->findOne([
            '_id' => new ObjectId($id),
            'deleted' => false,
        ]);

        if (null === $item) {
            return null;
        }

        if (!$item instanceof InventoryItem) {
            // TODO: enhance the exception
            throw new RuntimeException('Unexpected $item type');
        }

        return $item;
    }

    public function getInventoryItemsByTag(string $category, string $tag, bool $archived): iterable
    {
        $query = [
            $category => [
                '$regex' => '^' . $tag . '$',
                '$options' => 'i'
            ],
            'deleted' => false,
        ];

        if ($archived) {
            $query['archived'] = true;
        } else {
            $query = [
                '$or' => [
                    $query + ['archived' => false],
                    $query + ['archived' => null],
                ],
            ];
        }

        return $this->getInventoryCollection()->find(
            $query,
            ['sort' => ['name' => 1]]
        );
    }

    public function getRandomInventoryItemByTag(string $category, string $tag): ?InventoryItem
    {
        $item = $this->getInventoryCollection()->findOne([
            $category => [
                '$regex' => '^' . $tag . '$',
                '$options' => 'i'
            ],
            'deleted' => false
        ]);

        if (null === $item) {
            return null;
        }

        if (!$item instanceof InventoryItem) {
            // TODO: enhance the exception
            throw new RuntimeException('Unexpected $item type');
        }

        return $item;
    }

    /**
     * @return string The ID of the item
     */
    public function saveInventoryItem(InventoryItem $item): string
    {
        $inventory = $this->getInventoryCollection();
        // Get the original tags so we can update their counters
        $originalItem = $this->getInventoryItem($item->getId());
        $originalTypes = [];
        $originalLocations = [];
        if ($originalItem) {
            $originalTypes = $originalItem->getTypes();
            $originalLocations = $originalItem->getLocations();
        }
        $item->setModifiedTime();
        $inventory->replaceOne(
            ['_id' => $item->getObjectId()],
            $item,
            ['upsert' => true]
        );
        $this->saveInventoryItemTags(Tag::CATEGORY_ITEM_TYPE, $originalTypes, $item->getTypes());
        $this->saveInventoryItemTags(Tag::CATEGORY_ITEM_LOCATION, $originalLocations, $item->getLocations());
    
        return $item->getId();
    }

    /**
     * Save tag entities associated with an inventory item being updated
     * 
     * @param string $category One of Tag::CATEGORY_*
     * @param string[] $originalTagStrings Tag strings associated with the item before update
     * @param string[] $updatedTagStrings Tag strings associated with the updated item
     */
    private function saveInventoryItemTags(string $category, array $originalTagStrings, array $updatedTagStrings): void
    {
        // TODO: note, this function can cause a race condition.
        $tags = [];
        $collection = $this->getTagCollection();
        foreach (array_diff($originalTagStrings, $updatedTagStrings) as $removed) {
            if ($tag = $this->getTagByName($category, $removed)) {
                $tag->decrementCount();
                if ($tag->getCount() === 0) {
                    $collection->deleteOne(
                        ['_id' => $tag->getObjectId()],
                    );
                } else {
                    $tags[] = $tag;
                }
            }
        }
        foreach (array_diff($updatedTagStrings, $originalTagStrings) as $added) {
            $tag = $this->getTagByName($category, $added);
            if (!$tag) {
                $tag = new Tag();
                $tag->setName($added);
                $tag->setCategory($category);
            }
            $tag->incrementCount();
            $tags[] = $tag;
        }
        foreach ($tags as $tag) {
            $tag->setModifiedTime();
            $collection->replaceOne(
                ['_id' => $tag->getObjectId()],
                $tag,
                ['upsert' => true]
            );
        }
    }

    public function deleteInventoryItem(InventoryItem $item): void
    {
        $item->setDeleted(true);
        $inventory = $this->getInventoryCollection();
        $inventory->replaceOne(
            ['_id' => $item->getObjectId()],
            $item,
            ['upsert' => true]
        );
        $this->saveInventoryItemTags(Tag::CATEGORY_ITEM_TYPE, $item->getTypes(), []);
        $this->saveInventoryItemTags(Tag::CATEGORY_ITEM_LOCATION, $item->getLocations(), []);
        $this->imageStorage->deleteItemImages($item);
    }

    public function archiveInventoryItem(InventoryItem $item): void
    {
        $item->setArchived(true);
        $inventory = $this->getInventoryCollection();
        $inventory->replaceOne(
            ['_id' => $item->getObjectId()],
            $item,
            ['upsert' => true]
        );
        $this->saveInventoryItemTags(Tag::CATEGORY_ITEM_TYPE, $item->getTypes(), []);
        $this->saveInventoryItemTags(Tag::CATEGORY_ITEM_LOCATION, $item->getLocations(), []);
        $this->imageStorage->deleteItemImages($item);
    }

    public function unarchiveInventoryItem(InventoryItem $item): void
    {
        $item->setArchived(false);
        $inventory = $this->getInventoryCollection();
        $inventory->replaceOne(
            ['_id' => $item->getObjectId()],
            $item,
            ['upsert' => true]
        );
        $this->saveInventoryItemTags(Tag::CATEGORY_ITEM_TYPE, $item->getTypes(), []);
        $this->saveInventoryItemTags(Tag::CATEGORY_ITEM_LOCATION, $item->getLocations(), []);
        $this->imageStorage->deleteItemImages($item);
    }

    /**
     * Get tags, optionally by category
     *
     * @param string|null $category One of Tag::CATEGORY_*
     * @param string[] $orderBy Field to order by
     */
    public function getTags(string $category = null, array $orderBy = []): iterable
    {
        $collection = $this->getTagCollection();
        $filter = [];
        $options = [];
        if ($category) {
            $filter = ['category' => $category];
        }
        foreach ($orderBy as $field) {
            $direction = 1;
            if ($field === 'count') {
                $direction = -1;
            }
            $options['sort'] = [$field => $direction];
        }
        return $collection->find($filter, $options);
    }

    /**
     * Get "top" 5 tags by category
     *
     * @param string $category One of Tag::CATEGORY_*
     */
    public function getTopTags(string $category): iterable
    {
        $collection = $this->getTagCollection();
        return $collection->find(
            ['category' => $category],
            ['limit' => 5, 'sort' => ['count' => -1, 'name' => 1]]
        );
    }

    /**
     * Get "top" 5 type tags
     */
    public function getTopTypeTags(): iterable
    {
        return $this->getTopTags(Tag::CATEGORY_ITEM_TYPE);
    }

    /**
     * Get "top" 5 location tags
     */
    public function getTopLocationTags(): iterable
    {
        return $this->getTopTags(Tag::CATEGORY_ITEM_LOCATION);
    }

    /**
     * Get a Tag entity by name
     * 
     * @param string $category One of Tag::CATEGORY_*
     * @param string $name
     * @return Tag|null
     */
    public function getTagByName(string $category, string $name): ?Tag
    {
        $item = $this->getTagCollection()->findOne(
            [
                'category' => $category, 
                // Case insensitive indexed search
                'name' => [
                    '$regex' => '^' . $name . '$',
                    '$options' => 'i'
                ]
            ]
        );

        if (null === $item) {
            return null;
        }

        if (!$item instanceof Tag) {
            // TODO: enhance the exception
            throw new RuntimeException('Unexpected $item type');
        }

        return $item;
    }
}
