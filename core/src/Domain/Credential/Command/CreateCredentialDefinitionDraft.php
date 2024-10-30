<?php

namespace App\Domain\Credential\Command;

final readonly class CreateCredentialDefinitionDraft
{
    public function __construct(
        public string $hierarchyParent,
        public int $organization,
        public array $content,
    ) {
    }
}
