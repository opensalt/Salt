<?php

namespace App\Serializer\CaseJson;

use App\Entity\Framework\CfRubricCriterion;
use App\Service\Api1Uris;
use App\Util\Collection;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class CfRubricCriterionNormalizer implements NormalizerAwareInterface, NormalizerInterface
{
    use NormalizerAwareTrait;
    use LinkUriTrait;
    use LastChangeDateTimeTrait;

    public function __construct(
        private readonly Api1Uris $api1Uris,
    ) {
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof CfRubricCriterion;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [CfRubricCriterion::class => true];
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): ?array
    {
        if (!$object instanceof CfRubricCriterion) {
            return null;
        }

        $jsonLd = $context['case-json-ld'] ?? null;
        $addContext = (null !== $jsonLd) ? ($context['add-case-context'] ?? null) : null;
        $addType = (null === $addContext) ? ($context['add-case-type'] ?? null) : $addContext;
        $addCriterionLevels = !($context['no-sub-items'] ?? false);
        if (null !== ($context['add-case-context'] ?? null)) {
            unset($context['add-case-context']);
        }
        $data = [
            '@context' => (null !== $addContext)
                ? 'https://purl.imsglobal.org/spec/case/v1p0/context/imscasev1p0_context_v1p0.jsonld'
                : null,
            'type' => (null !== $addType)
                ? 'CFRubricCriterion'
                : null,
            'identifier' => $object->getIdentifier(),
            'uri' => $this->api1Uris->getUri($object),
            'lastChangeDateTime' => $this->getLastChangeDateTime($object),
            'CFItemURI' => $this->createLinkUri($object->getItem(), $context),
            'rubricId' => in_array('CfRubricCriterion', $context['groups'] ?? [], true)
                ? $object->getRubric()?->getIdentifier()
                : null,
            'category' => $object->getCategory(),
            'description' => $object->getDescription(),
            'position' => $object->getPosition(),
            'weight' => $object->getWeight(),
        ];

        if ($addCriterionLevels) {
            foreach ($object->getLevels() as $level) {
                $data['CFRubricCriterionLevels'][] = $this->normalizer->normalize($level, $format, $context);
            }
        }

        if (in_array('opensalt', $context['groups'] ?? [], true)) {
            $data['_opensalt'] = $object->getExtra();
        }

        return Collection::removeEmptyElements($data);
    }
}
