<?php

namespace App\Controller\Framework;

use App\Command\CommandDispatcherTrait;
use App\Command\Framework\AddAssociationCommand;
use App\Command\Framework\AddExemplarToItemCommand;
use App\Command\Framework\AddTreeAssociationCommand;
use App\Command\Framework\DeleteAssociationCommand;
use App\Command\Framework\UpdateAssociationCommand;
use App\Entity\Framework\LsAssociation;
use App\Entity\Framework\LsDefAssociationGrouping;
use App\Entity\Framework\LsDoc;
use App\Entity\Framework\LsItem;
use App\Form\Type\LsAssociationType;
use App\Security\Permission;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/cfassociation')]
class LsAssociationController extends AbstractController
{
    use CommandDispatcherTrait;

    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
    ) {
    }

    /**
     * Lists all LsAssociation entities.
     */
    #[Route(path: '/', name: 'lsassociation_index', methods: ['GET'])]
    public function index(): Response
    {
        $em = $this->managerRegistry->getManager();

        $lsAssociations = $em->getRepository(LsAssociation::class)->findBy([], null, 100);

        return $this->render('framework/ls_association/index.html.twig', [
            'lsAssociations' => $lsAssociations,
        ]);
    }

    /**
     * Creates a new LsAssociation entity.
     */
    #[Route(path: '/new/{sourceLsItem}', name: 'lsassociation_new', methods: ['GET', 'POST'])]
    #[Route(path: '/new/{sourceLsItem}/{assocGroup}', name: 'lsassociation_new_ag', methods: ['GET', 'POST'])]
    #[IsGranted(Permission::ASSOCIATION_ADD_TO, 'sourceLsItem')]
    public function new(
        Request $request,
        #[MapEntity(id: 'sourceLsItem')] ?LsItem $sourceLsItem = null,
        #[MapEntity(id: 'assocGroup')] ?LsDefAssociationGrouping $assocGroup = null,
    ): Response {
        // @TODO: Add LsDoc of the new association for when adding via AJAX
        $ajax = $request->isXmlHttpRequest();

        $lsAssociation = new LsAssociation();
        if ($sourceLsItem) {
            $lsAssociation->setOriginLsItem($sourceLsItem);

            // Default to adding to source item's LsDoc
            $lsAssociation->setLsDoc($sourceLsItem->getLsDoc());
        }

        // set assocGroup if provided
        $lsAssociation->setGroup($assocGroup);

        $form = $this->createForm(LsAssociationType::class, $lsAssociation, ['ajax' => $ajax]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $command = new AddAssociationCommand($lsAssociation);
                $this->sendCommand($command);

                if ($ajax) {
                    return new Response($this->generateUrl('doc_tree_item_view', ['id' => $sourceLsItem->getId()]), Response::HTTP_CREATED);
                }

                return $this->redirectToRoute('lsassociation_show', ['id' => $lsAssociation->getId()]);
            } catch (\Exception $e) {
                $form->addError(new FormError('Error adding new association: '.$e->getMessage()));
            }
        }

        $lsDoc = $form->get('lsDoc')->getData();

        $ret = [
            'lsAssociation' => $lsAssociation,
            'form' => $form->createView(),
            'lsDoc' => $lsDoc,
        ];

        if ($ajax && $form->isSubmitted() && !$form->isValid()) {
            //return $this->render('framework/ls_association/new.html.twig', $ret, new Response('', Response::HTTP_UNPROCESSABLE_ENTITY));
            return $this->render('framework/ls_association/new.html.twig', $ret, new Response('', Response::HTTP_OK));
        }

        return $this->render('framework/ls_association/new.html.twig', $ret);
    }

    /**
     * Creates a new LsAssociation entity -- tree-view version, called via ajax.
     */
    #[Route(path: '/treenew/{lsDoc}', name: 'lsassociation_tree_new', methods: ['POST'])]
    #[IsGranted(Permission::ASSOCIATION_ADD_TO, 'lsDoc')]
    public function treeNew(Request $request, LsDoc $lsDoc): Response
    {
        // type, origin['externalDoc', 'id', 'identifier'], dest['externalDoc', 'id', 'identifier'], assocGroup
        foreach (['type', 'origin', 'dest'] as $value) {
            if (!$request->request->has($value)) {
                return new JsonResponse(['error' => ['message' => "Missing value: {$value}"]], Response::HTTP_BAD_REQUEST);
            }
        }

        try {
            $command = new AddTreeAssociationCommand(
                $lsDoc,
                $request->request->all('origin'), // passed as an array
                $request->request->get('type'),
                $request->request->all('dest'), // passed as an array
                $request->request->get('assocGroup'),
                $request->request->get('annotation')
            );
            $this->sendCommand($command);
            $lsAssociation = $command->getAssociation();

            // return id of created association
            $rv = [
                'id' => $lsAssociation?->getId(),
                'identifier' => $lsAssociation?->getIdentifier(),
            ];

            $response = new JsonResponse($rv);
            $response->headers->set('Cache-Control', 'no-cache');

            return $response;
        } catch (\Exception $e) {
            return new JsonResponse(['error' => ['message' => $e->getMessage()]], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Creates a new LsAssociation entity for an exemplar.
     *
     * @throws \InvalidArgumentException
     */
    #[Route(path: '/treenewexemplar/{originLsItem}', name: 'lsassociation_tree_new_exemplar', methods: ['GET', 'POST'])]
    #[IsGranted(Permission::ASSOCIATION_ADD_TO, 'originLsItem')]
    public function treeNewExemplar(Request $request, LsItem $originLsItem): Response
    {
        if (!$request->request->has('exemplarUrl')) {
            return new JsonResponse(['error' => ['message' => 'Missing value: exemplarUrl']], Response::HTTP_BAD_REQUEST);
        }

        try {
            $command = new AddExemplarToItemCommand(
                $originLsItem,
                $request->request->get('exemplarUrl'),
                $request->request->get('annotation')
            );
            $this->sendCommand($command);
            $lsAssociation = $command->getAssociation();

            $rv = [
                'id' => $lsAssociation?->getId(),
                'identifier' => $lsAssociation?->getIdentifier(),
            ];

            $response = new JsonResponse($rv);
            $response->headers->set('Cache-Control', 'no-cache');

            return $response;
        } catch (\Exception $e) {
            return new JsonResponse(['error' => ['message' => $e->getMessage()]], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Finds and displays a LsAssociation entity.
     */
    #[Route(path: '/{id}', name: 'lsassociation_show', methods: ['GET'])]
    public function show(LsAssociation $lsAssociation): Response
    {
        $deleteForm = $this->createDeleteForm($lsAssociation);

        return $this->render('framework/ls_association/show.html.twig', [
            'lsAssociation' => $lsAssociation,
            'delete_form' => $deleteForm->createView(),
        ]);
    }

    /**
     * Displays a form to edit an existing LsAssociation entity.
     */
    #[Route(path: '/{id}/edit', name: 'lsassociation_edit', methods: ['GET', 'POST'])]
    #[IsGranted(Permission::ASSOCIATION_EDIT, 'lsAssociation')]
    public function edit(Request $request, LsAssociation $lsAssociation): Response
    {
        $deleteForm = $this->createDeleteForm($lsAssociation);
        $editForm = $this->createForm(LsAssociationType::class, $lsAssociation);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            try {
                $command = new UpdateAssociationCommand($lsAssociation);
                $this->sendCommand($command);

                return $this->redirectToRoute('lsassociation_edit', ['id' => $lsAssociation->getId()]);
            } catch (\Exception $e) {
                $editForm->addError(new FormError('Error updating new association: '.$e->getMessage()));
            }
        }

        return $this->render('framework/ls_association/edit.html.twig', [
            'lsAssociation' => $lsAssociation,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ]);
    }

    /**
     * Deletes a LsAssociation entity.
     */
    #[Route(path: '/{id}', name: 'lsassociation_delete', methods: ['DELETE'])]
    #[IsGranted(Permission::ASSOCIATION_EDIT, 'lsAssociation')]
    public function delete(Request $request, LsAssociation $lsAssociation): RedirectResponse
    {
        $form = $this->createDeleteForm($lsAssociation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $command = new DeleteAssociationCommand($lsAssociation);
            $this->sendCommand($command);
        }

        return $this->redirectToRoute('lsassociation_index');
    }

    /**
     * Remove a child LSItem.
     */
    #[Route(path: '/{id}/remove', name: 'lsassociation_remove', methods: ['POST'])]
    #[IsGranted(Permission::ASSOCIATION_EDIT, 'lsAssociation')]
    public function removeChild(LsAssociation $lsAssociation): Response
    {
        $command = new DeleteAssociationCommand($lsAssociation);
        $this->sendCommand($command);

        return $this->render('framework/ls_association/remove_child.html.twig', []);
    }

    /**
     * Export an LsAssociation entity.
     */
    #[Route(path: '/{id}/export', name: 'lsassociation_export', defaults: ['_format' => 'json'], methods: ['GET'])]
    public function export(LsAssociation $lsAssociation): Response
    {
        return $this->render('framework/ls_association/export.json.twig', [
            'lsAssociation' => $lsAssociation,
        ]);
    }

    /**
     * Creates a form to delete a LsAssociation entity.
     */
    private function createDeleteForm(LsAssociation $lsAssociation): FormInterface
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('lsassociation_delete', ['id' => $lsAssociation->getId()]))
            ->setMethod(Request::METHOD_DELETE)
            ->getForm()
            ;
    }
}
