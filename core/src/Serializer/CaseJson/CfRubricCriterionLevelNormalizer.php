<?php

namespace App\Serializer\CaseJson;

use App\Entity\Framework\CfRubricCriterionLevel;
use App\Service\Api1Uris;
use App\Util\Collection;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class CfRubricCriterionLevelNormalizer implements NormalizerInterface
{
    use LastChangeDateTimeTrait;

    public function __construct(
        private readonly Api1Uris $api1Uris,
    ) {
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof CfRubricCriterionLevel;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [CfRubricCriterionLevel::class => true];
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): ?array
    {
        if (!$object instanceof CfRubricCriterionLevel) {
            return null;
        }

        $jsonLd = $context['case-json-ld'] ?? null;
        $addContext = (null !== $jsonLd) ? ($context['add-case-context'] ?? null) : null;
        $addType = (null === $addContext) ? ($context['add-case-type'] ?? null) : $addContext;
        $data = [
            '@context' => (null !== $addContext)
                ? 'https://purl.imsglobal.org/spec/case/v1p0/context/imscasev1p0_context_v1p0.jsonld'
                : null,
            'type' => (null !== $addType)
                ? 'CFRubricCriterionLevel'
                : null,
            'identifier' => $object->getIdentifier(),
            'uri' => $this->api1Uris->getUri($object),
            'rubricCriterionId' => in_array('CfRubricCriterionLevel', $context['groups'] ?? [], true)
                ? $object->getCriterion()->getIdentifier()
                : null,
            'lastChangeDateTime' => $this->getLastChangeDateTime($object),
            'description' => $object->getDescription(),
            'feedback' => $object->getFeedback(),
            'quality' => $object->getQuality(),
            'score' => $object->getScore(),
            'position' => $object->getPosition(),
        ];

        if (in_array('opensalt', $context['groups'] ?? [], true)) {
            $data['_opensalt'] = $object->getExtra();
        }

        return Collection::removeEmptyElements($data);
    }
}
