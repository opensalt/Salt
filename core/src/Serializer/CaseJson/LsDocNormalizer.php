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

    public function normalize(mixed $object, ?string $format = null, array $context = []): ?array
    {
        if (!$object instanceof LsDoc) {
            return null;
        }

        $jsonLd = $context['case-json-ld'] ?? null;
        $addContext = (null !== $jsonLd) ? ($context['add-case-context'] ?? null) : null;
        $addType = (null === $addContext) ? ($context['add-case-type'] ?? null) : $addContext;
        $subject = $object->getSubject();
        $subjectURIs = $object->getSubjects();
        $data = [
            '@context' => (null !== $addContext)
                ? 'https://purl.imsglobal.org/spec/case/v1p0/context/imscasev1p0_context_v1p0.jsonld'
                : null,
            'type' => (null !== $addType)
                ? 'CFDocument'
                : null,
            'identifier' => $object->getIdentifier(),
            'uri' => $this->api1Uris->getUri($object),
            'creator' => $object->getCreator(),
            'title' => $object->getTitle(),
            'lastChangeDateTime' => $this->getLastChangeDateTime($object),
            'officialSourceURL' => $object->getOfficialUri(),
            'CFPackageURI' => $this->createPackageLinkUri($object, 'LsDoc', $context),
            'publisher' => $object->getPublisher(),
            'description' => $object->getDescription(),
            'subject' => count($subject ?? []) > 0
                ? $subject
                : null,
            'subjectURI' => count($subjectURIs) > 0
                ? $this->api1Uris->getLinkUriList($subjectURIs)
                : null,
            'language' => $object->getLanguage(),
            'version' => $object->getVersion(),
            'adoptionStatus' => $object->getAdoptionStatus(),
            'statusStartDate' => $this->toDate($object->getStatusStart()),
            'statusEndDate' => $this->toDate($object->getStatusEnd()),
            'licenseURI' => $this->api1Uris->getLinkUri($object->getLicence()),
            'notes' => $object->getNote(),
            'updatedAt' => in_array('updatedAt', $context['groups'] ?? [], true) ? $object->getUpdatedAt() : null,
            'associationSet' => $this->createAssociationLinks($object, $context),
        ];

        if (in_array('opensalt', $context['groups'] ?? [], true)) {
            $data['_opensalt'] = $object->getExtra();
        }

        return Collection::removeEmptyElements($data);
    }
}
