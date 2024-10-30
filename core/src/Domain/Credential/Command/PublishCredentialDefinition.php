<?php

namespace App\Domain\Credential\Command;

use Ecotone\Modelling\Attribute\TargetIdentifier;
use Symfony\Component\Uid\Uuid;

final readonly class PublishCredentialDefinition
{
    public function __construct(
        #[TargetIdentifier]
        public Uuid $id,
        public Uuid $versionId,
    ) {
    }
}
