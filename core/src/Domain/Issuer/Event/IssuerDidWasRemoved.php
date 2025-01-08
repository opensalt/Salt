<?php

namespace App\Domain\Issuer\Event;

use Ecotone\Modelling\Attribute\NamedEvent;
use Symfony\Component\Uid\Uuid;

#[NamedEvent(self::NAME)]
final readonly class IssuerDidWasRemoved
{
    public const string NAME = 'issuer.issuer_did_removed';

    public function __construct(
        public Uuid $id,
        public string $did,
    ) {
    }
}