<?php

namespace App\Service;

use MongoDB\Client as MongoDB;
use MongoDB\BSON\ObjectId;

use App\Entity\InventoryItem;
use App\Entity\Tag;

class DocumentStorage
{
    /** MongoDB\Client */
    protected $mongo;

    /**
     * Set up
     */
    protected function init()
    {
        if (!$this->mongo) {
            $this->mongo = new MongoDB(getenv('DATABASE_URL'));
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
        }
    }

    /**
     * Get a reference to our inventory collection
     * 
     * @return MongoDB\Collection
     */
    protected function getInventoryCollection() : \MongoDB\Collection
    {
        $this->init();
        return $this->mongo->inventory->inventory;
    }

    /**
     * Get a reference to our tag collection
     * 
     * @return MongoDB\Collection
     */
    protected function getTagCollection() : \MongoDB\Collection
    {
        $this->init();
        return $this->mongo->inventory->tags;
    }

    /**
     * Get inventory items
     * 
     * @return MongoDB\Driver\Cursor
     */
    public function getInventoryItems() : iterable
    {
        return $this->getInventoryCollection()->find(
            ['deleted' => false], 
            ['sort' => ['name' => 1]]
        );
    }

    /**
     * Get inventory items containing a string
     * 
     * @return MongoDB\Driver\Cursor
     */
    public function searchInventoryItems(string $query) : iterable
    {
        return $this->getInventoryCollection()->find([
            '$text' => ['$search' => $query],
            'deleted' => false
        ]);
    }

    /**
     * Get an inventory item
     * 
     * @return App\Entity\InventoryItem
     */
    public function getInventoryItem(string $id) : ?InventoryItem
    {
        $inventory = $this->getInventoryCollection();
        return $inventory->findOne(['_id' => new ObjectId("$id"), 'deleted' => false]);
    }

    /**
     * Get inventory items by tag name
     * 
     * @param string $category One of Tag::CATEGORY_*
     * @param string $tag Tag name
     * @return MongoDB\Driver\Cursor
     */
    public function getInventoryItemsByTag(string $category, string $tag) : iterable
    {
        return $this->getInventoryCollection()->find(
            [
                $category => [
                    '$regex' => '^' . $tag . '$',
                    '$options' => 'i'
                ],
                'deleted' => false
            ], 
            ['sort' => ['name' => 1]]
        );
    }

    /**
     * Get one random inventory item by tag name
     * 
     * @param string $category One of Tag::CATEGORY_*
     * @param string $tag Tag name
     * @return MongoDB\Driver\Cursor
     */
    public function getRandomInventoryItemByTag(string $category, string $tag) : ?InventoryItem
    {
        return $this->getInventoryCollection()->findOne([
            $category => [
                '$regex' => '^' . $tag . '$',
                '$options' => 'i'
            ],
            'deleted' => false
        ]);
    }

    /**
     * Persist an inventory item
     * 
     * @return string The ID of the item
     */
    public function saveInventoryItem(InventoryItem $item) : string
    {
        if (!$item) {
            throw new \RuntimeException('Empty item can not be saved');
        }
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
    
        return (string) $item->getId();
    }

    /**
     * Save tag entities associated with an inventory item being updated
     * 
     * @param string $category One of Tag::CATEGORY_*
     * @param string[] $originalTagStrings Tag strings associated with the item before update
     * @param string[] $updatedTagStrings Tag strings associated with the updated item
     */
    protected function saveInventoryItemTags(string $category, array $originalTagStrings, array $updatedTagStrings)
    {
        $tags = [];
        foreach (array_diff($originalTagStrings, $updatedTagStrings) as $removed) {
            if ($tag = $this->getTagByName($category, $removed)) {
                $tag->decrementCount();
                $tags[] = $tag;
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
        $collection = $this->getTagCollection();
        foreach ($tags as $tag) {
            $tag->setModifiedTime();
            $collection->replaceOne(
                ['_id' => $tag->getObjectId()],
                $tag,
                ['upsert' => true]
            );
        }
    }

    /**
     * Soft delete an inventory item
     */
    public function deleteInventoryItem(InventoryItem $item)
    {
        if (!$item) {
            throw new \RuntimeException('Empty item can not be deleted');
        }
        $item->setDeleted(true);
        $inventory = $this->getInventoryCollection();
        $inventory->replaceOne(
            ['_id' => $item->getObjectId()],
            $item,
            ['upsert' => true]
        );
        $this->saveInventoryItemTags(Tag::CATEGORY_ITEM_TYPE, $item->getTypes(), []);
        $this->saveInventoryItemTags(Tag::CATEGORY_ITEM_LOCATION, $item->getLocations(), []);
    }

    /**
     * Get tags, optionally by category
     * 
     * @param string $category One of Tag::CATEGORY_*
     * @param string[] $orderBy Field to order by
     * @return MongoDB\Driver\Cursor
     */
    public function getTags(string $category = null, array $orderBy = []) : iterable
    {
        $this->init();
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
     * @return MongoDB\Driver\Cursor
     */
    public function getTopTags(string $category) : iterable
    {
        $this->init();
        $collection = $this->getTagCollection();
        return $collection->find(
            ['category' => $category],
            ['limit' => 5, 'sort' => ['count' => -1, 'name' => 1]]
        );
    }

    /**
     * Get "top" 5 type tags
     * 
     * @return MongoDB\Driver\Cursor
     */
    public function getTopTypeTags() : iterable
    {
        return $this->getTopTags(Tag::CATEGORY_ITEM_TYPE);
    }

    /**
     * Get "top" 5 location tags
     * 
     * @return MongoDB\Driver\Cursor
     */
    public function getTopLocationTags() : iterable
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
    public function getTagByName(string $category, string $name) : ?Tag
    {
        $this->init();
        $collection = $this->getTagCollection();
        return $collection->findOne(
            [
                'category' => $category, 
                // Case insensitive indexed search
                'name' => [
                    '$regex' => '^' . $name . '$',
                    '$options' => 'i'
                ]
            ]
        );
    }
}
