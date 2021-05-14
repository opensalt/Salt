<?php

namespace App\Serializer\CaseJson;

use App\Entity\Framework\LsDefItemType;
use App\Service\Api1Uris;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;

final class LsDefItemTypeNormalizer implements ContextAwareNormalizerInterface
{
    use LastChangeDateTimeTrait;

    public function __construct(
        private Api1Uris $api1Uris,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function supportsNormalization($data, string $format = null, array $context = [])
    {
        return $data instanceof LsDefItemType;
    }

    /**
     * @inheritDoc
     */
    public function normalize($object, string $format = null, array $context = [])
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
            'id' => (null !== $jsonLd)
                ? $this->api1Uris->getUri($object)
                : null,
            'type' => (null !== $jsonLd)
                ? 'CFItemType'
                : null,
            'identifier' => $object->getIdentifier(),
            'uri' => $this->api1Uris->getUri($object),
            'title' => $object->getTitle(),
            'lastChangeDateTime' => $this->getLastChangeDateTime($object),
            'description' => $object->getDescription(),
            'typeCode' => $object->getCode(),
            'hierarchyCode' => $object->getHierarchyCode(),
        ];

        return array_filter($data, static function ($val) {
            return null !== $val;
        });
    }
}
