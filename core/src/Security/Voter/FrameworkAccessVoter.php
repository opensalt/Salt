<?php

namespace App\Security\Voter;

use App\Entity\Framework\LsDoc;
use App\Entity\User\User;
use App\Entity\User\UserDocAcl;
use App\Security\Permission;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, LsDoc|null>
 */
class FrameworkAccessVoter extends Voter
{
    use RoleCheckTrait;

    final public const LIST = Permission::FRAMEWORK_LIST; // User can see the framework in a list
    final public const VIEW = Permission::FRAMEWORK_VIEW;
    final public const EDIT = Permission::FRAMEWORK_EDIT;
    final public const EDIT_ALL = Permission::FRAMEWORK_EDIT_ALL;
    final public const DELETE = Permission::FRAMEWORK_DELETE;
    final public const CREATE = Permission::FRAMEWORK_CREATE;
    final public const DOWNLOAD_EXCEL = Permission::FRAMEWORK_DOWNLOAD_EXCEL;

    public function supportsAttribute(string $attribute): bool
    {
        return \in_array($attribute, [static::LIST, static::VIEW, static::CREATE, static::EDIT, static::EDIT_ALL, static::DELETE, static::DOWNLOAD_EXCEL], true);
    }

    public function supportsType(string $subjectType): bool
    {
        if ('null' === $subjectType) {
            return true;
        }

        return is_a($subjectType, LsDoc::class, true);
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return match ($attribute) {
            self::CREATE, self::EDIT_ALL => null === $subject,
            self::LIST, self::VIEW, self::EDIT, self::DELETE, self::DOWNLOAD_EXCEL => $subject instanceof LsDoc,
            default => false,
        };
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        return match ($attribute) {
            self::CREATE => $this->canCreateFramework($token),
            self::LIST => $this->canListFramework($subject, $token),
            self::VIEW => $this->canViewFramework($subject, $token),
            self::EDIT => $this->canEditFramework($subject, $token),
            self::EDIT_ALL => $this->canEditAllFrameworks($token),
            self::DELETE => $this->canDeleteFramework($subject, $token),
            self::DOWNLOAD_EXCEL => $this->canDownloadExcelFramework($subject, $token),
            default => false,
        };
    }

    private function canCreateFramework(TokenInterface $token): bool
    {
        if ($this->roleChecker->isEditor($token)) {
            return true;
        }

        return false;
    }

    private function canListFramework(LsDoc $subject, TokenInterface $token): bool
    {
        if (LsDoc::ADOPTION_STATUS_PRIVATE_DRAFT !== $subject->getAdoptionStatus()
            && (!$subject->isMirrored() || true === $subject->getMirroredFramework()?->isVisible())) {
            return true;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            // If the user is not logged in then they can't see private frameworks
            return false;
        }

        // Allow users to view private frameworks of their org
        if ($user->getOrg()->getId() === $subject->getOrg()?->getId()) {
            return true;
        }

        // Editors can see all mirrored frameworks in the list
        if ($subject->isMirrored()) {
            return $this->roleChecker->isEditor($token);
        }

        return $this->canEditFramework($subject, $token);
    }

    private function canViewFramework(LsDoc $subject, TokenInterface $token): bool
    {
        // Anyone can view a framework if they know about it
        return true;
    }

    private function canEditFramework(LsDoc $subject, TokenInterface $token): bool
    {
        // Do not allow editing if the framework is mirrored
        if ($subject->isMirrored()) {
            return false;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            // If the user is not logged in then deny access
            return false;
        }

        // Do not allow editing if the user is not an editor
        if (!$this->roleChecker->isEditor($token)) {
            return false;
        }

        // Allow editing if the user is a super-editor
        if ($this->roleChecker->isSuperEditor($token)) {
            return true;
        }

        if (!$subject instanceof LsDoc) {
            // If the subject is not a document then do not allow editing
            return false;
        }

        // Allow the owner to edit the framework
        if ($subject->getUser() === $user) {
            return true;
        }

        // Check for an explicit ACL (could be a DENY)
        $docAcls = $user->getDocAcls();
        foreach ($docAcls as $acl) {
            if ($acl->getLsDoc() === $subject) {
                return UserDocAcl::ALLOW === $acl->getAccess();
            }
        }

        // Lastly check if the user is in the same organization
        return $user->getOrg()->getId() === $subject->getOrg()?->getId();
    }

    private function canDeleteFramework(LsDoc $subject, TokenInterface $token): bool
    {
        return $this->canEditFramework($subject, $token);
    }

    private function canEditAllFrameworks(TokenInterface $token): bool
    {
        if ($this->roleChecker->isSuperEditor($token)) {
            return true;
        }

        return false;
    }

    private function canDownloadExcelFramework(mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            // If the user is not logged in then deny access to the Excel download
            return false;
        }

        return true;
    }
}
