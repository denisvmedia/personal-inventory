<?php

declare(strict_types=1);

namespace App\Forms\Type;

use App\Entity\Tag;
use App\Storage\DocumentStorage;
use LogicException;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class InventoryItemType extends AbstractType
{
    public function __construct(private DocumentStorage $docs, private string $appCurrency)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Request $request */
        $request = $options['request'] ?? null;
        if (!$request instanceof Request) {
            throw new LogicException('Unexpected request type');
        }

        $tagAttributes = [
            'attr' => ['class' => 'tags'],
            'expanded' => false,
            'help' => 'Hit enter or comma to create new tags',
            'multiple' => true,
            'required' => false
        ];

        $builder->add('name', TextType::class)
            ->add('quantity', IntegerType::class)
            ->add('manufacturer', TextType::class, ['required' => false])
            ->add('model', TextType::class, ['required' => false])
            ->add('url', UrlType::class, ['required' => false])
            ->add('serialNumbers', TextareaType::class, ['required' => false])
            ->add(
                'purchasePrice',
                MoneyType::class,
                ['label' => 'Purchase price (per item)', 'required' => false, 'currency' => $this->appCurrency]
            )
            ->add(
                'value',
                MoneyType::class,
                ['label' => 'Current value (per item)', 'required' => false, 'currency' => $this->appCurrency]
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
                    'attr' => ['accept' => 'image/*'],
                    'constraints' => [
                        new Assert\All([
                            new Assert\Image(maxWidth: 4000, maxHeight: 4000, maxSize: '2048k'),
                        ]),
                    ],
                ]
            );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(array(
            'request' => null,
        ));
    }

    /**
     * Get tags, including any new tags POSTed through the form
     *
     * @param Request $request HTTP request
     * @param string $field Form and entity field name
     * @param string $tagCategory
     * @return string[]
     */
    private function getTags(Request $request, $field, $tagCategory): array
    {
        $tags = [];
        if ($request->getMethod() === 'POST') {
            $formInput = $request->request->all('inventory_item');
            if (array_key_exists($field, $formInput)) {
                $tags = array_combine($formInput[$field], $formInput[$field]);
            }
        }
        foreach ($this->docs->getTags($tagCategory) as $tag) {
            $tags[(string) $tag] = (string) $tag;
        }
        return $tags;
    }
}