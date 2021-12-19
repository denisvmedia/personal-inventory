<?php

namespace App\Controller;

use App\Service\TagManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TagController extends AbstractController
{
    public function __construct(private TagManager $tagManager)
    {
    }

    /**
     * Render list of tags from a category
     * 
     * @param string $category One of Tag::CATEGORY_*
     */
    #[Route('/tags/{category}', name: 'tag_list')]
    public function listTags(string $category): Response
    {
        $tags = $this->tagManager->getTags($category);
        $images = $this->tagManager->getImagesForTags($tags);

        return $this->render('tag/list.html.twig', [
            'tags' => $tags,
            'images' => $images,
        ]);
    }
}
