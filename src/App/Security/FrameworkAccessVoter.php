<?php

namespace App\Security;

use CftfBundle\Entity\LsDoc;
use JMS\DiExtraBundle\Annotation as DI;
use Salt\UserBundle\Entity\User;
use Salt\UserBundle\Entity\UserDocAcl;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Class FrameworkEditVoter
 *
 * @DI\Service(public=false)
 * @DI\Tag("security.voter")
 */
class FrameworkAccessVoter extends Voter
{
    public const VIEW = 'view';
    public const EDIT = 'edit';
    public const DELETE = 'delete';
    public const CREATE = 'create';

    public const FRAMEWORK = 'lsdoc';

    /**
     * @var AccessDecisionManagerInterface
     */
    private $decisionManager;

    /**
     * SuperUserVoter constructor.
     *
     * @param \Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface $decisionManager
     *
     * @DI\InjectParams({
     *     "decisionManager" = @DI\Inject("security.access.decision_manager")
     * })
     */
    public function __construct(AccessDecisionManagerInterface $decisionManager)
    {
        $this->decisionManager = $decisionManager;
    }

    /**
     * Determines if the attribute and subject are supported by this voter.
     *
     * @param string $attribute An attribute
     * @param mixed $subject The subject to secure, e.g. an object the user wants to access or any other PHP type
     *
     * @return bool True if the attribute and subject are supported, false otherwise
     */
    protected function supports($attribute, $subject)
    {
        if (!\in_array($attribute, [static::VIEW, static::CREATE, static::EDIT, static::DELETE], true)) {
            return false;
        }

        // If the attribute is CREATE then we can handle if the subject is FRAMEWORK
        if (static::FRAMEWORK === $subject && static::CREATE === $attribute) {
            return true;
        }

        // For the other attributes, we can handle if the subject is a document
        if (!$subject instanceof LsDoc) {
            return false;
        }

        return true;
    }

    /**
     * Perform a single access check operation on a given attribute, subject and token.
     * It is safe to assume that $attribute and $subject already passed the "supports()" method check.
     *
     * @param string $attribute
     * @param mixed $subject
     * @param TokenInterface $token
     *
     * @return bool
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        switch ($attribute) {
            case self::CREATE:
                return (static::FRAMEWORK === $subject) && $this->canCreateFramework($token);
                break;

            case self::VIEW:
                return $this->canViewFramework($subject, $token);
                break;

            case self::EDIT:
                return $this->canEditFramework($subject, $token);
                break;

            case self::DELETE:
                return $this->canDeleteFramework($subject, $token);
                break;
        }

        return false;
    }

    private function canCreateFramework(TokenInterface $token)
    {
        if ($this->decisionManager->decide($token, ['ROLE_EDITOR'])) {
            return true;
        }

        return false;
    }

    private function canViewFramework(LsDoc $subject, TokenInterface $token)
    {
        if (LsDoc::ADOPTION_STATUS_PRIVATE_DRAFT !== $subject->getAdoptionStatus()) {
            return true;
        }

        return $this->canEditFramework($subject, $token);
    }

    private function canEditFramework(LsDoc $subject, TokenInterface $token)
    {
        // Allow editing if the user is a super-editor
        if ($this->decisionManager->decide($token, ['ROLE_SUPER_EDITOR'])) {
            return true;
        }

        // Do not allow editing if the user is not an editor
        if (!$this->decisionManager->decide($token, ['ROLE_EDITOR'])) {
            return false;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            // If the user is not logged in then deny access
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
        return $user->getOrg() === $subject->getOrg();
    }

    private function canDeleteFramework(LsDoc $subject, TokenInterface $token)
    {
        return $this->canEditFramework($subject, $token);
    }
}
