<?php

declare(strict_types=1);

namespace App\Serializer;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/*
 * derived from https://github.com/gbprod/uuid-normalizer/blob/master/src/UuidDenormalizer.php as it does not support Symfony 7
 */
class RamseyUuidDenormalizer implements DenormalizerInterface
{
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): ?UuidInterface
    {
        if (null === $data) {
            return null;
        }

        if (!\is_string($data) || !Uuid::isValid($data)) {
            throw new UnexpectedValueException('Expected a valid Uuid.');
        }

        return Uuid::fromString($data);
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return Uuid::class === $type || UuidInterface::class === $type;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Uuid::class => true,
            UuidInterface::class => true,
        ];
    }
}
