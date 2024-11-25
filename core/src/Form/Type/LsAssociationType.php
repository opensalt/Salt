<?php

namespace App\Form\Type;

use App\Entity\Framework\AdditionalField;
use App\Entity\Framework\LsAssociation;
use App\Entity\Framework\LsDoc;
use App\Entity\Framework\LsItem;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Valid;

/**
 * @extends AbstractType<LsAssociation>
 */
class LsAssociationType extends AbstractType
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choiceList = [];
        foreach (LsAssociation::typeChoiceList() as $choice) {
            $choiceList[$choice] = $choice;
        }

        if (!$options['ajax']) {
            $builder
                ->add('uri')
                ->add('originNodeUri')
            ;
        }

        $builder
            ->add('type', ChoiceType::class, [
                'choices' => $choiceList,
                'label' => 'Relationship Type',
            ])
            ->add('lsDoc', EntityType::class, [
                'label' => 'Choose Document',
                'placeholder' => '- Choose a Document or Enter URI Manually -',
                'choice_label' => 'title',
                'required' => false,
                'multiple' => false,
                'class' => LsDoc::class,
                'query_builder' => static fn (EntityRepository $er) => $er->createQueryBuilder('d')
                    ->orderBy('d.title', 'ASC'),
            ])
        ;

        $formModifier = static function (FormInterface $form, ?LsDoc $lsDoc = null) {
            if ($lsDoc) {
                $form->add('destinationLsItem', EntityType::class, [
                    'placeholder' => '- Select Statement From Document Below -',
                    'label' => 'Choose Statement',
                    'choice_label' => 'fullStatement',
                    'required' => true,
                    'multiple' => false,
                    'class' => LsItem::class,
                    'query_builder' => static fn (EntityRepository $er) => $er->createQueryBuilder('d')
                        ->where('d.lsDoc = '.$lsDoc->getId())
                        ->orderBy('d.fullStatement', 'ASC'),
                ]);
            } else {
                $form->add('destinationNodeIdentifier', null, [
                    'label' => 'Destination Id',
                    'required' => true,
                    'attr' => [
                        'placeholder' => 'Enter Identifier',
                    ],
                ]);
                $form->add('destinationNodeUri', null, [
                    'label' => 'Destination Uri',
                    'required' => false,
                    'attr' => [
                        'placeholder' => 'Enter URI',
                    ],
                ]);
            }
        };

        $fields = $this->em->getRepository(AdditionalField::class)
            ->findBy(['appliesTo' => LsAssociation::class]);
        if (count($fields)) {
            $builder->add('additional_fields', CustomFieldsType::class, [
                'applies_to' => LsItem::class,
                'label' => 'Additional fields',
                'constraints' => [new Valid()],
            ]);
        }

        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            static function (FormEvent $event) use ($formModifier) {
                $data = $event->getData();

                $formModifier($event->getForm(), $data->getLsDoc());
            }
        );

        $builder->get('lsDoc')->addEventListener(
            FormEvents::POST_SUBMIT,
            static function (FormEvent $event) use ($formModifier) {
                $lsDoc = $event->getForm()->getData();
                $formModifier($event->getForm()->getParent(), $lsDoc);
            }
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LsAssociation::class,
            'ajax' => false,
            //'csrf_protection' => false,
        ]);
    }
}
