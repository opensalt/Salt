<?php

namespace App\Domain\FrontMatter\Form;

use App\Domain\FrontMatter\DTO\FrontMatterDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @template-extends AbstractType<FrontMatterDto>
 */
class FrontMatterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('filename', TextType::class, [
                'label' => 'Template Filename',
                'help' => 'The filename of the page goes here, of the form "front:<em>PATH</em>.html.twig"',
                'help_html' => true,
                'required' => true,
                'attr' => [
                    'placeholder' => 'front:index.html.twig',
                ],
            ])
            ->add('source', TextareaType::class, [
                'label' => 'Template Content',
                'help' => 'Enter the page content here',
                'required' => true,
                'attr' => [
                    'rows' => 15,
                    'spellcheck' => 'false',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FrontMatterDto::class,
        ]);
    }
}
