<?php

namespace App\Domain;

use App\Domain\Credential\CredentialDefinition;
use Ecotone\Modelling\Attribute\Repository;
use Symfony\Component\Uid\Uuid;

interface CredentialDefinitionRepository
{
    #[Repository]
    public function findBy(Uuid $id): CredentialDefinition;
}
