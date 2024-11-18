<?php

namespace App\Domain\Credential;

use Ecotone\Modelling\Attribute\Repository;
use Symfony\Component\Uid\Uuid;

interface CredentialDefinitionRepository
{
    #[Repository]
    public function findBy(Uuid $id): CredentialDefinition;
}