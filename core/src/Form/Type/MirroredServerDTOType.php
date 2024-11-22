<?php

namespace App\Form\Type;

use App\Entity\Framework\Mirror\OAuthCredential;
use App\Entity\Framework\Mirror\Server;
use App\Form\DTO\MirroredServerDTO;
use Doctrine\ORM\EntityRepository;
use League\Uri\UriString;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<MirroredServerDTO>
 */
class MirroredServerDTOType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('url', TextType::class, [
                'label' => 'URL of server to mirror',
                'attr' => [
                    'placeholder' => 'https://server.hostname.com/',
                ],
                'help' => 'Enter the URL of the server you want to mirror.',
            ])
            ->add('autoAddFoundFrameworks', ChoiceType::class, [
                'label' => 'Automatically add frameworks?',
                'help' => 'Should all frameworks found on this server be automatically added to the list of frameworks being mirrored?',
                'help_html' => true,
                'choices' => [
                    'No' => false,
                    'Yes' => true,
                ],
            ])
            ->add('credentials', EntityType::class, [
                'label' => 'Use credentials (if required)',
                'help' => 'Select the credentials to use when authenticating with the server if they are needed.',
                'help_html' => true,
                'class' => OAuthCredential::class,
                'choice_label' => static fn (OAuthCredential $credential) => $credential->getKey().' @ '.$credential->getAuthenticationEndpoint(),
                'required' => false,
                'multiple' => false,
                'query_builder' => static fn (EntityRepository $repo) => $repo->createQueryBuilder('c')
                    ->orderBy('c.key', 'ASC')
                    ->addOrderBy('c.authenticationEndpoint', 'ASC'),
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Status',
                'help' => 'The "Suspended" status will disable automatic checking of frameworks on this server.',
                'help_html' => true,
                'choices' => [
                    'Active' => Server::STATUS_ACTIVE,
                    'Suspended' => Server::STATUS_SUSPENDED,
                ],
            ])
        ;

        // Remove API endpoint from end of URL
        $builder->get('url')->addEventListener(
            FormEvents::PRE_SUBMIT,
            static function (FormEvent $event): void {
                $url = $event->getData();
                if (null === $url) {
                    return;
                }

                $uri = UriString::parse($url);
                $uri['path'] = preg_replace('!/ims/case/v1p0/CFDocuments/?$!', '/', $uri['path']);
                $url = UriString::build($uri);

                $event->setData($url);
            }
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
