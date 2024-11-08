<?php

namespace App\Entity\User;

use App\Entity\Framework\LsDoc;
use App\Repository\User\UserDocAclRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserDocAclRepository::class)]
#[ORM\Table(name: 'salt_user_doc_acl')]
#[ORM\UniqueConstraint(name: 'uniq_acl_id', columns: ['doc_id', 'user_id'])]
class UserDocAcl
{
    final public const DENY = 0;
    final public const ALLOW = 1;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    protected ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, fetch: 'EAGER', inversedBy: 'docAcls')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    protected User $user;

    #[ORM\ManyToOne(targetEntity: LsDoc::class, fetch: 'EAGER', inversedBy: 'docAcls')]
    #[ORM\JoinColumn(name: 'doc_id', referencedColumnName: 'id', nullable: false)]
    protected LsDoc $lsDoc;

    #[ORM\Column(name: 'access', type: 'smallint', nullable: false)]
    protected int $access;

    /**
     * UserDocAcl constructor.
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(User $user, LsDoc $lsDoc, int $access)
    {
        if (!in_array($access, [self::DENY, self::ALLOW], true)) {
            throw new \InvalidArgumentException('Invalid value for "access".  Access can only be 0 or 1');
        }
        $this->user = $user;
        $this->lsDoc = $lsDoc;
        $this->access = $access;
    }

    public function getId(): int
    {
        return $this->id;
    }

    /**
     * The User that the ACL is for.
     */
    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * The Document that the ACL is for.
     */
    public function getLsDoc(): LsDoc
    {
        return $this->lsDoc;
    }

    /**
     * Determine if the user has access.
     *
     * @return int 0|1 indicating Deny or Allow
     */
    public function getAccess(): int
    {
        return $this->access;
    }
}
