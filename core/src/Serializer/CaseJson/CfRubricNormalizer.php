<?php

namespace App\Serializer\CaseJson;

use App\Entity\Framework\CfRubric;
use App\Service\Api1Uris;
use App\Util\Collection;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class CfRubricNormalizer implements NormalizerAwareInterface, NormalizerInterface
{
    use NormalizerAwareTrait;
    use LastChangeDateTimeTrait;

    public function __construct(
        private readonly Api1Uris $api1Uris,
    ) {
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof CfRubric;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [CfRubric::class => true];
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): ?array
    {
        if (!$data instanceof CfRubric) {
            return null;
        }

        $jsonLd = $context['case-json-ld'] ?? null;
        $addContext = (null !== $jsonLd) ? ($context['add-case-context'] ?? null) : null;
        $addType = (null === $addContext) ? ($context['add-case-type'] ?? null) : $addContext;
        $addCriteria = !($context['no-sub-items'] ?? false);
        if (null !== ($context['add-case-context'] ?? null)) {
            unset($context['add-case-context']);
        }
        $ret = [
            '@context' => (null !== $addContext)
                ? 'https://purl.imsglobal.org/spec/case/v1p0/context/imscasev1p0_context_v1p0.jsonld'
                : null,
            'type' => (null !== $addType)
                ? 'CFRubric'
                : null,
            'identifier' => $data->getIdentifier(),
            'uri' => $this->api1Uris->getUri($data),
            'title' => $data->getTitle(),
            'lastChangeDateTime' => $this->getLastChangeDateTime($data),
            'description' => $data->getDescription(),
        ];

        if ($addCriteria) {
            foreach ($data->getCriteria() as $criterion) {
                $ret['CFRubricCriteria'][] = $this->normalizer->normalize($criterion, $format, $context);
            }
        }

        if (in_array('opensalt', $context['groups'] ?? [], true)) {
            $ret['_opensalt'] = $data->getExtra();
        }

        return Collection::removeEmptyElements($ret);
    }
}
