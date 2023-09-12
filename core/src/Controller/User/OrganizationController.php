<?php

namespace App\Controller\User;

use App\Command\CommandDispatcherTrait;
use App\Command\User\AddOrganizationCommand;
use App\Command\User\DeleteOrganizationCommand;
use App\Command\User\UpdateOrganizationCommand;
use App\Entity\User\Organization;
use App\Form\Type\OrganizationType;
use App\Security\Permission;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/admin/organization')]
#[IsGranted(Permission::MANAGE_ORGANIZATIONS)]
class OrganizationController extends AbstractController
{
    use CommandDispatcherTrait;

    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
    ) {
    }

    /**
     * Lists all organization entities.
     */
    #[Route(path: '/', methods: ['GET'], name: 'admin_organization_index')]
    public function index(): Response
    {
        $em = $this->managerRegistry->getManager();

        $organizations = $em->getRepository(Organization::class)->findAll();

        return $this->render('user/organization/index.html.twig', [
            'organizations' => $organizations,
        ]);
    }

    /**
     * Creates a new organization entity.
     */
    #[Route(path: '/new', name: 'admin_organization_new', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $organization = new Organization();
        $form = $this->createForm(OrganizationType::class, $organization);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $command = new AddOrganizationCommand($organization);
                $this->sendCommand($command);

                return $this->redirectToRoute('admin_organization_index');
            } catch (\Exception $e) {
                $form->addError(new FormError($e->getMessage()));
            }
        }

        return $this->render('user/organization/new.html.twig', [
            'organization' => $organization,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Finds and displays an organization entity.
     */
    #[Route(path: '/{id}', name: 'admin_organization_show', methods: ['GET'])]
    public function show(Organization $organization): Response
    {
        $deleteForm = $this->createDeleteForm($organization);

        return $this->render('user/organization/show.html.twig', [
            'organization' => $organization,
            'delete_form' => $deleteForm->createView(),
        ]);
    }

    /**
     * Displays a form to edit an existing organization entity.
     */
    #[Route(path: '/{id}/edit', name: 'admin_organization_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Organization $organization): Response
    {
        $deleteForm = $this->createDeleteForm($organization);
        $editForm = $this->createForm(OrganizationType::class, $organization);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            try {
                $command = new UpdateOrganizationCommand($organization);
                $this->sendCommand($command);

                return $this->redirectToRoute('admin_organization_index');
            } catch (\Exception $e) {
                $editForm->addError(new FormError($e->getMessage()));
            }
        }

        return $this->render('user/organization/edit.html.twig', [
            'organization' => $organization,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ]);
    }

    /**
     * Deletes an organization entity.
     */
    #[Route(path: '/{id}', name: 'admin_organization_delete', methods: ['DELETE'])]
    public function delete(Request $request, Organization $organization): RedirectResponse
    {
        $form = $this->createDeleteForm($organization);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $command = new DeleteOrganizationCommand($organization);
            $this->sendCommand($command);
        }

        return $this->redirectToRoute('admin_organization_index');
    }

    /**
     * Creates a form to delete an organization entity.
     */
    private function createDeleteForm(Organization $organization): FormInterface
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('admin_organization_delete', ['id' => $organization->getId()]))
            ->setMethod(Request::METHOD_DELETE)
            ->getForm()
        ;
    }
}
