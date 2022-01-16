<?php

declare(strict_types=1);

namespace App\Forms;

use App\Entity\InventoryItem;
use App\Forms\DTO\InventoryItemEditResult;
use App\Forms\Type\InventoryItemType;
use App\Inventory\Inventory;
use App\Storage\DocumentStorage;
use App\Storage\File\UploadedFile;
use App\Storage\ImageStorage;
use Exception;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

final class InventoryItemEditFormHandler
{
    public function __construct(
        private DocumentStorage $docs,
        private ImageStorage $images,
        private Inventory $inventory,
        private FormFactoryInterface $formFactory,
    )
    {
    }

    public function submit(Request $request, ?string $id): InventoryItemEditResult
    {
        $errors = [];
        $item = $this->inventory->getOrCreateItem($id);

        $form = $this->formFactory->create(InventoryItemType::class, $item, [
            'request' => $request,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $item = $form->getData();
            try {
                $id = $this->docs->saveInventoryItem($item);
                $images = $request->files->get('form');
                if (!empty($images)) {
                    $this->images->saveItemImages($item, UploadedFile::fromUploadedFiles($images));
                    $this->deleteImages($request, $item);
                }
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        return new InventoryItemEditResult($item, $form, $errors);
    }

    /**
     * Delete images from form POST
     *
     * @param Request $request
     * @param InventoryItem $item
     */
    private function deleteImages(Request $request, InventoryItem $item): void
    {
        $formInput = $request->request->get('delete_images');
        if ($formInput) {
            foreach ($formInput as $filename) {
                $this->images->deleteItemImage($item, $filename);
            }
        }
    }
}