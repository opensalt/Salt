<?php

namespace App\Command\Framework;

use App\Command\BaseCommand;
use App\Entity\Framework\LsDefSubject;
use Symfony\Component\Validator\Constraints as Assert;

class DeleteSubjectCommand extends BaseCommand
{
    /**
     * @var LsDefSubject
     */
    #[Assert\Type(LsDefSubject::class)]
    #[Assert\NotNull]
    private $subject;

    public function __construct(LsDefSubject $subject)
    {
        $this->subject = $subject;
    }

    public function getSubject(): LsDefSubject
    {
        return $this->subject;
    }
}
