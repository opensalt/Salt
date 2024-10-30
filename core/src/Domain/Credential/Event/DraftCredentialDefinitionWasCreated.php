<?php

declare(strict_types=1);

namespace App\Domain\Credential\Event;

use Ecotone\Modelling\Attribute\NamedEvent;
use Symfony\Component\Uid\Uuid;

#[NamedEvent(self::NAME)]
final readonly class DraftCredentialDefinitionWasCreated
{
    public const string NAME = 'credential_definition.draft_created';

    public function __construct(
        public string $hierarchyParent,
        public int $organization,
        public string $content,
        public Uuid $id,
        public Uuid $versionId,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
