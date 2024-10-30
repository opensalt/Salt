<?php

declare(strict_types=1);

namespace App\Domain\Credential\Event;

use Ecotone\Modelling\Attribute\NamedEvent;
use Symfony\Component\Uid\Uuid;

#[NamedEvent(self::NAME)]
final readonly class DefinitionHierarchyWasChanged
{
    public const string NAME = 'credential_definition.hierarchy_changed';

    public function __construct(
        public Uuid $id,
        public string $hierarchyParent,
    ) {
    }
}
