<?php

declare(strict_types=1);

namespace App\Entity;

use DateTime;
use MongoDB\BSON\ObjectId;
use RuntimeException;
use Symfony\Component\Validator\Constraints as Assert;

class InventoryItem extends Persistable
{
    #[Assert\Length(min: 3)]
    protected string $name = '';

    protected ?string $manufacturer = null;

    protected ?string $model = null;

    protected ?string $serialNumbers = null;

    #[Assert\Url]
    protected ?string $url = null;

    protected ?string $notes = null;

    /**
     * @var string[]
     */
    #[Assert\All([
        new Assert\NotNull,
        new Assert\Length(min: 3),
        new Assert\Type('string'),
        new Assert\Regex(
            pattern: '/^[A-Z\-a-z0-9]+$/',
            message: 'Value must consist of letters, digits or dashes.',
        ),
    ])]
    protected array $locations = [];

    /**
     * @var string[]
     */
    #[Assert\All([
        new Assert\NotNull,
        new Assert\Length(min: 3),
        new Assert\Type('string'),
        new Assert\Regex(
            pattern: '/^[A-Z\-a-z0-9]+$/',
            message: 'Value must consist of letters, digits or dashes.',
        ),
    ])]
    protected array $types = [];

    #[Assert\Type(type: 'numeric')]
    protected ?string $purchasePrice = null;

    #[Assert\Type(type: 'numeric')]
    protected ?string $value = null;

    #[Assert\GreaterThanOrEqual(1)]
    protected int $quantity = 1;

    protected int|string|null $acquiredDate = null;

    protected bool $deleted = false;

    public function __construct(?string $id = null)
    {
        parent::__construct();

        if ('' !== $id && null !== $id) {
            $this->id = new ObjectId($id);
        }
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getName() : ?string
    {
        return $this->name;
    }

    public function setManufacturer(string $manufacturer) 
    {
        $this->manufacturer = $manufacturer;
    }

    public function getManufacturer() : ?string
    {
        return $this->manufacturer;
    }

    public function setModel(?string $model): void
    {
        $this->model = $model;
    }

    public function getModel() : ?string
    {
        return $this->model;
    }

    public function setSerialNumbers(?string $serialNumbers): void
    {
        $this->serialNumbers = $serialNumbers;
    }

    public function getSerialNumbers() : ?string
    {
        return $this->serialNumbers;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
    }

    public function getNotes() : ?string
    {
        return $this->notes;
    }

    /**
     * @param string $location
     */
    public function addLocation(string $location): void
    {
        $this->locations[] = $location;
    }

    /**
     * @param string[] $locations
     */
    public function setLocations(array $locations): void
    {
        foreach ($locations as $location) {
            if (!is_string($location)) {
                throw new RuntimeException('All item locations must be strings');
            }
        }
        $this->locations = $locations;
    }

    /**
     * @return string[]
     */
    public function getLocations() : array
    {
        return $this->locations;
    }

    /**
     * Add one type to the set of types
     * 
     * @param string $type
     */
    public function addType(string $type) : void
    {
        $this->types[] = $type;
    }

    /**
     * @param string[] $types
     */
    public function setTypes(array $types) : void
    {
        foreach ($types as &$type) {
            if (is_object($type)) {
                $type = (string) $type;
            }
        }
        $this->types = $types;
    }

    /**
     * @return string[]
     */
    public function getTypes() : array
    {
        return $this->types;
    }

    public function setPurchasePrice(string $price): void
    {
        if (!is_numeric($price)) {
            throw new RuntimeException('Item price must be numeric');
        }
        $this->purchasePrice = $price;
    }

    public function getPurchasePrice() : ?string
    {
        return $this->purchasePrice;
    }

    /**
     * Get total purchase price (individual price * quantity)
     *
     * @return string|null
     */
    public function getTotalPurchasePrice() : ?string
    {
        $price = null;
        if ($this->purchasePrice && $this->quantity) {
            $price = bcmul($this->purchasePrice, (string) $this->quantity);
        }

        return $price;
    }

    public function setValue(string $value)
    {
        if (!is_numeric($value)) {
            throw new RuntimeException('Item value must be numeric');
        }
        $this->value = $value;
    }

    public function getValue() : ?string
    {
        return $this->value;
    }

    /**
     * Get total value (individual value * quantity)
     * 
     * @return string|null
     */
    public function getTotalValue() : ?string
    {
        $value = null;
        if ($this->value && $this->quantity) {
            $value = bcmul($this->value, (string) $this->quantity);
        }

        return $value;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setAcquiredDate(DateTime|string $acquiredDate = null): void
    {
        if (is_string($acquiredDate)) {
            $acquiredDate = new DateTime('@'.$acquiredDate);
        }

        if (null !== $acquiredDate) {
            $this->acquiredDate = (int) $acquiredDate->format('U');
        } else {
            $this->acquiredDate = null;
        }
    }

    public function getAcquiredDate(): ?DateTime
    {
        if ($this->acquiredDate) {
            return new DateTime('@' . $this->acquiredDate);
        }
        return null;
    }

    public function setDeleted(bool $deleted): void
    {
        $this->deleted = $deleted;
    }

    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }
}
