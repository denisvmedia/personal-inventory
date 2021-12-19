<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tag;

final class TagManager
{
    public function __construct(protected DocumentStorage $docs, protected ImageStorage $images)
    {
    }

    /**
     * @return array<Tag>
     */
    public function getTags(string $category): array
    {
        return $this->docs->getTags($category, ['count', 'name'])->toArray();
    }

    /**
     * @param array<Tag> $tags
     */
    public function getImagesForTags(array $tags): array
    {
        $images = [];

        foreach ($tags as $tag) {
            // Get a random image associated with the tag
            $images[$tag->getName()] = null;
            $item = $this->docs->getRandomInventoryItemByTag($tag->getCategory(), $tag->getName());
            if ($item) {
                $itemImages = $this->images->getItemImages($item);
                if ($count = count($itemImages)) {
                    $rand = rand(0, $count - 1);
                    $images[$tag->getName()] = ['itemid' => $item->getId(), 'filename' => $itemImages[$rand]];
                }
            }
        }

        return $images;
    }
}