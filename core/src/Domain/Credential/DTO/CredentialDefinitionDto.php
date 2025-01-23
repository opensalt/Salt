<?php

namespace App\Domain\Credential\DTO;

use App\Entity\User\Organization;
use Symfony\Component\Validator\Constraints as Assert;

class CredentialDefinitionDto
{
    #[Assert\NotBlank(message: 'The collection must be provided.')]
    public ?string $hierarchyParent = null;

    public ?Organization $organization = null;

    public ?string $content = null;
}
