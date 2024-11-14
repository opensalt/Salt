<?php

namespace App\Controller\Framework;

use App\Command\CommandDispatcherTrait;
use App\Command\Framework\AddDocumentCommand;
use App\Command\Framework\DeleteDocumentCommand;
use App\Command\Framework\DeriveDocumentCommand;
use App\Command\Framework\LockDocumentCommand;
use App\Command\Framework\UpdateDocumentCommand;
use App\Command\Framework\UpdateFrameworkCommand;
use App\Entity\Framework\LsDoc;
use App\Entity\User\User;
use App\Exception\AlreadyLockedException;
use App\Form\Type\LsDocCreateType;
use App\Form\Type\LsDocType;
use App\Form\Type\RemoteCaseServerType;
use App\Security\Permission;
use Doctrine\Persistence\ManagerRegistry;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/cfdoc')]
class LsDocController extends AbstractController
{
    use CommandDispatcherTrait;

    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
    ) {
    }

    /**
     * Lists all LsDoc entities.
     */
    #[Route(path: '/', name: 'lsdoc_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('framework/ls_doc/index.html.twig');
    }

    /**
     * Show frameworks from a remote system.
     */
    #[Route(path: '/remote', name: 'lsdoc_remote_index', methods: ['GET', 'POST'])]
    public function remoteIndex(Request $request): Response
    {
        $form = $this->createForm(RemoteCaseServerType::class);
        $form->handleRequest($request);

        $docs = null;
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $docs = $this->loadDocumentListFromHost($form->getData()['hostname']);
            } catch (\Exception $e) {
                $form->get('hostname')->addError(new FormError($e->getMessage()));
            }
        }

        return $this->render('framework/ls_doc/remote_index.html.twig', [
            'form' => $form->createView(),
            'docs' => $docs,
        ]);
    }

    protected function loadDocumentsFromServer(string $urlPrefix): ResponseInterface
    {
        $jsonClient = new Client();
        $list = $jsonClient->request(
            'GET',
            $urlPrefix.'/ims/case/v1p0/CFDocuments',
            [
                'timeout' => 60,
                'headers' => [
                    'Accept' => 'application/vnd.opensalt+json, application/json;q=0.8',
                ],
            ]
        );

        return $list;
    }

    /**
     * Creates a new LsDoc entity.
     */
    #[Route(path: '/new', name: 'lsdoc_new', methods: ['GET', 'POST'])]
    #[IsGranted(Permission::FRAMEWORK_CREATE)]
    public function new(Request $request): Response
    {
        $lsDoc = new LsDoc();
        $form = $this->createForm(LsDocCreateType::class, $lsDoc);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $command = new AddDocumentCommand($lsDoc);
                $this->sendCommand($command);

                return $this->redirectToRoute(
                    'doc_tree_view',
                    ['slug' => $lsDoc->getSlug()]
                );
            } catch (\Exception $e) {
                $form->addError(new FormError('Error adding new document: '.$e->getMessage()));
            }
        }

        return $this->render('framework/ls_doc/new.html.twig', [
            'lsDoc' => $lsDoc,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Finds and displays a LsDoc entity.
     */
    #[Route(path: '/{id}.{_format}', name: 'lsdoc_show', defaults: ['_format' => 'html'], methods: ['GET'])]
    #[IsGranted(Permission::FRAMEWORK_VIEW, 'lsDoc')]
    public function show(LsDoc $lsDoc, string $_format = 'html'): Response
    {
        if ('json' === $_format) {
            // Redirect?  Change Action for Template?
            return $this->render('framework/ls_doc/show.json.twig', [
                'lsDoc' => $lsDoc,
            ]);
        }

        $deleteForm = $this->createDeleteForm($lsDoc);

        return $this->render('framework/ls_doc/show.html.twig', [
            'lsDoc' => $lsDoc,
            'delete_form' => $deleteForm->createView(),
        ]);
    }

    /**
     * Update a framework given a CSV or external File.
     *
     * @deprecated It appears this is unused now
     */
    #[Route(path: '/doc/{id}/update', name: 'lsdoc_update', methods: ['POST'])]
    #[IsGranted(Permission::FRAMEWORK_EDIT, 'lsDoc')]
    public function update(Request $request, LsDoc $lsDoc): Response
    {
        $response = new JsonResponse();
        $fileContent = $request->request->get('content');
        /** @var array $cfItemKeys - cfItemKeys is an array argument */
        $cfItemKeys = $request->request->all('cfItemKeys');
        $frameworkToAssociate = $request->request->get('frameworkToAssociate');

        $command = new UpdateFrameworkCommand($lsDoc, base64_decode($fileContent), $frameworkToAssociate, $cfItemKeys);
        $this->sendCommand($command);

        return $response->setData([
            'message' => 'Success',
        ]);
    }

    /**
     * Update a framework given a CSV or external File on a derivative framework.
     */
    #[Route(path: '/doc/{id}/derive', name: 'lsdoc_update_derive', methods: ['POST'])]
    #[IsGranted(Permission::FRAMEWORK_CREATE)]
    public function derive(Request $request, LsDoc $lsDoc): Response
    {
        $fileContent = $request->request->get('content');
        $frameworkToAssociate = $request->request->get('frameworkToAssociate');

        $command = new DeriveDocumentCommand($lsDoc, base64_decode($fileContent), $frameworkToAssociate);
        $this->sendCommand($command);
        $derivedDoc = $command->getDerivedDoc();

        return new JsonResponse([
            'message' => 'Success',
            'new_doc_id' => $derivedDoc->getId(),
        ]);
    }

    /**
     * Displays a form to edit an existing LsDoc entity.
     */
    #[Route(path: '/{id}/edit', name: 'lsdoc_edit', methods: ['GET', 'POST'])]
    #[IsGranted(Permission::FRAMEWORK_EDIT, 'lsDoc')]
    public function edit(Request $request, LsDoc $lsDoc, #[CurrentUser] User $user): Response
    {
        $ajax = $request->isXmlHttpRequest();

        try {
            $command = new LockDocumentCommand($lsDoc, $user);
            $this->sendCommand($command);
        } catch (AlreadyLockedException $e) {
            return $this->render(
                'framework/ls_doc/locked.html.twig',
                []
            );
        }

        $deleteForm = $this->createDeleteForm($lsDoc);
        $editForm = $this->createForm(
            LsDocType::class,
            $lsDoc,
            ['ajax' => $ajax]
        );
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            try {
                $command = new UpdateDocumentCommand($lsDoc);
                $this->sendCommand($command);

                if ($ajax) {
                    return new Response('OK', Response::HTTP_ACCEPTED);
                }

                return $this->redirectToRoute(
                    'lsdoc_edit',
                    ['id' => $lsDoc->getId()]
                );
            } catch (\Exception $e) {
                $editForm->addError(new FormError('Error upating new document: '.$e->getMessage()));
            }
        }

        $ret = [
            'lsDoc' => $lsDoc,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ];

        if ($ajax && $editForm->isSubmitted() && !$editForm->isValid()) {
            return $this->render(
                'framework/ls_doc/edit.html.twig',
                $ret,
                new Response('', Response::HTTP_UNPROCESSABLE_ENTITY)
            );
        }

        return $this->render('framework/ls_doc/edit.html.twig', $ret);
    }

    /**
     * Deletes a LsDoc entity.
     */
    #[Route(path: '/{id}', name: 'lsdoc_delete', methods: ['DELETE'])]
    #[IsGranted(Permission::FRAMEWORK_DELETE, 'lsDoc')]
    public function delete(Request $request, LsDoc $lsDoc): Response
    {
        if ($request->isXmlHttpRequest()) {
            $token = $request->request->get('token');
            if ($this->isCsrfTokenValid('DELETE '.$lsDoc->getId(), $token)) {
                try {
                    $this->deleteFramework($lsDoc);

                    return new JsonResponse('OK');
                } catch (\Exception) {
                    return new JsonResponse(['error' => ['message' => 'Error deleting framework']], Response::HTTP_BAD_REQUEST);
                }
            }

            return new JsonResponse(['error' => ['message' => 'CSRF token invalid']], Response::HTTP_BAD_REQUEST);
        }

        $form = $this->createDeleteForm($lsDoc);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->deleteFramework($lsDoc);
        }

        return $this->redirectToRoute('lsdoc_index');
    }

    /**
     * Finds and displays a LsDoc entity.
     */
    #[Route(path: '/{id}/export.{_format}', name: 'lsdoc_export', requirements: ['_format' => '(json|html|null)'], defaults: ['_format' => 'json'], methods: ['GET'])]
    #[IsGranted(Permission::FRAMEWORK_VIEW, 'lsDoc')]
    public function export(LsDoc $lsDoc, string $_format = 'json'): Response
    {
        if ('json' !== $_format) {
            $_format = 'html';
        }

        $items = $this->managerRegistry
            ->getRepository(LsDoc::class)
            ->findAllChildrenArray($lsDoc);

        return $this->render('framework/ls_doc/export.'.$_format.'.twig', [
            'lsDoc' => $lsDoc,
            'items' => $items,
        ]);
    }

    /**
     * Load the document list from a remote host.
     *
     * @throws \Exception
     */
    protected function loadDocumentListFromHost(string $hostname): ?array
    {
        // Remove any scheme or path from the passed value
        $hostname = preg_replace('#^(?:https?://)?([^/]+).*#', '$1', $hostname);

        try {
            $remoteResponse = $this->loadDocumentsFromServer(
                'https://'.$hostname
            );
        } catch (\Exception) {
            try {
                $remoteResponse = $this->loadDocumentsFromServer(
                    'http://'.$hostname
                );
            } catch (\Exception) {
                throw new \Exception("Could not access CASE API on {$hostname}.");
            }
        }

        try {
            $docJson = $remoteResponse->getBody()->getContents();
            $docs = json_decode($docJson, true, 512, JSON_THROW_ON_ERROR);
            $docs = $docs['CFDocuments'];
            foreach ($docs as $key => $doc) {
                if (empty($doc['creator'])) {
                    $docs[$key]['creator'] = 'Unknown';
                }
                if (empty($doc['title'])) {
                    $docs[$key]['title'] = 'Unknown';
                }
            }
            usort(
                $docs,
                function ($a, $b) {
                    if ($a['creator'] !== $b['creator']) {
                        return $a['creator'] <=> $b['creator'];
                    }

                    return $a['title'] <=> $b['title'];
                }
            );
        } catch (\Exception) {
            $docs = null;
        }

        return $docs;
    }

    protected function deleteFramework(LsDoc $lsDoc): void
    {
        $command = new DeleteDocumentCommand($lsDoc);
        $this->sendCommand($command);
    }

    /**
     * Creates a form to delete a LsDoc entity.
     */
    private function createDeleteForm(LsDoc $lsDoc): FormInterface
    {
        return $this->createFormBuilder()
            ->setAction(
                $this->generateUrl(
                    'lsdoc_delete',
                    ['id' => $lsDoc->getId()]
                )
            )
            ->setMethod(Request::METHOD_DELETE)
            ->getForm();
    }
}
