<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use App\Entity\InventoryItem;
use App\Entity\Tag;
use App\Service\DocumentStorage;
use App\Service\ImageStorage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class InventoryController extends AbstractController
{
    public function __construct(protected DocumentStorage $docs, protected ImageStorage $images)
    {
    }

    #[Route('/inventory', name: 'inventory_list')]
    #[Route('/inventory/tags/{category}/{tag}', name: 'inventory_list_by_tag')]
    public function listItems(Request $request, string $category = null, string $tag = null)
    {
        $breadcrumb = '';
        if ($category && $tag) {
            $items = $this->docs->getInventoryItemsByTag($category, $tag);
            $breadcrumb = $tag;
        } elseif ($query = $request->query->get('q', '')) {
            $items = $this->docs->searchInventoryItems($query);
            $breadcrumb = $query;
        } else {
            $items = $this->docs->getInventoryItems();
        }
        return $this->render(
            'inventory/list.html.twig', 
            [
                'items' => $items,
                'breadcrumb' => $breadcrumb
            ]
        );
    }

    #[Route('/inventory/{id<[0-9a-fA-F]{24}>}', name: 'inventory_get')]
    public function getItem($id)
    {
        $item = $this->docs->getInventoryItem($id);
        if (!$item) {
            throw $this->createNotFoundException('Item not found');
        }
        return $this->render(
            'inventory/view.html.twig', 
            ['item' => $item, 'images' => $this->images->getItemImages($item)]
        );
    }

    #[Route('/inventory/add', name: 'inventory_add')]
    #[Route('/inventory/{id}/edit', name: 'inventory_edit')]
    public function editItem(Request $request, $id = null)
    {
        $errors = [];
        if ($id) {
            $item = $this->docs->getInventoryItem($id);
            if (!$item) {
                throw $this->createNotFoundException('Item not found');
            }
            $images = $this->images->getItemImages($item);
            $mode = 'edit';
        } else {
            $item = new InventoryItem();
            $images = [];
            $mode = 'new';
        }

        // Handle delete
        if ($request->isMethod('POST') && $request->request->get('submit', 'submit') === 'delete') {
            $this->docs->deleteInventoryItem($item);
            return $this->redirectToRoute('inventory_list');
        }

        $form = $this->getItemForm($request, $item);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $item = $form->getData();
            try {
                $id = $this->docs->saveInventoryItem($item);
                $this->images->saveItemImages($item, $request->files->get('form')['images']);
                $this->deleteImages($request, $item);
            } catch (\Exception $e) {
                $errors[] = $e->getMessage();
            }
            if (!$errors) {
                if ($request->request->get('submit', 'submit') === 'submit_add') {
                    return $this->redirectToRoute('inventory_add');
                } elseif ($request->query->get('return_to', '') === 'list') {
                    return $this->redirectToRoute('inventory_list');
                } else {
                    return $this->redirectToRoute('inventory_get', ['id' => $id]);
                }
            }
        }

        return $this->render(
            'inventory/edit.html.twig', 
            [
                'form' => $form->createView(), 
                'mode' => $mode, 
                'itemid' => $item->getId(),
                'images' => $images,
                'errors' => $errors
            ]
        );
    }

    private function getItemForm(Request $request, InventoryItem $item)
    {
        $tagAttributes = [
            'attr' => ['class' => 'tags'],
            'expanded' => false,
            'help' => 'Hit enter or comma to create new tags',
            'multiple' => true,
            'required' => false
        ];

        return $this->createFormBuilder($item)
            ->add('name', TextType::class)
            ->add('quantity', IntegerType::class)
            ->add('manufacturer', TextType::class, ['required' => false])
            ->add('model', TextType::class, ['required' => false])
            ->add('serialNumbers', TextareaType::class, ['required' => false])
            ->add(
                'purchasePrice', 
                MoneyType::class, 
                // TODO: Make currency configurable
                ['label' => 'Purchase price (per item)', 'required' => false, 'currency' => 'USD']
            )
            ->add(
                'value', 
                MoneyType::class, 
                // TODO: Make currency configurable
                ['label' => 'Current value (per item)', 'required' => false, 'currency' => 'USD']
            )
            ->add(
                'types',
                ChoiceType::class,
                [
                    'label' => 'Type / Tags',
                    'choices' => $this->getTags($request, 'types', Tag::CATEGORY_ITEM_TYPE),
                ] + $tagAttributes
            )
            ->add(
                'locations',
                ChoiceType::class,
                [
                    'label' => 'Location(s)',
                    'choices' => $this->getTags($request, 'locations', Tag::CATEGORY_ITEM_LOCATION),
                ] + $tagAttributes
            )
            ->add(
                'acquiredDate', 
                DateType::class,
                [
                    'label' => 'Date Acquired', 
                    'widget' => 'single_text',
                    'required' => false
                ]
            )
            ->add(
                'notes', 
                TextareaType::class,
                ['required' => false])
            ->add(
                'images',
                FileType::class,
                [
                    'label' => 'Add Images', 
                    'multiple' => true, 
                    'mapped' => false, 
                    'required' => false,
                    'attr' => ['accept' => 'image/*']
                ]
            )
            ->getForm();
    }

    /**
     * Get tags, including any new tags POSTed through the form
     * 
     * @param Request $request HTTP request
     * @param string $field Form and entity field name
     * @param string $tagCategory
     * @return string[]
     */
    private function getTags(Request $request, $field, $tagCategory)
    {
        $tags = [];
        if ($request->getMethod() === 'POST') {
            $formInput = $request->request->all('form');
            if (array_key_exists($field, $formInput)) {
                $tags = array_combine($formInput[$field], $formInput[$field]);
            }
        }
        foreach ($this->docs->getTags($tagCategory) as $tag) {
            $tags[(string) $tag] = (string) $tag;
        }
        return $tags;
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

    /**
     * GET image content; POST to delete
     * 
     * Query string parameters "w" and "h" can be used to get a scaled version. Original images will be scaled as needed.
     */
    #[Route('/inventory/{id}/images/{filename}', name: 'inventory_image')]
    public function image(Request $request, $id, $filename): Response
    {
        $item = $this->docs->getInventoryItem($id);
        if (!$item) {
            throw $this->createNotFoundException('Item not found');
        }
        if ($request->getMethod() === 'POST' && $request->request->get['action'] === 'delete') {
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
