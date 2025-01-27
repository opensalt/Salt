<?php

namespace App\Form\Type;

use App\DTO\ItemType\CourseDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\LanguageType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<CourseDto>
 */
class LsItemCourseType extends AbstractType
{
    public function __construct()
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Name',
                'help' => 'Name or title of the course.',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'help' => 'Description of this course.',
            ])
            ->add('webpage', UrlType::class, [
                'label' => 'Webpage',
                'required' => false,
                'help' => 'Webpage that describes this course.',
            ])
            ->add('codedNotation', TextType::class, [
                'label' => 'Coded Notation',
                'required' => false,
                'help' => 'Identifier for this course, eg. ENG101',
            ])
            ->add('inLanguage', LanguageType::class, [
                'required' => false,
                'preferred_choices' => ['en', 'es', 'fr'],
                'help' => 'Language used to teach this course',
            ])
            ->add('deliveryType', ChoiceType::class, [
                'label' => 'Delivery Type',
                'required' => false,
                'choices' => [
                    'In Person' => 'in-person',
                    'Online' => 'online',
                    'Hybrid' => 'hybrid',
                ],
                'help' => 'The method of delivering this course',
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
            'data_class' => CourseDto::class,
        ]);
    }
}
