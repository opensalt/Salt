<?php

namespace App\Controller\Credential;

use App\Domain\Credential\Command\ChangeDefinitionContent;
use App\Domain\Credential\Command\ChangeDefinitionHierarchy;
use App\Domain\Credential\Command\ChangeDefinitionOrganization;
use App\Domain\Credential\Command\CreateCredentialDefinitionDraft;
use App\Domain\Credential\Command\DeprecateCredentialDefinition;
use App\Domain\Credential\Command\PublishCredentialDefinition;
use App\Domain\Credential\CredentialDefinition;
use App\Domain\Credential\CredentialDefinitionRepository;
use App\Domain\Credential\DTO\CredentialDefinitionDto;
use App\Domain\Credential\Form\CredentialDefinitionContentType;
use App\Domain\Credential\Form\CredentialDefinitionCreateType;
use App\Domain\Credential\Form\CredentialDefinitionHierarchyType;
use App\Domain\Credential\Form\CredentialDefinitionOrganizationType;
use App\Entity\User\User;
use App\Repository\User\OrganizationRepository;
use App\Security\Permission;
use Ecotone\EventSourcing\EventStore;
use Ecotone\Modelling\CommandBus;
use Ecotone\Modelling\QueryBus;
use Prooph\EventStore\Metadata\MetadataMatcher;
use Prooph\EventStore\Metadata\Operator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[AsController]
class CredentialController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly QueryBus $queryBus,
        private readonly CredentialDefinitionRepository $repository,
        private readonly OrganizationRepository $organizationRepository,
    ) {
    }

    #[Route('/credential', name: 'credential_index', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $list = $this->queryBus->sendWithRouting('getAllCredentialDefinitions');

        $hierarchy = [];
        foreach ($list as $item) {
            $hierarchy[$item['hierarchyParent']][] = $item;
        }
        ksort($hierarchy);
        foreach ($hierarchy as $list) {
            usort($list, function ($a, $b) { return $a['name'] <=> $b['name']; });
        }

        return $this->render('credential/list.html.twig', [
            'list' => $hierarchy,
        ]);
    }

    #[Route('/credential/new', name: 'credential_new', methods: ['GET', 'POST'])]
    #[IsGranted(Permission::CREDENTIAL_DEF_CREATE)]
    public function new(Request $request, #[CurrentUser] User $user): Response
    {
        $credential = new CredentialDefinitionDto();
        if (null === $credential->organization) {
            $credential->organization = $user->getOrg();
        }
        $form = $this->createForm(CredentialDefinitionCreateType::class, $credential);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $orgId = $credential->organization->getId();
            if (!$this->isGranted(Permission::CREDENTIAL_DEF_EDIT_ALL)) {
                $orgId = $user->getOrg()->getId();
            }

            try {
                // Save the form data
                $this->commandBus->send(
                    new CreateCredentialDefinitionDraft(
                        $credential->hierarchyParent,
                        $orgId,
                        json5_decode($credential->content, true)
                    )
                );

                return $this->redirectToRoute('credential_index', [], Response::HTTP_SEE_OTHER);
            } catch (\Throwable $e) {
                $form->addError(new FormError('Error adding new document: '.$e->getMessage()));
            }
        }

        return $this->render('credential/new.html.twig', [
            'form' => $form->createView(),
            'def' => $credential->content,
        ]);
    }

    #[Route('/credential/{id}/{versionId}/edit', name: 'credential_edit', methods: ['GET', 'POST'])]
    public function editAction(Request $request, string $id, string $versionId, #[CurrentUser] User $user): Response
    {
        $uuid = Uuid::fromBase58($id);

        try {
            $credential = $this->repository->findBy($uuid);
        } catch (\Throwable $e) {
            throw $this->createNotFoundException('No credential found');
        }

        if (!$this->isGranted(Permission::CREDENTIAL_DEF_EDIT, $credential)) {
            throw $this->createAccessDeniedException('You cannot edit this credential');
        }

        if ($credential->getLastVersion()->getId()->toBase58() !== $versionId) {
            throw $this->createNotFoundException('Only the most recent version can be edited');
        }

        $lastVersion = $credential->getLastVersion();
        $content = $lastVersion->getContent();
        if (null !== $lastVersion->getPublishedAt()) {
            // The new version will have to be a derivative
            $content['version'] = $lastVersion->getDefinitionVersion() + 1;
            $replacementAlignment = [
                'type' => ['Alignment'],
                'targetName' => $content['name'],
                'targetUrl' => $content['id'],
                'targetType' => 'ext:replaces',
            ];
            $alignmentFound = false;
            foreach ($content['alignment'] ?? [] as $key => $alignment) {
                if ('ext:replaces' === ($alignment['targetType'] ?? null)) {
                    $content['alignment'][$key] = $replacementAlignment;
                    $alignmentFound = true;
                    break;
                }
            }
            if (!$alignmentFound) {
                $content['alignment'][] = $replacementAlignment;
            }

            // Was used above, but now remove as it is for the previous version
            unset($content['id']);
        }

        $credentialDto = new CredentialDefinitionDto();
        $credentialDto->content = json_encode($content);
        $credentialDto->hierarchyParent = $credential->getHierarchyParent();
        $credentialDto->organization = $user->getOrg();

        $form = $this->createForm(CredentialDefinitionContentType::class, $credentialDto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Save the form data
                $this->commandBus->send(
                    new ChangeDefinitionContent(
                        $uuid,
                        $credentialDto->content,
                    )
                );

                return $this->redirectToRoute('credential_index', [], Response::HTTP_SEE_OTHER);
            } catch (\Throwable $e) {
                $form->addError(new FormError('Error updating credential: '.$e->getMessage()));
            }
        }

        return $this->render('credential/edit.html.twig', [
            'form' => $form->createView(),
            'credential' => $credential,
            'def' => $credentialDto->content,
        ]);
    }

    #[Route('/credential/{id}/{versionId}/publish', name: 'credential_publish', methods: ['POST'])]
    public function publishAction(Request $request, string $id, string $versionId, #[CurrentUser] User $user): Response
    {
        $uuid = Uuid::fromBase58($id);

        try {
            $credential = $this->repository->findBy($uuid);
        } catch (\Throwable $e) {
            throw $this->createNotFoundException('No credential found');
        }

        if (!$this->isGranted(Permission::CREDENTIAL_DEF_EDIT, $credential)) {
            throw $this->createAccessDeniedException('You cannot edit this credential');
        }

        $this->commandBus->send(new PublishCredentialDefinition($uuid, Uuid::fromBase58($versionId)));

        return $this->redirectToRoute('credential_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/credential/{id}/{versionId}/deprecate', name: 'credential_deprecate', methods: ['POST'])]
    public function deprecateAction(Request $request, string $id, string $versionId, #[CurrentUser] User $user): Response
    {
        $uuid = Uuid::fromBase58($id);

        try {
            $credential = $this->repository->findBy($uuid);
        } catch (\Throwable $e) {
            throw $this->createNotFoundException('No credential found');
        }

        if (!$this->isGranted(Permission::CREDENTIAL_DEF_EDIT, $credential)) {
            throw $this->createAccessDeniedException('You cannot edit this credential');
        }

        $this->commandBus->send(new DeprecateCredentialDefinition($uuid, Uuid::fromBase58($versionId)));

        return $this->redirectToRoute('credential_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/credential/{id}/hierarchy', name: 'credential_hierarchy', methods: ['GET', 'POST'])]
    public function hierarchyAction(Request $request, string $id, #[CurrentUser] User $user): Response
    {
        $uuid = Uuid::fromBase58($id);

        try {
            $credential = $this->repository->findBy($uuid);
        } catch (\Throwable $e) {
            throw $this->createNotFoundException('No credential found');
        }

        if (!$this->isGranted(Permission::CREDENTIAL_DEF_EDIT, $credential)) {
            throw $this->createAccessDeniedException('You cannot edit this credential');
        }

        $credentialDto = new CredentialDefinitionDto();
        $credentialDto->hierarchyParent = $credential->getHierarchyParent();

        $form = $this->createForm(CredentialDefinitionHierarchyType::class, $credentialDto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Save the form data
                $this->commandBus->send(
                    new ChangeDefinitionHierarchy(
                        $uuid,
                        $credentialDto->hierarchyParent,
                    )
                );

                return $this->redirectToRoute('credential_index', [], Response::HTTP_SEE_OTHER);
            } catch (\Throwable $e) {
                $form->addError(new FormError('Error updating credential: '.$e->getMessage()));
            }
        }

        return $this->render('credential/hierarchy.html.twig', [
            'form' => $form->createView(),
            'credential' => $credential,
            'def' => $credentialDto->content,
        ]);
    }

    #[Route('/credential/{id}/organization', name: 'credential_organization', methods: ['GET', 'POST'])]
    public function organizationAction(Request $request, string $id, #[CurrentUser] User $user): Response
    {
        $uuid = Uuid::fromBase58($id);

        try {
            $credential = $this->repository->findBy($uuid);
        } catch (\Throwable $e) {
            throw $this->createNotFoundException('No credential found');
        }

        if (!$this->isGranted(Permission::CREDENTIAL_DEF_EDIT_ALL)) {
            throw $this->createAccessDeniedException('You cannot edit the organization of this credential');
        }

        $credentialDto = new CredentialDefinitionDto();
        $credentialDto->hierarchyParent = $credential->getHierarchyParent();
        $credentialDto->organization = $this->organizationRepository->find($credential->getOrganization());

        $form = $this->createForm(CredentialDefinitionOrganizationType::class, $credentialDto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Save the form data
                $this->commandBus->send(
                    new ChangeDefinitionOrganization(
                        $uuid,
                        $credentialDto->organization->getId(),
                    )
                );

                return $this->redirectToRoute('credential_index', [], Response::HTTP_SEE_OTHER);
            } catch (\Throwable $e) {
                $form->addError(new FormError('Error updating credential: '.$e->getMessage()));
            }
        }

        return $this->render('credential/organization.html.twig', [
            'form' => $form->createView(),
            'credential' => $credential,
            'def' => $credentialDto->content,
        ]);
    }

    #[Route('/credential/{id}', name: 'credential_show_redirect', methods: ['GET'])]
    public function showCurrent(string $id): Response
    {
        $uuid = Uuid::fromBase58($id);

        try {
            $credential = $this->repository->findBy($uuid);
        } catch (\Throwable $e) {
            throw $this->createNotFoundException('No credential found');
        }

        if (!$this->isGranted(Permission::CREDENTIAL_DEF_VIEW, $credential)) {
            throw $this->createAccessDeniedException('You cannot view this credential');
        }

        $curVersion = $credential->getLastVersion();

        //dump($credential);

        return $this->redirectToRoute('credential_show', ['id' => $id, 'versionId' => $curVersion->getId()->toBase58()]);
    }

    #[Route('/credential/{id}/{versionId}.{_format}', name: 'credential_show', defaults: ['_format' => null], methods: ['GET'])]
    public function show(Request $request, string $id, string $versionId, EventStore $store): Response
    {
        $uuid = Uuid::fromBase58($id);

        try {
            $credential = $this->repository->findBy($uuid);
        } catch (\Throwable $e) {
            throw $this->createNotFoundException('No credential found');
        }

        if (!$this->isGranted(Permission::CREDENTIAL_DEF_VIEW, $credential)) {
            throw $this->createAccessDeniedException('You cannot view this credential');
        }

        $requestedVersion = null;
        foreach ($credential->getVersions() as $version) {
            if ($version->getId()->toBase58() === $versionId) {
                $requestedVersion = $version;
                break;
            }
        }

        if (!$requestedVersion) {
            throw $this->createNotFoundException('No credential found');
        }

        if ('jsonld' === $request->getRequestFormat()
            || in_array('application/ld+json', $request->getAcceptableContentTypes())
            || in_array('application/json', $request->getAcceptableContentTypes())
        ) {
            return new JsonResponse($requestedVersion->getContent());
        }

        $curVersion = $credential->getLastVersion();
        $isCurrentVersion = $curVersion->getId()->equals($requestedVersion->getId());

        if ($this->isGranted(Permission::CREDENTIAL_DEF_EDIT, $credential)) {
            $publishForm = $this->createPublishForm($credential);
            $deprecateForm = $this->createDeprecateForm($credential);
        }

        $history = $store->load(CredentialDefinition::STREAM, metadataMatcher: (new MetadataMatcher())->withMetadataMatch('_aggregate_id', Operator::EQUALS(), $uuid->toString()), deserialize: false);
        //dump($history);

        return $this->render('credential/show.html.twig', [
            'credential' => $credential,
            'def' => $requestedVersion,
            'isCurrentVersion' => $isCurrentVersion,
            'publishForm' => isset($publishForm) ? $publishForm->createView() : null,
            'deprecateForm' => isset($deprecateForm) ? $deprecateForm->createView() : null,
        ]);
    }

    private function createPublishForm(CredentialDefinition $credential): FormInterface
    {
        return $this->createFormBuilder()
            ->setAction(
                $this->generateUrl(
                    'credential_publish',
                    [
                        'id' => $credential->getId()->toBase58(),
                        'versionId' => $credential->getLastVersion()->getId()->toBase58(),
                    ]
                )
            )
            ->setMethod(Request::METHOD_POST)
            ->getForm();
    }

    private function createDeprecateForm(CredentialDefinition $credential): FormInterface
    {
        return $this->createFormBuilder()
            ->setAction(
                $this->generateUrl(
                    'credential_deprecate',
                    [
                        'id' => $credential->getId()->toBase58(),
                        'versionId' => $credential->getLastVersion()->getId()->toBase58(),
                    ]
                )
            )
            ->setMethod(Request::METHOD_POST)
            ->getForm();
    }
}
