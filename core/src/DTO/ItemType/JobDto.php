<?php

namespace App\DTO\ItemType;

use Symfony\Component\Validator\Constraints as Assert;

class JobDto
{
    public function __construct(
        #[Assert\NotBlank()]
        #[Assert\Length(max: 255)]
        public ?string $abbreviatedStatement = null,
        #[Assert\NotBlank()]
        public ?string $fullStatement = null,
        public ?string $webpage = null,
    ) {
    }
}
