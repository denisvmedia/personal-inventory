<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\InventoryItem;
use App\Service\DocumentStorage;
use App\Service\ImageStorage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

class ExportController
{
    public function __construct(protected DocumentStorage $docs, protected ImageStorage $images)
    {
    }

    #[Route('/export', name: 'export')]
    public function exportAction(): Response
    {
        $items = $this->docs->getInventoryItems();
        $response = new StreamedResponse();
        $response->headers->set('Content-Type', [
            'application/force-download',
            'application/octet-stream',
            'application/download',
            //'application/xml',
        ]);
        $response->headers->set('Content-Disposition', 'attachment; filename=export-'.time().'.xml');
        $response->setCallback(function() use ($items) {
            echo '<?xml version="1.0" encoding="UTF-8"?>';
            echo '<items>';
            /** @var InventoryItem $item */
            foreach ($items as $item) {
                echo '<item>';
                echo '<id>'.$item->getId().'</id>';
                echo '<name>'.$item->getName().'</name>';
                echo '<manufacturer>'.$item->getManufacturer().'</manufacturer>';
                echo '<model>'.$item->getModel().'</model>';
                echo '<serialnumbers>'.$item->getSerialNumbers().'</serialnumbers>';
                echo '<notes>'.$item->getSerialNumbers().'</notes>';
                $locations = $item->getLocations();
                if (empty($locations)) {
                    echo '<locations/>';
                } else {
                    echo '<locations>';
                    foreach ($locations as $location) {
                        echo '<location>'.$location.'</location>';
                    }
                    echo '</locations>';
                }
                $types = $item->getTypes();
                if (empty($types)) {
                    echo '<types/>';
                } else {
                    echo '<types>';
                    foreach ($types as $type) {
                        echo '<type>'.$type.'</type>';
                    }
                    echo '</types>';
                }
                echo '<purchasePrice>'.$item->getPurchasePrice().'</purchasePrice>';
                echo '<currentPriceValue>'.$item->getValue().'</currentPriceValue>';
                echo '<quantity>'.$item->getQuantity().'</quantity>';
                $date = $item->getAcquiredDate();
                if ($date !== null) {
                    echo '<acquiredDate>'.$date->format('Y-m-d').'</acquiredDate>';
                } else {
                    echo '<acquiredDate/>';
                }
                echo '<deleted>'.($item->isDeleted() ? '1' : '0').'</deleted>';
                $images = $this->images->getItemImages($item);
                if (empty($images)) {
                    echo '<images/>';
                } else {
                    echo '<images>';
                    foreach ($images as $image) {
                        $imageFilePath = $this->images->getFilePath($item, $image);
                        if (!empty($imageFilePath)) {
                            $data = file_get_contents($imageFilePath);
                            if ($data !== false) {
                                echo '<image>'.base64_encode($data).'</image>';
                            }
                        }
                    }
                    echo '</images>';
                }
                echo '</item>';
            }
            echo '</items>';
        });

        return $response;
    }
}