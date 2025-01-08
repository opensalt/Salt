<?php

namespace App\Domain\Issuer\Command;

use Symfony\Component\Uid\Uuid;

final readonly class AddIssuerCommand
{
    public ?Uuid $id;

    public function __construct(
        public string $name,
        public ?string $did = null,
        public ?string $contact = null,
        public ?string $notes = null,
        public ?string $orgType = null,
        public ?bool $trusted = null,
        null|Uuid|string $id = null,
    ) {
        if ($id instanceof Uuid) {
            $this->id = $id;
        } elseif (is_string($id) && Uuid::isValid($id)) {
            $this->id = Uuid::fromString($id);
        } else {
            $this->id = null;
        }
    }
}
