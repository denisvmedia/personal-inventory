<?php

declare(strict_types=1);

namespace App\Inventory\DTO;

final class InventoryListResponse
{
    public function __construct(public readonly iterable $items, public readonly string $breadcrumb = '')
    {
    }
}
