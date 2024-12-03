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

    public function normalize(mixed $data, ?string $format = null, array $context = []): ?array
    {
        if (!$data instanceof CfRubricCriterion) {
            return null;
        }

        $jsonLd = $context['case-json-ld'] ?? null;
        $addContext = (null !== $jsonLd) ? ($context['add-case-context'] ?? null) : null;
        $addType = (null === $addContext) ? ($context['add-case-type'] ?? null) : $addContext;
        $addCriterionLevels = !($context['no-sub-items'] ?? false);
        if (null !== ($context['add-case-context'] ?? null)) {
            unset($context['add-case-context']);
        }
        $return = [
            '@context' => (null !== $addContext)
                ? 'https://purl.imsglobal.org/spec/case/v1p0/context/imscasev1p0_context_v1p0.jsonld'
                : null,
            'type' => (null !== $addType)
                ? 'CFRubricCriterion'
                : null,
            'identifier' => $data->getIdentifier(),
            'uri' => $this->api1Uris->getUri($data),
            'lastChangeDateTime' => $this->getLastChangeDateTime($data),
            'CFItemURI' => $this->createLinkUri($data->getItem(), $context),
            'rubricId' => in_array('CfRubricCriterion', $context['groups'] ?? [], true)
                ? $data->getRubric()?->getIdentifier()
                : null,
            'category' => $data->getCategory(),
            'description' => $data->getDescription(),
            'position' => $data->getPosition(),
            'weight' => $data->getWeight(),
        ];

        if ($addCriterionLevels) {
            foreach ($data->getLevels() as $level) {
                $return['CFRubricCriterionLevels'][] = $this->normalizer->normalize($level, $format, $context);
            }
        }

        if (in_array('opensalt', $context['groups'] ?? [], true)) {
            $return['_opensalt'] = $data->getExtra();
        }

        return Collection::removeEmptyElements($return);
    }
}
