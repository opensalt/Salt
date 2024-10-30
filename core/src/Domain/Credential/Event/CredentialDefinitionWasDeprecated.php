<?php

namespace App\Domain\Credential\Event;

use Ecotone\Modelling\Attribute\NamedEvent;
use Symfony\Component\Uid\Uuid;

#[NamedEvent(self::NAME)]
final readonly class CredentialDefinitionWasDeprecated
{
    public const string NAME = 'credential_definition.was_deprecated';

    public function __construct(
        public Uuid $id,
        public Uuid $versionId,
    ) {
    }
}
