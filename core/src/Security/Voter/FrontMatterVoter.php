<?php

namespace App\Security\Voter;

use App\Domain\FrontMatter\Entity\FrontMatter;
use App\Security\Permission;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, FrontMatter|null>
 */
class FrontMatterVoter extends Voter
{
    use RoleCheckTrait;

    final public const string LIST = Permission::FRONT_MATTER_LIST; // User can see the credential in a list
    final public const string VIEW = Permission::FRONT_MATTER_VIEW;
    final public const string EDIT = Permission::FRONT_MATTER_EDIT;
    final public const string EDIT_ALL = Permission::FRONT_MATTER_EDIT_ALL;
    final public const string DELETE = Permission::FRONT_MATTER_DELETE;
    final public const string CREATE = Permission::FRONT_MATTER_CREATE;

    public function supportsAttribute(string $attribute): bool
    {
        return \in_array($attribute, [static::LIST, static::VIEW, static::CREATE, static::EDIT, static::EDIT_ALL, static::DELETE], true);
    }

    public function supportsType(string $subjectType): bool
    {
        if ('null' === $subjectType) {
            return true;
        }

        return is_a($subjectType, FrontMatter::class, true);
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return match ($attribute) {
            self::CREATE, self::EDIT_ALL, self::LIST => null === $subject,
            self::VIEW, self::EDIT, self::DELETE => $subject instanceof FrontMatter,
            default => false,
        };
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        return match ($attribute) {
            self::CREATE => $this->canCreateFrontMatter($token),
            self::LIST => $this->canListFrontMatter($token),
            self::VIEW => $this->canViewFrontMatter($subject, $token),
            self::EDIT => $this->canEditFrontMatter($subject, $token),
            self::EDIT_ALL => $this->canEditAllFrontMatters($token),
            self::DELETE => $this->canDeleteFrontMatter($subject, $token),
            default => false,
        };
    }

    private function canCreateFrontMatter(TokenInterface $token): bool
    {
        if ($this->roleChecker->isSuperEditor($token)) {
            return true;
        }

        return false;
    }

    private function canListFrontMatter(TokenInterface $token): bool
    {
        return $this->canCreateFrontMatter($token);
    }

    private function canViewFrontMatter(FrontMatter $subject, TokenInterface $token): bool
    {
        // Anyone can view a framework if they know about it
        return $this->canCreateFrontMatter($token);
    }

    private function canEditFrontMatter(FrontMatter $subject, TokenInterface $token): bool
    {
        return $this->canCreateFrontMatter($token);
    }

    private function canDeleteFrontMatter(FrontMatter $subject, TokenInterface $token): bool
    {
        return $this->canEditFrontMatter($subject, $token);
    }

    private function canEditAllFrontMatters(TokenInterface $token): bool
    {
        if ($this->roleChecker->isSuperEditor($token)) {
            return true;
        }

        return false;
    }
}
