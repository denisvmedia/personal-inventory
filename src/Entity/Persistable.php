<?php

declare(strict_types=1);

namespace App\Entity;

use DateTime;
use MongoDB\BSON\ObjectId;
use ReflectionObject;

abstract class Persistable implements \MongoDB\BSON\Persistable
{
    protected ObjectId $id;
    protected int $modifiedTime;

    public function __construct()
    {
        $this->id = new ObjectId();
    }

    /**
     * Implementation of \MongoDB\BSON\Persistable::bsonSerialize
     */
    public function bsonSerialize(): array|object
    {
        $data = ['_id' => $this->id];

        $reflection = new ReflectionObject($this);
        foreach ($reflection->getProperties() as $property) {
            $name = $property->getName();
            if ($name !== 'id') {
                $data[$name] = $this->$name;
            }
        }

        return $data;
    }

    /**
     * Implementation of MongoDB\BSON\Persistable::bsonUnserialize
     */
    public function bsonUnserialize(array $data): void
    {
        foreach ($data as $key => $value) {
            if ($key === '_id') {
                $this->id = new ObjectId((string)$value);
            } elseif (is_object($value) && is_a($value, 'ArrayObject')) {
                $this->$key = $value->getArrayCopy();
            } else {
                $this->$key = $value;
            }
        }
    }

    /**
     * Get item's Mongo Object ID
     * 
     * @return ObjectId
     */
    public function getObjectId() : ObjectId
    {
        return $this->id;
    }

    /**
     * Get item ID
     * 
     * @return string
     */
    public function getId() : string
    {
        return (string) $this->id;
    }

    /**
     * Set modified time to now
     */
    public function setModifiedTime(): void
    {
        $this->modifiedTime = time();
    }

    public function getModifiedTime(): DateTime
    {
        return new DateTime('@' . $this->modifiedTime);
    }
}
