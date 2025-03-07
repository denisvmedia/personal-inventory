<?php

declare(strict_types=1);

namespace App\Inventory;

use App\Storage\DocumentStorage;
use App\Storage\ImageStorage;
use Twig\Environment;

final class Exporter
{
    public function __construct(private readonly DocumentStorage $docs, private ImageStorage $images, private readonly Environment $twig)
    {
    }

    public function getExport(): string
    {
        $items = $this->docs->getInventoryItems(withArchived: true);
        $export = $this->twig->render('export.xml.twig', [
            'items' => $items,
            'imagesSvc' => $this->images,
        ]);

        return $export;
    }

    public function export(string $file)
    {
        $data = $this->getExport();
        file_put_contents($file, $data);
    }
}