<?php

namespace App\Domain\Issuer;

use Ecotone\Modelling\Attribute\Repository;
use Symfony\Component\Uid\Uuid;

interface IssuerRepository
{
    #[Repository]
    public function findBy(Uuid $id): ?Issuer;
}
