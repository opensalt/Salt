<?php

declare(strict_types=1);

namespace App\Serializer;

use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/*
 * derived from https://github.com/gbprod/uuid-normalizer/blob/master/src/UuidNormalizer.php as it does not support Symfony 7
 */
class RamseyUuidNormalizer implements NormalizerInterface
{
    public function normalize(mixed $data, ?string $format = null, array $context = []): string
    {
        return $data->toString();
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof UuidInterface;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [UuidInterface::class => true];
    }
}
