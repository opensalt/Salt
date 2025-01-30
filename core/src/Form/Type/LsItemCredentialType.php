<?php

namespace App\Form\Type;

use App\DTO\ItemType\CredentialDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<CredentialDto>
 */
class LsItemCredentialType extends AbstractType
{
    public function __construct()
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('credential', HiddenType::class, [
                'label' => 'Credential',
            ])
            ->add('_isCredentialForm', HiddenType::class, [
                'required' => false,
                'mapped' => false,
                'data' => 'true',
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
            'data_class' => CredentialDto::class,
        ]);
    }
}
