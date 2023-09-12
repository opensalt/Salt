<?php

namespace App\Controller\Framework;

use App\Command\CommandDispatcherTrait;
use App\Command\Framework\AddItemTypeCommand;
use App\Command\Framework\DeleteItemTypeCommand;
use App\Command\Framework\UpdateItemTypeCommand;
use App\Entity\Framework\LsDefItemType;
use App\Form\Type\LsDefItemTypeType;
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

#[Route(path: '/cfdef/item_type')]
class LsDefItemTypeController extends AbstractController
{
    use CommandDispatcherTrait;

    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
    ) {
    }

    /**
     * Lists all LsDefItemType entities.
     */
    #[Route(path: '/', name: 'lsdef_item_type_index', methods: ['GET'])]
    public function index(): Response
    {
        $em = $this->managerRegistry->getManager();

        $lsDefItemTypes = $em->getRepository(LsDefItemType::class)->findBy([], null, 100);

        return $this->render('framework/ls_def_item_type/index.html.twig', [
            'lsDefItemTypes' => $lsDefItemTypes,
        ]);
    }

    /**
     * Lists all LsDefItemType entities.
     */
    #[Route(path: '/list.{_format}', name: 'lsdef_item_type_index_json', defaults: ['_format' => 'json'], methods: ['GET'])]
    public function jsonList(Request $request, string $_format = 'json'): Response
    {
        // ?page_limit=N&q=SEARCHTEXT
        $em = $this->managerRegistry->getManager();

        /** @var string|null $search */
        $search = $request->query->get('q', null);
        $page = $request->query->get('page', '1');
        $page_limit = $request->query->get('page_limit', '50');

        $results = $em->getRepository(LsDefItemType::class)
            ->getSelect2List($search, (int) $page_limit, (int) $page);

        if (!empty($search) && empty($results['results'][$search])) {
            array_unshift(
                $results['results'],
                ['id' => '__'.$search, 'title' => '(NEW) '.$search]
            );
        }

        return $this->render('framework/ls_def_item_type/json_list.'.$_format.'.twig', [
            'results' => $results['results'],
            'more' => $results['more'],
        ]);
    }

    /**
     * Creates a new LsDefItemType entity.
     */
    #[Route(path: '/new', name: 'lsdef_item_type_new', methods: ['GET', 'POST'])]
    #[IsGranted(Permission::FRAMEWORK_CREATE)]
    public function new(Request $request): Response
    {
        $lsDefItemType = new LsDefItemType();
        $form = $this->createForm(LsDefItemTypeType::class, $lsDefItemType);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $command = new AddItemTypeCommand($lsDefItemType);
                $this->sendCommand($command);

                return $this->redirectToRoute('lsdef_item_type_show', ['id' => $lsDefItemType->getId()]);
            } catch (\Exception $e) {
                $form->addError(new FormError('Error adding item type: '.$e->getMessage()));
            }
        }

        return $this->render('framework/ls_def_item_type/new.html.twig', [
            'lsDefItemType' => $lsDefItemType,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Finds and displays a LsDefItemType entity.
     */
    #[Route(path: '/{id}', name: 'lsdef_item_type_show', methods: ['GET'])]
    public function show(LsDefItemType $lsDefItemType): Response
    {
        $deleteForm = $this->createDeleteForm($lsDefItemType);

        return $this->render('framework/ls_def_item_type/show.html.twig', [
            'lsDefItemType' => $lsDefItemType,
            'delete_form' => $deleteForm->createView(),
        ]);
    }

    /**
     * Displays a form to edit an existing LsDefItemType entity.
     */
    #[Route(path: '/{id}/edit', name: 'lsdef_item_type_edit', methods: ['GET', 'POST'])]
    #[IsGranted(Permission::FRAMEWORK_CREATE)]
    public function edit(Request $request, LsDefItemType $lsDefItemType): Response
    {
        $deleteForm = $this->createDeleteForm($lsDefItemType);
        $editForm = $this->createForm(LsDefItemTypeType::class, $lsDefItemType);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            try {
                $command = new UpdateItemTypeCommand($lsDefItemType);
                $this->sendCommand($command);

                return $this->redirectToRoute('lsdef_item_type_edit', ['id' => $lsDefItemType->getId()]);
            } catch (\Exception $e) {
                $editForm->addError(new FormError('Error updating concept: '.$e->getMessage()));
            }
        }

        return $this->render('framework/ls_def_item_type/edit.html.twig', [
            'lsDefItemType' => $lsDefItemType,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ]);
    }

    /**
     * Deletes a LsDefItemType entity.
     */
    #[Route(path: '/{id}', name: 'lsdef_item_type_delete', methods: ['DELETE'])]
    #[IsGranted(Permission::FRAMEWORK_CREATE)]
    public function delete(Request $request, LsDefItemType $lsDefItemType): RedirectResponse
    {
        $form = $this->createDeleteForm($lsDefItemType);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $command = new DeleteItemTypeCommand($lsDefItemType);
            $this->sendCommand($command);
        }

        return $this->redirectToRoute('lsdef_item_type_index');
    }

    private function createDeleteForm(LsDefItemType $lsDefItemType): FormInterface
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('lsdef_item_type_delete', ['id' => $lsDefItemType->getId()]))
            ->setMethod(Request::METHOD_DELETE)
            ->getForm()
        ;
    }
}
