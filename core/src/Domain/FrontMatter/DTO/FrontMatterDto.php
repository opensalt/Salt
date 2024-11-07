<?php

namespace App\Domain\FrontMatter\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class FrontMatterDto
{
    public ?int $id = null;

    #[Assert\NotNull(message: 'The filename must be supplied')]
    public ?string $filename = null;

    #[Assert\NotNull(message: 'Source content must be supplied')]
    public ?string $source = null;
}
