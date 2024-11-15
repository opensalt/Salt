<?php

declare(strict_types=1);

namespace App\Domain\Issuer\Form;

use App\Domain\Issuer\DTO\IssuerDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @template-extends AbstractType<IssuerDto>
 */
class IssuerAddType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Name',
                'required' => true,
            ])
            ->add('did', TextType::class, [
                'label' => 'DID',
                'required' => false,
            ])
            ->add('contact', TextType::class, [
                'label' => 'Contact',
                'required' => false,
            ])
            ->add('trusted', ChoiceType::class, [
                'label' => 'Is Trusted',
                'choices' => ['Yes' => true, 'No' => false],
                'required' => false,
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'attr' => [
                    'rows' => 5,
                ],
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => IssuerDto::class,
        ]);
    }
}
