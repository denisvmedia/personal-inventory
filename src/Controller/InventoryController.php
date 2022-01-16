<?php

namespace App\Controller;

use App\Forms\InventoryItemEditFormHandler;
use App\Inventory\DTO\Query;
use App\Inventory\Inventory;
use App\Storage\ImageStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class InventoryController extends AbstractController
{
    public function __construct(private ImageStorage $images, private Inventory $inventory)
    {
    }

    #[Route('/inventory', name: 'inventory_list')]
    #[Route('/inventory/tags/{category}/{tag}', name: 'inventory_list_by_tag')]
    public function listItems(Request $request, string $category = null, string $tag = null): Response
    {
        $itemList = $this->inventory->listItems(new Query($request, $category, $tag));
        return $this->render(
            'inventory/list.html.twig', 
            [
                'items' => $itemList->items,
                'breadcrumb' => $itemList->breadcrumb,
            ]
        );
    }

    #[Route('/inventory/{id<[0-9a-fA-F]{24}>}', name: 'inventory_get')]
    public function getItem(string $id): Response
    {
        $item = $this->inventory->getItem($id);
        return $this->render(
            'inventory/view.html.twig', 
            [
                'item' => $item,
                'images' => $this->images->getItemImages($item),
            ]
        );
    }

    #[Route('/inventory/add', name: 'inventory_add')]
    #[Route('/inventory/{id}/edit', name: 'inventory_edit')]
    public function editItem(Request $request, InventoryItemEditFormHandler $formHandler, ?string $id = null): Response
    {
        $result = $formHandler->submit($request, $id);

        if ($request->getMethod() === 'POST' && empty($result->errors)) {
            if ($request->request->get('submit', 'submit') === 'submit_add') {
                return $this->redirectToRoute('inventory_add');
            } elseif ($request->query->get('return_to', '') === 'list') {
                return $this->redirectToRoute('inventory_list');
            } else {
                return $this->redirectToRoute('inventory_get', ['id' => $id]);
            }
        }

        return $this->render(
            'inventory/edit.html.twig', 
            [
                'form' => $result->form->createView(),
                'mode' => null === $id ? 'new' : 'edit',
                'itemid' => $id,
                'images' => $this->images->getItemImages($result->item),
                'errors' => $result->errors,
            ]
        );
    }

    #[Route('/inventory/{id}/delete', name: 'inventory_delete', methods: ['POST'])]
    public function deleteItem(?string $id = null): Response
    {
        $this->inventory->delete($id);
        return $this->redirectToRoute('inventory_list');
    }

    /**
     * GET image content; POST to delete
     * 
     * Query string parameters "w" and "h" can be used to get a scaled version. Original images will be scaled as needed.
     */
    #[Route('/inventory/{id}/images/{filename}', name: 'inventory_image')]
    public function image(Request $request, $id, $filename): Response
    {
        $item = $this->inventory->getItem($id);
        if ($request->getMethod() === 'POST' && $request->request->get('action') === 'delete') {
            $this->images->deleteItemImage($item, $filename);
            return new JsonResponse(['success' => 1]);
        } else {
            $path = $this->images->getFilePath($item, $filename, $request->query->get('w'), $request->query->get('h'));
            if (file_exists($path)) {
                return new BinaryFileResponse($path, Response::HTTP_OK, ['Cache-Control' => 'max-age=14400']);
            } else {
                throw $this->createNotFoundException('Image not found');
            }
        }
    }
}
