<?php

declare(strict_types=1);

namespace App\Domain\Converter;

use Ecotone\Messaging\Attribute\Converter;

final readonly class DateTimeConverter
{
    #[Converter]
    public function fromString(string $dateTime): \DateTimeImmutable
    {
        return new \DateTimeImmutable($dateTime);
    }

    #[Converter]
    public function toString(\DateTimeImmutable $dateTime): string
    {
        return $dateTime->format('Y-m-d\TH:i:sp');
    }
}
