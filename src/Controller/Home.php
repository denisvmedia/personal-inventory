<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class Home extends AbstractController
{
    public function index(): Response
    {
        return $this->redirectToRoute('inventory_list');
    }
}
