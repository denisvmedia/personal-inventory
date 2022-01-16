<?php

declare(strict_types=1);

namespace App\Controller;

use App\Inventory\Exporter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class ExportController
{
    #[Route('/export', name: 'export')]
    public function __invoke(Exporter $exporter): Response
    {
        $response = new Response();
        $response->headers->set('Content-Type', [
            'application/force-download',
            'application/octet-stream',
            'application/download',
            //'application/xml',
        ]);
        $response->headers->set('Content-Disposition', 'attachment; filename=export-'.time().'.xml');
        $response->setContent($exporter->getExport());

        return $response;
    }
}