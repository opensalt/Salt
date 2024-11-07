<?php

declare(strict_types=1);

namespace App\Controller\FrontMatter;

use App\Domain\FrontMatter\DTO\FrontMatterDto;
use App\Domain\FrontMatter\Form\FrontMatterType;
use App\Entity\FrontMatter\FrontMatter;
use App\Repository\FrontMatterRepository;
use App\Security\Permission;
use Ecotone\Modelling\CommandBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(Permission::FRONT_MATTER_CREATE)]
#[Route('/front-edit')]
class FrontMatterEditController extends AbstractController
{
    public function __construct(
        private readonly FrontMatterRepository $twigTemplateRepository,
        private readonly CommandBus $commandBus,
    ) {
    }

    #[Route('/', name: 'front_matter_list')]
    #[IsGranted(Permission::FRONT_MATTER_LIST)]
    public function list(): Response
    {
        $templates = $this->twigTemplateRepository->findAll();
        usort($templates, fn ($a, $b) => $a->getFilename() <=> $b->getFilename());

        return $this->render('front_matter/list.html.twig', [
            'templates' => $templates,
        ]);
    }

    #[Route('/new', name: 'front_matter_new', methods: ['GET', 'POST'])]
    #[IsGranted(Permission::FRONT_MATTER_CREATE)]
    public function new(Request $request): Response
    {
        $template = new FrontMatterDto();
        $form = $this->createForm(FrontMatterType::class, $template);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->commandBus->sendWithRouting('createFrontMatter', $template);

                return $this->redirectToRoute('front_matter_list', [], Response::HTTP_SEE_OTHER);
            } catch (\Throwable $e) {
                $form->addError(new FormError('Error adding new template: '.$e->getMessage()));
            }
        }

        return $this->render('front_matter/new.html.twig', [
            'form' => $form->createView(),
            'template' => $template,
        ]);
    }

    #[Route('/{id}/edit', name: 'front_matter_edit', methods: ['GET', 'POST'])]
    #[IsGranted(Permission::FRONT_MATTER_EDIT, 'template')]
    public function edit(Request $request, FrontMatter $template): Response
    {
        $updateTemplate = new FrontMatterDto();
        $updateTemplate->id = $template->getId();
        $updateTemplate->filename = $template->getFilename();
        $updateTemplate->source = $template->getSource();
        $form = $this->createForm(FrontMatterType::class, $updateTemplate);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->commandBus->sendWithRouting('updateFrontMatter', $updateTemplate, metadata: ['aggregate.id' => $template->getId()]);

                return $this->redirectToRoute('front_matter_list', [], Response::HTTP_SEE_OTHER);
            } catch (\Throwable $e) {
                $form->addError(new FormError('Error updating template: '.$e->getMessage()));
            }
        }

        $deleteForm = $this->createDeleteForm($template);

        return $this->render('front_matter/edit.html.twig', [
            'form' => $form->createView(),
            'template' => $template,
            'delete_form' => $deleteForm->createView(),
        ]);
    }

    #[Route(path: '/{id}', name: 'front_matter_delete', methods: ['DELETE'])]
    #[IsGranted(Permission::FRONT_MATTER_DELETE, 'template')]
    public function delete(Request $request, FrontMatter $template): Response
    {
        $form = $this->createDeleteForm($template);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->commandBus->sendWithRouting('deleteFrontMatter', $template, metadata: ['aggregate.id' => $template->getId()]);
        }

        return $this->redirectToRoute('front_matter_list');
    }

    private function createDeleteForm(FrontMatter $template): FormInterface
    {
        return $this->createFormBuilder()
            ->setAction(
                $this->generateUrl(
                    'front_matter_delete',
                    ['id' => $template->getId()]
                )
            )
            ->setMethod(Request::METHOD_DELETE)
            ->getForm();
    }
}
