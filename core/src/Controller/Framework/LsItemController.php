<?php

namespace App\Controller\Framework;

use App\Command\CommandDispatcherTrait;
use App\Command\Framework\AddItemCommand;
use App\Command\Framework\ChangeItemParentCommand;
use App\Command\Framework\CopyItemToDocCommand;
use App\Command\Framework\DeleteItemCommand;
use App\Command\Framework\LockItemCommand;
use App\Command\Framework\RemoveChildCommand;
use App\Command\Framework\UpdateItemCommand;
use App\DTO\ItemType\AssessmentDto;
use App\DTO\ItemType\CourseDto;
use App\DTO\ItemType\CredentialDto;
use App\DTO\ItemType\ItemTypeInterface;
use App\DTO\ItemType\JobDto;
use App\Entity\Framework\LsAssociation;
use App\Entity\Framework\LsDefAssociationGrouping;
use App\Entity\Framework\LsDoc;
use App\Entity\Framework\LsItem;
use App\Entity\User\User;
use App\Exception\AlreadyLockedException;
use App\Form\Command\ChangeLsItemParentCommand;
use App\Form\Command\CopyToLsDocCommand;
use App\Form\Type\LsDocListType;
use App\Form\Type\LsItemParentType;
use App\Form\Type\LsItemType;
use App\Security\Permission;
use App\Service\BucketService;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/cfitem')]
class LsItemController extends AbstractController
{
    use CommandDispatcherTrait;

    public function __construct(
        private readonly ?string $bucketProvider,
        private readonly ManagerRegistry $managerRegistry,
    ) {
    }

    #[Route(path: '/', name: 'lsitem_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('framework/ls_item/index.html.twig', []);
    }

    #[Route(path: '/new/{doc}/PARENT', name: 'lsitem_new_top', methods: ['GET', 'POST'])]
    #[Route(path: '/new/{doc}/{parent}', name: 'lsitem_new', methods: ['GET', 'POST'])]
    #[Route(path: '/new/{doc}/PARENT/{assocGroup}', name: 'lsitem_new_top_ag', methods: ['GET', 'POST'])]
    #[Route(path: '/new/{doc}/{parent}/{assocGroup}', name: 'lsitem_new_ag', methods: ['GET', 'POST'])]
    #[IsGranted(Permission::ITEM_ADD_TO, 'doc')]
    public function new(
        Request $request,
        #[MapEntity(id: 'doc')] LsDoc $doc,
        #[MapEntity(id: 'parent')] ?LsItem $parent = null,
        #[MapEntity(id: 'assocGroup')] ?LsDefAssociationGrouping $assocGroup = null,
        #[MapQueryParameter] ?string $itemType = null,
    ): Response {
        $ajax = $request->isXmlHttpRequest();

        $lsItem = new LsItem();

        $lsItem->setLsDoc($doc);
        $lsItem->setLsDocUri($doc->getUri());

        $form = $this->getNewItemForm($itemType, $lsItem, $request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->processValidNewItemForm($form, $lsItem);
                $command = new AddItemCommand($lsItem, $lsItem->getLsDoc(), $parent, $assocGroup);
                $this->sendCommand($command);

                // retrieve isChildOf assoc id for the new item
                $assoc = $this->managerRegistry->getRepository(LsAssociation::class)->findOneBy(['originLsItem' => $lsItem, 'type' => LsAssociation::CHILD_OF]);

                if ($ajax) {
                    // if ajax call, return the item as json
                    return $this->generateItemJsonResponse($lsItem, $assoc);
                }

                return $this->redirectToRoute('lsitem_show', ['id' => $lsItem->getId()]);
            } catch (\Exception $e) {
                $form->addError(new FormError('Error adding new item: '.$e->getMessage()));
            }
        }

        $response = null;
        if ($ajax && $form->isSubmitted()) {
            $response = new Response('', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->render('framework/ls_item/new.html.twig', [
            'form' => $form->createView(),
        ], $response);
    }

    #[Route(path: '/{id}.{_format}', name: 'lsitem_show', defaults: ['_format' => 'html'], methods: ['GET'])]
    public function show(LsItem $lsItem, string $_format = 'html'): Response
    {
        if ('json' === $_format) {
            // Redirect?  Change Action for Template?
            return $this->render('framework/ls_item/show.json.twig', [
                'lsItem' => $lsItem,
            ]);
        }

        $deleteForm = $this->createDeleteForm($lsItem);

        return $this->render('framework/ls_item/show.html.twig', [
            'lsItem' => $lsItem,
            'delete_form' => $deleteForm->createView(),
        ]);
    }

    #[Route(path: '/{id}/edit', name: 'lsitem_edit', methods: ['GET', 'POST'])]
    #[IsGranted(Permission::ITEM_EDIT, 'lsItem')]
    public function edit(Request $request, LsItem $lsItem, #[CurrentUser] User $user): Response
    {
        $ajax = $request->isXmlHttpRequest();

        try {
            $command = new LockItemCommand($lsItem, $user);
            $this->sendCommand($command);
        } catch (AlreadyLockedException $e) {
            return $this->render(
                'framework/ls_item/locked.html.twig',
                []
            );
        }

        $editForm = $this->getEditItemForm($lsItem, $request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            try {
                $this->processValidEditItemForm($lsItem, $editForm);
                $command = new UpdateItemCommand($lsItem);
                $this->sendCommand($command);

                if ($ajax) {
                    // if ajax call, return the item as json
                    return $this->generateItemJsonResponse($lsItem);
                }

                return $this->redirectToRoute('lsitem_edit', ['id' => $lsItem->getId()]);
            } catch (\Exception $e) {
                $editForm->addError(new FormError('Error updating item: '.$e->getMessage()));
            }
        }

        $deleteForm = $this->createDeleteForm($lsItem);

        $ret = [
            'lsItem' => $lsItem,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ];

        if ($ajax && $editForm->isSubmitted() && !$editForm->isValid()) {
            return $this->render('framework/ls_item/edit.html.twig', $ret, new Response('', Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        return $this->render('framework/ls_item/edit.html.twig', $ret);
    }

    #[Route(path: '/{id}', name: 'lsitem_delete', methods: ['DELETE'])]
    #[IsGranted(Permission::ITEM_EDIT, 'lsItem')]
    public function delete(Request $request, LsItem $lsItem): RedirectResponse
    {
        $form = $this->createDeleteForm($lsItem);
        $form->handleRequest($request);

        $hasChildren = $lsItem->getChildren();

        if ($form->isSubmitted() && $form->isValid() && $hasChildren->isEmpty()) {
            $command = new DeleteItemCommand($lsItem);
            $this->sendCommand($command);
        }

        return $this->redirectToRoute('lsitem_index');
    }

    private function createDeleteForm(LsItem $lsItem): FormInterface
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('lsitem_delete', ['id' => $lsItem->getId()]))
            ->setMethod(Request::METHOD_DELETE)
            ->getForm()
        ;
    }

    #[Route(path: '/{id}/export', name: 'lsitem_export', defaults: ['_format' => 'json'], methods: ['GET'])]
    public function export(LsItem $lsItem): Response
    {
        return $this->render('framework/ls_item/export.json.twig', [
            'lsItem' => $lsItem,
        ]);
    }

    #[Route(path: '/{id}/removeChild/{child}', name: 'lsitem_remove_child', methods: ['POST'])]
    #[IsGranted(Permission::ITEM_EDIT, 'lsItem')]
    public function removeChild(
        #[MapEntity(id: 'id')] LsItem $parent,
        #[MapEntity(id: 'child')] LsItem $child,
    ): Response {
        $command = new RemoveChildCommand($parent, $child);
        $this->sendCommand($command);

        return $this->render('framework/ls_item/remove_child.html.twig', []);
    }

    #[Route(path: '/{id}/copy', name: 'lsitem_copy_item', methods: ['GET', 'POST'])]
    #[IsGranted(Permission::ITEM_EDIT, 'lsItem')]
    public function copy(Request $request, LsItem $lsItem): Response
    {
        // Steps
        //  - Select LsDoc to copy to
        //  - Clone LsItem to selected LsDoc

        $ajax = $request->isXmlHttpRequest();

        $command = new CopyToLsDocCommand();
        $form = $this->createForm(LsDocListType::class, $command->convertToDTO($lsItem), ['ajax' => $ajax]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $copyCommand = new CopyItemToDocCommand($form->getData());
            $this->sendCommand($copyCommand);
            $newItem = $copyCommand->getNewItem();

            if ($ajax) {
                return new Response(
                    $this->generateUrl('doc_tree_item_view', ['id' => $newItem->getId()]),
                    Response::HTTP_CREATED,
                    [
                        'Location' => $this->generateUrl('doc_tree_item_view', ['id' => $newItem->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
                    ]
                );
            }

            return $this->redirectToRoute('lsitem_show', ['id' => $lsItem->getId()]);
        }

        $ret = [
            'form' => $form->createView(),
        ];

        if ($ajax && $form->isSubmitted() && !$form->isValid()) {
            return $this->render('framework/ls_item/copy.html.twig', $ret, new Response('', Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        return $this->render('framework/ls_item/copy.html.twig', $ret);
    }

    #[Route(path: '/{id}/parent', name: 'lsitem_change_parent', methods: ['GET', 'POST'])]
    #[IsGranted(Permission::ITEM_EDIT, 'lsItem')]
    public function changeParent(Request $request, LsItem $lsItem): Response
    {
        $ajax = $request->isXmlHttpRequest();

        $lsDoc = $lsItem->getLsDoc();

        $command = new ChangeLsItemParentCommand();
        $form = $this->createForm(LsItemParentType::class, $command->convertToDTO($lsItem), ['ajax' => $ajax, 'lsDoc' => $lsDoc]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $changeCommand = new ChangeItemParentCommand($form->getData());
            $this->sendCommand($changeCommand);

            if ($ajax) {
                return new Response($this->generateUrl('doc_tree_item_view', ['id' => $lsItem->getId()]), Response::HTTP_ACCEPTED);
            }

            return $this->redirectToRoute('lsitem_edit', ['id' => $lsItem->getId()]);
        }

        $ret = [
            'lsItem' => $lsItem,
            'lsDoc' => $lsDoc,
            'form' => $form->createView(),
        ];

        if ($ajax && $form->isSubmitted() && !$form->isValid()) {
            return $this->render('framework/ls_item/change_parent.html.twig', $ret, new Response('', Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        return $this->render('framework/ls_item/change_parent.html.twig', $ret);
    }

    #[Route(path: '/{id}/upload_attachment', name: 'lsitem_upload_attachment', methods: ['POST'])]
    #[IsGranted(Permission::ITEM_ADD_TO, 'doc')]
    public function uploadAttachment(Request $request, LsDoc $doc, BucketService $bucket): Response
    {
        if (!empty($this->bucketProvider)) {
            $file = $request->files->get('file');

            if (null !== $file && $file->isValid()) {
                $fileUrl = $bucket->uploadFile($file, 'items');

                return new JsonResponse(['filename' => $fileUrl]);
            }
        }

        return new Response(null, Response::HTTP_BAD_REQUEST);
    }

    private function generateItemJsonResponse(LsItem $item, ?LsAssociation $assoc = null): Response
    {
        $ret = [
            'id' => $item->getId(),
            'identifier' => $item->getIdentifier(),
            'uri' => $item->getUri(),
            'fullStatement' => $item->getFullStatement(),
            'humanCodingScheme' => $item->getHumanCodingScheme(),
            'listEnumInSource' => $item->getListEnumInSource(),
            'abbreviatedStatement' => $item->getAbbreviatedStatement(),
            'conceptKeywords' => $item->getConceptKeywordsString(),
            'conceptKeywordsUri' => $item->getConceptKeywordsUri(),
            'notes' => $item->getNotes(),
            'language' => $item->getLanguage(),
            'educationalAlignment' => $item->getEducationalAlignment(),
            'itemType' => $item->getItemType(),
            'changedAt' => $item->getChangedAt(),
            'extra' => $item->getExtra(),
            'assocData' => [],
        ];

        if (null !== $assoc) {
            $destItem = $assoc->getDestinationNodeIdentifier();

            if (null !== $destItem) {
                $ret['assocData'] = [
                    'assocDoc' => $assoc->getLsDocIdentifier(),
                    'assocId' => $assoc->getId(),
                    'identifier' => $assoc->getIdentifier(),
                    // 'groupId' => $assoc->getGroup()?->getId(),
                    'dest' => ['doc' => $assoc->getLsDocIdentifier(), 'item' => $destItem, 'uri' => $destItem],
                ];
                if ($assoc->getGroup()) {
                    $ret['assocData']['groupId'] = $assoc->getGroup()->getId();
                }
                if ($assoc->getSequenceNumber()) {
                    $ret['assocData']['seq'] = $assoc->getSequenceNumber();
                }
            }
        }

        $response = new Response($this->renderView('framework/doc_tree/export_item.json.twig', ['lsItem' => $ret]));
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Cache-Control', 'no-cache');

        return $response;
    }

    private function getNewItemForm(?string $itemType, LsItem $lsItem, Request $request): FormInterface
    {
        $args = [];
        switch ($itemType) {
            case 'job':
                $itemDto = JobDto::fromItem($lsItem);
                $formType = $itemDto::ITEM_TYPE_FORM;
                break;

            case 'course':
                $itemDto = CourseDto::fromItem($lsItem);
                $formType = $itemDto::ITEM_TYPE_FORM;
                break;

            case 'assessment':
                $itemDto = AssessmentDto::fromItem($lsItem);
                $formType = $itemDto::ITEM_TYPE_FORM;
                break;

            case 'credential':
                $itemDto = CredentialDto::fromItem($lsItem);
                $formType = $itemDto::ITEM_TYPE_FORM;
                break;

            default:
                $itemDto = $lsItem;
                $args = [
                    'ajax' => $request->isXmlHttpRequest(),
                ];
                $formType = LsItemType::class;
                break;
        }

        $form = $this->createForm($formType, $itemDto, $args);

        $form->handleRequest($request);

        return $form;
    }

    private function processValidNewItemForm(FormInterface $form, LsItem $lsItem): void
    {
        $this->processValidEditItemForm($lsItem, $form);
    }

    private function getEditItemForm(LsItem $lsItem, Request $request): FormInterface
    {
        $itemTypeIdentifier = $lsItem->getDiscriminator();

        $args = [];
        switch ($itemTypeIdentifier) {
            case LsItem::TYPES['job']:
                $itemDto = JobDto::fromItem($lsItem);
                $formType = $itemDto::ITEM_TYPE_FORM;
                break;

            case LsItem::TYPES['course']:
                $itemDto = CourseDto::fromItem($lsItem);
                $formType = $itemDto::ITEM_TYPE_FORM;
                break;

            case LsItem::TYPES['assessment']:
                $itemDto = AssessmentDto::fromItem($lsItem);
                $formType = $itemDto::ITEM_TYPE_FORM;
                break;

            case LsItem::TYPES['credential']:
                $itemDto = CredentialDto::fromItem($lsItem);
                $formType = $itemDto::ITEM_TYPE_FORM;
                break;

            default:
                $args = ['ajax' => $request->isXmlHttpRequest()];
                $itemDto = $lsItem;
                $formType = LsItemType::class;
                break;
        }

        $form = $this->createForm($formType, $itemDto, $args);

        $form->handleRequest($request);

        return $form;
    }

    private function processValidEditItemForm(LsItem $lsItem, FormInterface $form): void
    {
        $item = $form->getData();

        if ($item instanceof LsItem) {
            return;
        }

        if ($item instanceof ItemTypeInterface) {
            $lsItem->setDiscriminator($item::ITEM_TYPE_IDENTIFIER);
            $jobItem = $form->getData();
            $jobItem->applyToItem($lsItem);
        }
    }
}
