<?php

declare(strict_types=1);

namespace App\Domain\Credential\Form;

use App\Domain\Credential\DTO\CredentialDefinitionDto;
use App\Entity\User\Organization;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class CredentialDefinitionOrganizationType extends AbstractType
{
    public function __construct(
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
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
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CredentialDefinitionDto::class,
        ]);
    }
}
