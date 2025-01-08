<?php

namespace App\Domain\Issuer;

use App\Domain\Issuer\Command\AddIssuerCommand;
use App\Domain\Issuer\Command\UpdateIssuerCommand;
use App\Domain\Issuer\DTO\IssuerDto;
use App\Domain\Issuer\Entity\IssuerRepository;
use App\Domain\Issuer\Form\IssuerAddType;
use App\Domain\Issuer\Form\IssuerEditType;
use App\Domain\Issuer\ReadModel\IssuerByDidProjection;
use App\Domain\Issuer\ReadModel\IssuerListProjection;
use App\Security\Permission;
use Ecotone\Modelling\CommandBus;
use Ecotone\Modelling\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[AsController]
class IssuerController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly QueryBus $queryBus,
        private readonly IssuerRepository $repository,
    ) {
    }

    #[Route('/registry/issuer', name: 'issuer_registry_index', methods: ['GET'])]
    #[IsGranted(Permission::ISSUER_REGISTRY_LIST)]
    public function list(): Response
    {
        $issuers = $this->queryBus->sendWithRouting(IssuerListProjection::QUERY_ALL_ISSUERS);
        usort($issuers, function ($a, $b) {
            return $a->name <=> $b->name;
        });

        return $this->render('issuer/list.html.twig', [
            'issuers' => $issuers,
        ]);
    }

    #[Route('/registry/issuer/did/{id}', name: 'issuer_registry_by_did', methods: ['GET'])]
    public function getIssuerByDid(string $did): Response
    {
        try {
            $issuer = $this->queryBus->sendWithRouting(IssuerByDidProjection::QUERY_ISSUER_BY_DID, ['did' => $did]);
        } catch (\Throwable) {
            throw $this->createNotFoundException('Issuer not found');
        }

        return new JsonResponse($issuer);
    }

    #[Route('/registry/issuer/new', name: 'issuer_registry_new', methods: ['GET', 'POST'])]
    #[IsGranted(Permission::ISSUER_REGISTRY_ADD)]
    public function new(Request $request): Response
    {
        $issuer = new IssuerDto();
        $form = $this->createForm(IssuerAddType::class, $issuer);
        $form->handleRequest($request);

        try {
            if ($form->isSubmitted() && $form->isValid()) {
                $this->commandBus->send(new AddIssuerCommand(
                    $issuer->name,
                    $issuer->did,
                    $issuer->contact,
                    $issuer->notes,
                    $issuer->orgType,
                    $issuer->trusted
                ));

                return $this->redirectToRoute('issuer_registry_index');
            }
        } catch (\Throwable $e) {
            $form->addError(new FormError('Error adding new document: '.$e->getMessage()));
        }

        return $this->render('issuer/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/registry/issuer/{id}/edit', name: 'issuer_registry_edit', methods: ['GET', 'POST'])]
    #[IsGranted(Permission::ISSUER_REGISTRY_ADD)]
    public function edit(Request $request, string $id): Response
    {
        $issuer = $this->repository->findBy(Uuid::fromString($id));

        if (!$this->isGranted(Permission::ISSUER_REGISTRY_EDIT, $issuer)) {
            throw $this->createAccessDeniedException('Access denied');
        }

        $issuerDto = new IssuerDto();
        $issuerDto->name = $issuer->getName();
        $issuerDto->id = $id;
        $issuerDto->contact = $issuer->getContact();
        $issuerDto->notes = $issuer->getNotes();
        $issuerDto->did = $issuer->getDid();
        $issuerDto->trusted = $issuer->getTrusted();

        $form = $this->createForm(IssuerEditType::class, $issuerDto);
        $form->handleRequest($request);

        try {
            if ($form->isSubmitted() && $form->isValid()) {
                $this->commandBus->send(new UpdateIssuerCommand(
                    $id,
                    $issuerDto->name,
                    $issuerDto->did,
                    $issuerDto->contact,
                    $issuerDto->notes,
                    $issuerDto->orgType,
                    $issuerDto->trusted
                ));

                return $this->redirectToRoute('issuer_registry_index');
            }
        } catch (\Throwable $e) {
            $form->addError(new FormError('Error adding new document: '.$e->getMessage()));
        }

        return $this->render('issuer/edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/registry/issuer/{id}', name: 'issuer_registry_show', methods: ['GET'])]
    public function show(string $id): Response
    {
        $uuid = Uuid::fromString($id);
        $issuer = $this->repository->findBy($uuid);

        if (!$issuer) {
            throw $this->createNotFoundException('Issuer not found');
        }

        if (!$this->isGranted(Permission::ISSUER_REGISTRY_VIEW, $issuer)) {
            throw $this->createAccessDeniedException('Access denied');
        }

        return $this->render('issuer/show.html.twig', [
            'issuer' => $issuer,
        ]);
    }
}
