<?php

namespace App\Serializer\CaseJson;

use App\Entity\Framework\LsDefItemType;
use App\Service\Api1Uris;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class LsDefItemTypeNormalizer implements NormalizerInterface
{
    use LastChangeDateTimeTrait;

    public function __construct(
        private readonly Api1Uris $api1Uris,
    ) {
    }

    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool
    {
        return $data instanceof LsDefItemType;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [LsDefItemType::class => true];
    }

    public function normalize(mixed $object, string $format = null, array $context = []): ?array
    {
        if (!$object instanceof LsDefItemType) {
            return null;
        }

        $jsonLd = $context['case-json-ld'] ?? null;
        $addContext = (null !== $jsonLd) ? ($context['add-case-context'] ?? null) : null;
        $data = [
            '@context' => (null !== $addContext)
                ? 'https://purl.imsglobal.org/spec/case/v1p0/context/imscasev1p0_context_v1p0.jsonld'
                : null,
            'type' => (null !== $addContext)
                ? 'CFItemType'
                : null,
            'identifier' => $object->getIdentifier(),
            'uri' => $this->api1Uris->getUri($object),
            'title' => $object->getTitle(),
            'lastChangeDateTime' => $this->getLastChangeDateTime($object),
            'description' => $object->getDescription() ?? $object->getTitle(),
            'typeCode' => $object->getCode(),
            'hierarchyCode' => $object->getHierarchyCode(),
        ];

        return array_filter($data, static fn ($val) => null !== $val);
    }
}
