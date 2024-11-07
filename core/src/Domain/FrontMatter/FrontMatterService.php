<?php

namespace App\Domain\FrontMatter;

use App\Entity\FrontMatter\FrontMatter;
use Doctrine\ORM\EntityManagerInterface;
use Ecotone\Modelling\Attribute\CommandHandler;

readonly class FrontMatterService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    #[CommandHandler('deleteFrontMatter')]
    public function deleteFrontMatter(FrontMatter $template): void
    {
        $delete = $this->em->find(FrontMatter::class, $template->getId());
        $this->em->remove($delete);
        $this->em->flush();
    }
}
