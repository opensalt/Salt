<?php

/**
 * Copyright (c) 2017 Public Consulting Group
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Form\Type;

use App\Entity\User\Organization;
use App\Entity\User\User;
use App\Security\Permission;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @extends AbstractType<User>
 */
class UserType extends AbstractType
{
    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $roleChoices = [];
        if ($this->authorizationChecker->isGranted('ROLE_SUPER_USER')) {
            $roleChoices = [
                'Super User' => 'ROLE_SUPER_USER',
                'Super Editor' => 'ROLE_SUPER_EDITOR',
            ];
        }
        $roleChoices['Organization Admin'] = 'ROLE_ADMIN';
        $roleChoices['Editor'] = 'ROLE_EDITOR';
        //$roleChoices['User'] = 'ROLE_USER';

        $builder
            ->add('username', TextType::class, [
                //'disabled' => !in_array('registration', $options['validation_groups']),
            ])
            ->add('plainPassword', TextType::class, [
                'required' => in_array('registration', $options['validation_groups'], true),
                'label' => 'Password',
            ])
            ->add('roles', ChoiceType::class, [
                'choices' => $roleChoices,
                'multiple' => true,
                'expanded' => true,
                'required' => false,
            ])
        ;

        if ($this->authorizationChecker->isGranted(Permission::MANAGE_ALL_USERS)) {
            $builder->add('org', EntityType::class, [
                'class' => Organization::class,
                'choice_label' => 'name',
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'validation_groups' => ['Default'],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'salt_userbundle_user';
    }
}
