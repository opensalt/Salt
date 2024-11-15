<?php

namespace App\Domain\Issuer\Command;

use Symfony\Component\Uid\Uuid;

final readonly class UpdateIssuerCommand
{
    public ?Uuid $id;

    public function __construct(
        Uuid|string $id,
        public string $name,
        public ?string $did = null,
        public ?string $contact = null,
        public ?string $notes = null,
        public ?bool $trusted = null,
    ) {
        if ($id instanceof Uuid) {
            $this->id = $id;
        } elseif (Uuid::isValid($id)) {
            $this->id = Uuid::fromString($id);
        } else {
            throw new \InvalidArgumentException('Invalid issuer ID');
        }
    }
}
