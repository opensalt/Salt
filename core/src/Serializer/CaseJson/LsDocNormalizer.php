<?php

namespace App\Serializer\CaseJson;

use App\Entity\Framework\LsDoc;
use App\Service\Api1Uris;
use App\Util\Collection;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class LsDocNormalizer implements NormalizerInterface
{
    use DateCallbackTrait;
    use AssociationLinkTrait;
    use LinkUriTrait;
    use LastChangeDateTimeTrait;

    public function __construct(
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly Api1Uris $api1Uris,
    ) {
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof LsDoc && null === ($context['generate-package'] ?? null);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [LsDoc::class => false];
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): ?array
    {
        if (!$data instanceof LsDoc) {
            return null;
        }

        $jsonLd = $context['case-json-ld'] ?? null;
        $addContext = (null !== $jsonLd) ? ($context['add-case-context'] ?? null) : null;
        $addType = (null === $addContext) ? ($context['add-case-type'] ?? null) : $addContext;
        $subject = $data->getSubject();
        $subjectURIs = $data->getSubjects();
        $ret = [
            '@context' => (null !== $addContext)
                ? 'https://purl.imsglobal.org/spec/case/v1p0/context/imscasev1p0_context_v1p0.jsonld'
                : null,
            'type' => (null !== $addType)
                ? 'CFDocument'
                : null,
            'identifier' => $data->getIdentifier(),
            'uri' => $this->api1Uris->getUri($data),
            'creator' => $data->getCreator(),
            'title' => $data->getTitle(),
            'lastChangeDateTime' => $this->getLastChangeDateTime($data),
            'officialSourceURL' => $data->getOfficialUri(),
            'CFPackageURI' => $this->createPackageLinkUri($data, 'LsDoc', $context),
            'publisher' => $data->getPublisher(),
            'description' => $data->getDescription(),
            'subject' => count($subject ?? []) > 0
                ? $subject
                : null,
            'subjectURI' => count($subjectURIs) > 0
                ? $this->api1Uris->getLinkUriList($subjectURIs)
                : null,
            'language' => $data->getLanguage(),
            'version' => $data->getVersion(),
            'adoptionStatus' => $data->getAdoptionStatus(),
            'statusStartDate' => $this->toDate($data->getStatusStart()),
            'statusEndDate' => $this->toDate($data->getStatusEnd()),
            'licenseURI' => $this->api1Uris->getLinkUri($data->getLicence()),
            'notes' => $data->getNote(),
            'updatedAt' => in_array('updatedAt', $context['groups'] ?? [], true) ? $data->getUpdatedAt() : null,
            'associationSet' => $this->createAssociationLinks($data, $context),
        ];

        if (in_array('opensalt', $context['groups'] ?? [], true)) {
            $ret['_opensalt'] = $data->getExtra();
        }

        return Collection::removeEmptyElements($ret);
    }
}
