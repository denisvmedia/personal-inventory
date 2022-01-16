<?php

namespace App\Controller;

use App\Service\TagManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class TagController extends AbstractController
{
    /**
     * Render list of tags from a category
     * 
     * @param string $category One of Tag::CATEGORY_*
     */
    #[Route('/tags/{category}', name: 'tag_list')]
    public function __invoke(TagManager $tagManager, string $category): Response
    {
        $tags = $tagManager->getTags($category);
        $images = $tagManager->getImagesForTags($tags);

        return $this->render('tag/list.html.twig', [
            'tags' => $tags,
            'images' => $images,
        ]);
    }
}
