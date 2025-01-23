<?php

namespace App\Form\Type;

use App\Entity\Framework\AdditionalField;
use App\Entity\Framework\LsDefGrade;
use App\Entity\Framework\LsDefItemType;
use App\Entity\Framework\LsDefLicence;
use App\Entity\Framework\LsItem;
use App\Form\DataTransformer\EducationAlignmentTransformer;
use App\Form\DataTransformer\ItemTypeTransformer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\LanguageType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Valid;
use Tetranz\Select2EntityBundle\Form\Type\Select2EntityType;

/**
 * @extends AbstractType<LsItem>
 */
class LsItemType extends AbstractType
{
    public function __construct(private EntityManagerInterface $em)
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
            ->add('fullStatement')
            ->add('humanCodingScheme')
            // ->add('identifier', null, ['attr'=>['placeholder'=>'hhhhhhhh-hhhh-hhhh-hhhh-hhhhhhhhhhhh']])
            ->add('listEnumInSource')
            ->add('abbreviatedStatement')
            ->add('conceptKeywords')
//            ->add('conceptKeywordsUri')
            ->add('language', LanguageType::class, [
                'required' => false,
                'preferred_choices' => ['en', 'es', 'fr'],
            ])
            ->add('educationalAlignment', EntityType::class, [
                'class' => LsDefGrade::class,
                'label' => 'Education Level',
                'choice_label' => 'code',
                'choice_attr' => static fn (LsDefGrade $val, $key, $index) => ['data-title' => $val->getTitle()],
                'required' => false,
                'multiple' => true,
                'query_builder' => static fn (EntityRepository $er) => $er->createQueryBuilder('g')->addOrderBy('g.rank'),
            ])
            ->add('itemType', Select2EntityType::class, [
                'multiple' => false,
                'remote_route' => 'lsdef_item_type_index_json',
                'class' => LsDefItemType::class,
                'primary_key' => 'id',
                'text_property' => 'title',
                'transformer' => ItemTypeTransformer::class,
                'minimum_input_length' => 0,
                'page_limit' => 1000,
                'scroll' => true,
                'allow_clear' => true,
                'delay' => 250,
                'placeholder' => 'Select Item Type',
            ])
            ->add('licence', EntityType::class, [
                'class' => LsDefLicence::class,
                'label' => 'License',
                'choice_label' => 'title',
                'required' => false,
            ])
            ->add('notes')
        ;

        $fields = $this->em->getRepository(AdditionalField::class)->findBy(['appliesTo' => LsItem::class]);
        if (count($fields)) {
            $builder->add('additional_fields', CustomFieldsType::class, [
                'applies_to' => LsItem::class,
                'label' => 'Additional fields',
                'constraints' => [new Valid()],
            ]);
        }

        $builder
            ->get('educationalAlignment')
            ->addModelTransformer(new EducationAlignmentTransformer($this->em))
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LsItem::class,
            'ajax' => false,
        ]);
    }
}
