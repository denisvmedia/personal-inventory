<?php

declare(strict_types=1);

namespace App\Inventory\DTO;

use Symfony\Component\HttpFoundation\Request;

final class Query
{
    public readonly ?string $query;

    public function __construct(Request $request, public readonly ?string $category = null, public readonly ?string $tag = null)
    {
        $val = $request->query->get('q');
        if (null !== $val && !is_string($val)) {
            $this->query = (string) $val;
        } else {
            $this->query = $val;
        }
    }
}
