<?php

namespace App\Serializer\CaseJson;

use App\Entity\Framework\LsAssociation;
use App\Service\Api1Uris;
use App\Util\Collection;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class LsAssociationNormalizer implements NormalizerInterface
{
    use LinkUriTrait;
    use LastChangeDateTimeTrait;

    public function __construct(
        private readonly Api1Uris $api1Uris,
    ) {
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof LsAssociation;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [LsAssociation::class => true];
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): ?array
    {
        if (!$data instanceof LsAssociation) {
            return null;
        }

        $jsonLd = $context['case-json-ld'] ?? null;
        $addContext = (null !== $jsonLd) ? ($context['add-case-context'] ?? null) : null;
        $addType = (null === $addContext) ? ($context['add-case-type'] ?? null) : $addContext;
        $addLinkUriType = (($context['no-case-link-uri-type'] ?? null) !== null) ? null : $addContext;
        $ret = [
            '@context' => (null !== $addContext)
                ? 'https://purl.imsglobal.org/spec/case/v1p0/context/imscasev1p0_context_v1p0.jsonld'
                : null,
            'type' => (null !== $addType)
                ? 'CFAssociation'
                : null,
            'identifier' => $data->getIdentifier(),
            'uri' => $this->api1Uris->getUri($data),
            'CFDocumentURI' => $this->createDocumentLinkUri($data->getLsDoc(), 'LsAssociation', $context),
            'lastChangeDateTime' => $this->getLastChangeDateTime($data),
            'sequenceNumber' => $data->getSequenceNumber(),
            'CFAssociationGroupingURI' => $this->createLinkUri($data->getGroup(), $context),
            'originNodeURI' => $this->createOutLink($data, 'origin', $context, null !== $addLinkUriType),
            'associationType' => $data->getNormalizedType(),
            'destinationNodeURI' => $this->createOutLink($data, 'destination', $context, null !== $addLinkUriType),
        ];

        if (in_array('opensalt', $context['groups'] ?? [], true)) {
            $ret['_opensalt'] = $data->getExtra();
            $ret['_opensalt']['subtype'] = $data->getSubtype();
            $ret['_opensalt']['annotation'] = $data->getAnnotation();
        }

        return Collection::removeEmptyElements($ret);
    }

    protected function createOutLink(LsAssociation $association, string $which, array $context, bool $addType = true): ?array
    {
        if (!in_array($which, ['origin', 'destination'])) {
            throw new \InvalidArgumentException('Expecting "origin" or "destination" for which part of the association is wanted');
        }

        $targetLink = $this->api1Uris->getNodeLinkUri($which, $association);

        if (null === $targetLink) {
            return null;
        }

        if (null === ($context['case-json-ld'] ?? null)) {
            return $targetLink;
        }

        return [
            'type' => $addType ? 'LinkURI' : null,
            'title' => $targetLink['title'],
            'identifier' => $targetLink['identifier'],
            'uri' => $targetLink['uri'],
        ];
    }
}
