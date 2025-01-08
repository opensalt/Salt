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
            ->add('did', TextareaType::class, [
                'label' => 'DIDs',
                'attr' => [
                    'rows' => 5,
                ],
                'required' => false,
                'help' => 'Enter one or more DIDs separated by newlines.',
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
            ->add('orgType', ChoiceType::class, [
                'label' => 'Organization Type',
                'choices' => [
                    'K12 School' => 'K12 School',
                    '2 Year College' => '2 Year College',
                    '4 Year College' => '4 Year College',
                    'University' => 'University',
                    'Government Agency' => 'Government Agency',
                    'Licensing Agency' => 'Licensing Agency',
                    'Credentialing Agency' => 'Credentialing Agency',
                    'Industry Organization' => 'Industry Organization',
                    'Other' => 'Other',
                ],
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
