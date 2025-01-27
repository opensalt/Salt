<?php

namespace App\Controller\User;

use App\Command\CommandDispatcherTrait;
use App\Command\User\AddFrameworkUserAclCommand;
use App\Command\User\AddFrameworkUsernameAclCommand;
use App\Command\User\DeleteFrameworkAclCommand;
use App\Entity\Framework\LsDoc;
use App\Entity\User\User;
use App\Entity\User\UserDocAcl;
use App\Form\DTO\AddAclUserDTO;
use App\Form\DTO\AddAclUsernameDTO;
use App\Form\Type\AddAclUsernameType;
use App\Form\Type\AddAclUserType;
use App\Security\Permission;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/cfdoc')]
class FrameworkAclController extends AbstractController
{
    use CommandDispatcherTrait;

    #[Route(path: '/{id}/acl', name: 'framework_acl_edit', methods: ['GET', 'POST'])]
    #[IsGranted(Permission::MANAGE_EDITORS, 'lsDoc')]
    public function edit(Request $request, LsDoc $lsDoc): Response
    {
        $addAclUserDto = new AddAclUserDTO($lsDoc, UserDocAcl::DENY);
        $addOrgUserForm = $this->createForm(AddAclUserType::class, $addAclUserDto, [
            'lsDoc' => $lsDoc,
            'action' => $this->generateUrl('framework_acl_edit', ['id' => $lsDoc->getId()]),
            'method' => 'POST',
        ]);
        $addAclUsernameDto = new AddAclUsernameDTO($lsDoc, UserDocAcl::ALLOW);
        $addUsernameForm = $this->createForm(AddAclUsernameType::class, $addAclUsernameDto);

        $addOrgUserForm->handleRequest($request);
        if ($ret = $this->handleOrgUserAdd($lsDoc, $addOrgUserForm)) {
            return $ret;
        }

        $addUsernameForm->handleRequest($request);
        if ($ret = $this->handleUsernameAdd($lsDoc, $addUsernameForm)) {
            return $ret;
        }

        $acls = $lsDoc->getDocAcls();
        /** @var \ArrayIterator $iterator */
        $iterator = $acls->getIterator();
        $iterator->uasort(fn (UserDocAcl $a, UserDocAcl $b) => strcasecmp($a->getUser()->getUserIdentifier(), $b->getUser()->getUserIdentifier()));
        /** @var array<array-key, UserDocAcl> $userArray */
        $userArray = iterator_to_array($iterator);
        $acls = match ($userArray) {
            [] => new ArrayCollection(),
            default => new ArrayCollection($userArray),
        };

        $deleteForms = [];
        foreach ($acls as $acl) {
            /** @var UserDocAcl $acl */
            $aclUser = $acl->getUser();
            $deleteForms[$aclUser->getId()] = $this->createDeleteForm($lsDoc, $aclUser)->createView();
        }

        $orgUsers = [];
        if ('organization' === $lsDoc->getOwnedBy()) {
            $orgUsers = $lsDoc->getOrg()->getUsers();
        }

        return $this->render('user/framework_acl/edit.html.twig', [
            'lsDoc' => $lsDoc,
            'aclCount' => $acls->count(),
            'acls' => $acls,
            'orgUsers' => $orgUsers,
            'addOrgUserForm' => $addOrgUserForm->createView(),
            'addUsernameForm' => $addUsernameForm->createView(),
            'deleteForms' => $deleteForms,
        ]);
    }

    private function handleOrgUserAdd(LsDoc $lsDoc, FormInterface $addOrgUserForm): ?RedirectResponse
    {
        if ($addOrgUserForm->isSubmitted() && $addOrgUserForm->isValid()) {
            $dto = $addOrgUserForm->getData();
            $command = new AddFrameworkUserAclCommand($dto);

            try {
                $this->sendCommand($command);

                return $this->redirectToRoute('framework_acl_edit', ['id' => $lsDoc->getId()]);
            } catch (UniqueConstraintViolationException $e) {
                $error = new FormError('The username is already in your exception list.');
                $error->setOrigin($addOrgUserForm);
                $addOrgUserForm->addError($error);
            } catch (\InvalidArgumentException $e) {
                $error = new FormError($e->getMessage());
                $error->setOrigin($addOrgUserForm);
                $addOrgUserForm->addError($error);
            } catch (\Exception) {
                $error = new FormError('Unknown Error');
                $error->setOrigin($addOrgUserForm);
                $addOrgUserForm->addError($error);
            }
        }

        return null;
    }

    private function handleUsernameAdd(LsDoc $lsDoc, FormInterface $addUsernameForm): ?RedirectResponse
    {
        if ($addUsernameForm->isSubmitted() && $addUsernameForm->isValid()) {
            $dto = $addUsernameForm->getData();
            $command = new AddFrameworkUsernameAclCommand($dto);

            try {
                $this->sendCommand($command);

                return $this->redirectToRoute('framework_acl_edit', ['id' => $lsDoc->getId()]);
            } catch (UniqueConstraintViolationException $e) {
                $error = new FormError('The username is already in your exception list.');
                $error->setOrigin($addUsernameForm);
                $addUsernameForm->addError($error);
            } catch (\InvalidArgumentException $e) {
                $error = new FormError($e->getMessage());
                $error->setOrigin($addUsernameForm);
                $addUsernameForm->addError($error);
            } catch (\Exception) {
                //$error = new FormError($e->getMessage().' '.get_class($e));
                $error = new FormError('Unknown Error');
                $error->setOrigin($addUsernameForm);
                $addUsernameForm->addError($error);
            }
        }

        return null;
    }

    #[Route(path: '/{id}/acl/{targetUser}', name: 'framework_acl_remove', methods: ['DELETE'])]
    #[IsGranted(Permission::MANAGE_EDITORS, 'lsDoc')]
    public function removeAcl(
        Request $request,
        LsDoc $lsDoc,
        #[MapEntity(id: 'targetUser')] User $targetUser,
    ): RedirectResponse {
        $form = $this->createDeleteForm($lsDoc, $targetUser);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $command = new DeleteFrameworkAclCommand($lsDoc, $targetUser);
            $this->sendCommand($command);
        }

        return $this->redirectToRoute('framework_acl_edit', ['id' => $lsDoc->getId()]);
    }

    /**
     * Creates a form to delete a user entity.
     */
    private function createDeleteForm(LsDoc $lsDoc, User $targetUser): FormInterface
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('framework_acl_remove', ['id' => $lsDoc->getId(), 'targetUser' => $targetUser->getId()]))
            ->setMethod(Request::METHOD_DELETE)
            ->getForm()
            ;
    }
}
