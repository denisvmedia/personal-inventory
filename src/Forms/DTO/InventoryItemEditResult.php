<?php

declare(strict_types=1);

namespace App\Forms\DTO;

use App\Entity\InventoryItem;
use Symfony\Component\Form\FormInterface;

final class InventoryItemEditResult
{
    public function __construct(public readonly InventoryItem $item, public readonly FormInterface $form, public readonly array $errors)
    {
    }
}