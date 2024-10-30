<?php

namespace App\Domain\Credential\Event;

use Ecotone\Modelling\Attribute\NamedEvent;
use Ecotone\Modelling\Attribute\TargetIdentifier;
use Symfony\Component\Uid\Uuid;

#[NamedEvent(self::NAME)]
final readonly class NewCredentialDefinitionVersionWasCreated
{
    public const string NAME = 'credential_definition.new_version';

    public function __construct(
        #[TargetIdentifier]
        public Uuid $id,
        public Uuid $versionId,
        public string $hierarchyParent,
        public int $organization,
        public string $content,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
