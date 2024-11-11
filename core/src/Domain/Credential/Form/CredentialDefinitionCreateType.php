<?php

declare(strict_types=1);

namespace App\Domain\Credential\Form;

use App\Domain\Credential\DTO\CredentialDefinitionDto;
use App\Entity\User\Organization;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @template-extends AbstractType<CredentialDefinitionDto>
 */
class CredentialDefinitionCreateType extends AbstractType
{
    public function __construct(
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('hierarchyParent', TextType::class, [
                'required' => true,
                'label' => 'Collection',
                'help' => 'Enter the name of the collection that this credential definition should be listed under.',
            ])
            ->add('organization', EntityType::class, [
                'required' => true,
                'disabled' => !$this->authorizationChecker->isGranted('ROLE_ADMIN'),
                // 'disabled' => true,
                'placeholder' => 'None',
                'help' => 'Select the organization that this credential definition belongs to.',
                'label' => 'Owning Organization',
                'class' => Organization::class,
                'choice_label' => 'name',
            ])
            ->add('content', HiddenType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CredentialDefinitionDto::class,
        ]);
    }
}
