<?php

namespace App\Form\Type;

use App\Entity\Framework\LsItem;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<LsItem>
 */
class LsItemJobType extends AbstractType
{
    public function __construct()
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (!$options['ajax']) {
            $builder
                ->add('uri')
                ->add('lsDoc')
            ;
        }

        $builder
            ->add('abbreviatedStatement', TextType::class, [
                'label' => 'Title',
            ])
            ->add('fullStatement', TextareaType::class, [
                'label' => 'Description',
            ])
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'ls_item';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LsItem::class,
            'ajax' => false,
        ]);
    }
}
