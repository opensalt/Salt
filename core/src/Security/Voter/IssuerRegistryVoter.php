<?php

namespace App\Security\Voter;

use App\Domain\Issuer\Issuer;
use App\Security\Permission;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Issuer|null>
 */
class IssuerRegistryVoter extends Voter
{
    use RoleCheckTrait;

    final public const string CREATE = Permission::ISSUER_REGISTRY_ADD;
    final public const string LIST = Permission::ISSUER_REGISTRY_LIST; // User can see the credential in a list
    final public const string VIEW = Permission::ISSUER_REGISTRY_VIEW;
    final public const string EDIT = Permission::ISSUER_REGISTRY_EDIT;
    final public const string DELETE = Permission::ISSUER_REGISTRY_DELETE;

    public function supportsAttribute(string $attribute): bool
    {
        return \in_array($attribute, [static::LIST, static::VIEW, static::CREATE, static::EDIT, static::DELETE], true);
    }

    public function supportsType(string $subjectType): bool
    {
        if ('null' === $subjectType) {
            return true;
        }

        return is_a($subjectType, Issuer::class, true);
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return match ($attribute) {
            self::CREATE, self::LIST => null === $subject,
            self::VIEW, self::EDIT, self::DELETE => $subject instanceof Issuer,
            default => false,
        };
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        return match ($attribute) {
            self::CREATE => $this->canAddIssuer($token),
            self::LIST => $this->canListIssuer($token),
            self::VIEW => $this->canViewIssuer($subject, $token),
            self::EDIT => $this->canEditIssuer($subject, $token),
            self::DELETE => $this->canDeleteIssuer($subject, $token),
            default => false,
        };
    }

    private function canAddIssuer(TokenInterface $token): bool
    {
        if ($this->roleChecker->isSuperEditor($token)) {
            return true;
        }

        return false;
    }

    private function canListIssuer(TokenInterface $token): bool
    {
        return true;
    }

    private function canViewIssuer(Issuer $subject, TokenInterface $token): bool
    {
        // Anyone can view an issuer if they know about it
        return true;
    }

    private function canEditIssuer(Issuer $subject, TokenInterface $token): bool
    {
        return $this->canAddIssuer($token);
    }

    private function canDeleteIssuer(Issuer $subject, TokenInterface $token): bool
    {
        return $this->canEditIssuer($subject, $token);
    }
}
