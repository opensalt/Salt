<?php declare(strict_types=1);

namespace App\Domain\Credential\Event;

use Ecotone\Modelling\Attribute\NamedEvent;
use Symfony\Component\Uid\Uuid;

#[NamedEvent(self::NAME)]
final readonly class DefinitionOrganizationWasChanged
{
    public const string NAME = 'credential_definition.organization_changed';

    public function __construct(
        public Uuid $id,
        public int $organization,
    ) {
    }
}
