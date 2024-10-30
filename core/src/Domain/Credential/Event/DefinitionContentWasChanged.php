<?php

declare(strict_types=1);

namespace App\Domain\Credential\Event;

use Ecotone\Modelling\Attribute\NamedEvent;
use Symfony\Component\Uid\Uuid;

#[NamedEvent(self::NAME)]
final readonly class DefinitionContentWasChanged
{
    public const string NAME = 'credential_definition.content_changed';

    public function __construct(
        public Uuid $id,
        public Uuid $versionId,
        public string $newContent,
    ) {
    }
}
