<?php

namespace App\Domain\Issuer\DTO;

class IssuerDto
{
    public ?string $id = null;
    public string $name;
    public ?string $did = null;
    public ?string $contact = null;
    public ?string $notes = null;
    public ?bool $trusted = null;
}
