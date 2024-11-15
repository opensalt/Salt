<?php

namespace App\Domain\Issuer\Event;

use Ecotone\Modelling\Attribute\NamedEvent;
use Symfony\Component\Uid\Uuid;

#[NamedEvent(self::NAME)]
final readonly class IssuerWasAdded
{
    public const string NAME = 'issuer.issuer_added';

    public function __construct(
        public Uuid $id,
        public string $name,
        public ?string $did = null,
        public ?string $contact = null,
        public ?string $notes = null,
        public ?bool $trusted = null
    ) {
    }
}
