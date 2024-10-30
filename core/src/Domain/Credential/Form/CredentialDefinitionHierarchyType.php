<?php

declare(strict_types=1);

namespace App\Domain\Credential\Form;

use App\Domain\Credential\DTO\CredentialDefinitionDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CredentialDefinitionHierarchyType extends AbstractType
{
    public function __construct(
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('hierarchyParent', TextType::class, [
                'required' => true,
                'label' => 'Location in hierarchy',
                'help' => 'Enter the name of the grouping that this credential definition should be listed under.',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CredentialDefinitionDto::class,
        ]);
    }
}
