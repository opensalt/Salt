<?php

namespace App\Controller\Mirror;

use App\DTO\Mirror\ServerListItem;
use App\Entity\Framework\LsDoc;
use App\Entity\Framework\Mirror\Framework;
use App\Entity\Framework\Mirror\Server;
use App\Form\DTO\MirroredServerDTO;
use App\Form\Type\MirroredServerDTOType;
use App\Repository\Framework\Mirror\ServerRepository;
use App\Security\Permission;
use App\Service\MirrorServer;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/admin/mirror/server')]
#[IsGranted(Permission::MANAGE_MIRRORS)]
class ServerController extends AbstractController
{
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
    ) {
    }

    #[Route(path: '/', name: 'mirror_server_index')]
    public function index(ServerRepository $serverRepository): Response
    {
        $servers = $serverRepository->findAllForList();
        $statuses = [];
        $deleteForms = [];
        foreach ($servers as $server) {
            $status = [
                Framework::STATUS_NEW => 0,
                Framework::STATUS_OK => 0,
                Framework::STATUS_PROCESSING => 0,
                Framework::STATUS_ERROR => 0,
                Framework::STATUS_SCHEDULED => 0,
                'not-included' => 0,
            ];

            foreach ($server->getFrameworks() as $framework) {
                if (!$framework->isInclude()) {
                    ++$status['not-included'];

                    continue;
                }

                ++$status[$framework->getStatus()];
            }

            $statuses[$server->getId()] = $status;
            $deleteForms[$server->getId()] = $this->createDeleteForm($server)->createView();
        }

        return $this->render('mirror/server/index.html.twig', [
            'servers' => $servers,
            'statuses' => $statuses,
            'deleteForms' => $deleteForms,
        ]);
    }

    #[Route(path: '/new', name: 'mirror_server_add')]
    public function new(Request $request, MirrorServer $mirrorService): Response
    {
        // Multiple steps
        // 1 - Add server information
        //   - Validate and add all mirrored frameworks (include set to auto value)
        // 2 - If not mirroring all frameworks, select frameworks to mirror
        $serverDto = new MirroredServerDTO();
        $form = $this->createForm(MirroredServerDTOType::class, $serverDto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $server = $mirrorService->addServer($serverDto);

                return $this->redirectToRoute('mirror_server_list', ['id' => $server->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('mirror/server/new.html.twig', [
            'mirrored_server' => $serverDto,
            'form' => $form->createView(),
        ]);
    }

    #[Route(path: '/{id}/edit', name: 'mirror_server_edit')]
    public function edit(Request $request, Server $server, MirrorServer $mirrorService): Response
    {
        $serverDto = new MirroredServerDTO();
        $serverDto->url = $server->getUrl();
        $serverDto->autoAddFoundFrameworks = $server->isAddFoundFrameworks();
        $serverDto->credentials = $server->getCredentials();
        $serverDto->status = $server->getStatus();
        $form = $this->createForm(MirroredServerDTOType::class, $serverDto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $server->setUrl($serverDto->url);
            $server->setCredentials($serverDto->credentials);
            $server->setAddFoundFrameworks($serverDto->autoAddFoundFrameworks);
            $server->setStatus($serverDto->status);
            if (Server::STATUS_SUSPENDED == $serverDto->status) {
                $server->setNextCheck(null);
            } else {
                $server->scheduleNextCheck();
            }

            $this->managerRegistry->getManager()->flush();

            if (Server::STATUS_ACTIVE === $serverDto->status) {
                $mirrorService->updateFrameworkList($server);
            }

            return $this->redirectToRoute('mirror_server_list', ['id' => $server->getId()]);
        }

        return $this->render('mirror/server/edit.html.twig', [
            'mirrored_server' => $serverDto,
            'form' => $form->createView(),
        ]);
    }

    #[Route(path: '/{id}/list', name: 'mirror_server_list')]
    public function list(Server $server): Response
    {
        $enableForms = [];
        $visibleForms = [];
        $disableForms = [];
        $refreshForms = [];
        $showLogs = [];

        foreach ($server->getFrameworks() as $framework) {
            if ($framework->hasLogs()) {
                $showLogs[$framework->getId()] = $this->generateUrl('mirror_framework_logs', ['id' => $framework->getId()]);
            }

            $visibleForm = $this->createVisibilityForm($framework);
            if (null !== $visibleForm) {
                $visibleForms[$framework->getId()] = $visibleForm->createView();
            }

            if (!$framework->isInclude()) {
                $enableForms[$framework->getId()] = $this->createEnableForm($framework)->createView();

                continue;
            }

            $disableForms[$framework->getId()] = $this->createDisableForm($framework)->createView();
            $refreshForms[$framework->getId()] = $this->createFrameworkRefreshForm($framework)->createView();
        }

        $serverRefreshForm = $this->createRefreshForm($server);

        return $this->render('mirror/server/list.html.twig', [
            'server' => $server,
            'serverRefreshForm' => $serverRefreshForm->createView(),
            'enableForms' => $enableForms,
            'visibleForms' => $visibleForms,
            'disableForms' => $disableForms,
            'refreshForms' => $refreshForms,
            'showLogs' => $showLogs,
        ]);
    }

    #[Route(path: '/{id}', name: 'mirror_server_delete', methods: ['GET', 'DELETE'])]
    public function remove(Request $request, Server $server): Response
    {
        $form = $this->createDeleteForm($server);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->managerRegistry->getManager();

            foreach ($server->getFrameworks() as $framework) {
                $doc = $framework->getFramework();
                if (null !== $doc) {
                    $doc->setMirroredFramework(null);
                }
                $em->remove($framework);
            }

            $em->remove($server);

            $em->flush();

            return $this->redirectToRoute('mirror_server_index');
        }

        return $this->render('mirror/server/delete.html.twig', [
            'server' => $server,
            'deleteForm' => $form->createView(),
        ]);
    }

    #[Route(path: '/{id}/refresh', name: 'mirror_server_refresh', methods: ['POST'])]
    public function refresh(Server $server, MirrorServer $mirrorServer): Response
    {
        $mirrorServer->updateFrameworkList($server);

        return $this->redirectToRoute('mirror_server_list', ['id' => $server->getId()]);
    }

    private function createDeleteForm(Server|ServerListItem $server): FormInterface
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('mirror_server_delete', ['id' => $server->getId()]))
            ->setMethod(Request::METHOD_DELETE)
            ->getForm()
        ;
    }

    private function createEnableForm(Framework $framework): FormInterface
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('mirror_framework_enable', ['id' => $framework->getId()]))
            ->getForm()
        ;
    }

    private function createDisableForm(Framework $framework): FormInterface
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('mirror_framework_disable', ['id' => $framework->getId()]))
            ->getForm()
        ;
    }

    private function createRefreshForm(Server $server): FormInterface
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('mirror_server_refresh', ['id' => $server->getId()]))
            ->getForm()
        ;
    }

    private function createFrameworkRefreshForm(Framework $framework): FormInterface
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('mirror_framework_refresh', ['id' => $framework->getId()]))
            ->getForm()
        ;
    }

    private function createVisibilityForm(Framework $framework): ?FormInterface
    {
        $actualFramework = $framework->getFramework();
        if (null === $actualFramework || LsDoc::ADOPTION_STATUS_PRIVATE_DRAFT === $actualFramework->getAdoptionStatus()) {
            return null;
        }

        return $this->createFormBuilder()
            ->setAction($this->generateUrl('mirror_framework_'.($framework->isVisible() ? 'invisible' : 'visible'), ['id' => $framework->getId()]))
            ->getForm()
        ;
    }
}
