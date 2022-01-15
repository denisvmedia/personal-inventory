<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\DocumentStorage;
use App\Service\ImageStorage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

class ExportController
{
    public function __construct(protected DocumentStorage $docs, protected ImageStorage $images, protected Environment $twig)
    {
    }

    #[Route('/export', name: 'export')]
    public function exportAction(): Response
    {
        $items = $this->docs->getInventoryItems();
        $response = new Response();
        $response->headers->set('Content-Type', [
            'application/force-download',
            'application/octet-stream',
            'application/download',
            //'application/xml',
        ]);
        $response->headers->set('Content-Disposition', 'attachment; filename=export-'.time().'.xml');
        $response->setContent($this->twig->render('export.xml.twig', [
            'items' => $items,
            'imagesSvc' => $this->images,
        ]));

        return $response;
    }
}