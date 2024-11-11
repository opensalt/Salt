<?php

namespace App\Domain\FrontMatter\DTO;

use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Validator\Constraints as Assert;

class FrontMatterDto
{
    public ?UuidInterface $id = null;

    #[Assert\NotNull(message: 'The filename must be supplied')]
    public ?string $filename = null;

    #[Assert\NotNull(message: 'Source content must be supplied')]
    public ?string $source = null;
}
