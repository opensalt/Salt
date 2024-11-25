<?php

namespace App\Form\DTO;

use App\Entity\Framework\LsDoc;
use App\Entity\User\User;
use Symfony\Component\Validator\Constraints as Assert;

class AddAclUserDTO
{
    #[Assert\Type(User::class)]
    #[Assert\NotNull]
    public ?User $user = null;

    #[Assert\Type(LsDoc::class)]
    #[Assert\NotNull]
    public LsDoc $lsDoc;

    #[Assert\Type('int')]
    #[Assert\NotNull]
    public int $access;

    public function __construct(LsDoc $doc, int $access, ?User $user = null)
    {
        $this->lsDoc = $doc;
        $this->access = $access;
        $this->user = $user;
    }
}
