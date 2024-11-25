<?php

namespace App\Serializer\CaseJson;

use App\Entity\Framework\LsAssociation;
use App\Entity\Framework\LsDoc;
use App\Entity\Framework\LsItem;
use App\Repository\Framework\LsDocRepository;
use App\Security\Permission;
use App\Service\Api1Uris;
use App\Util\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class CfPackageNormalizer implements NormalizerAwareInterface, NormalizerInterface
{
    use NormalizerAwareTrait;

    public function __construct(
        private readonly Api1Uris $api1Uris,
        private readonly LsDocRepository $docRepository,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof LsDoc && null !== ($context['generate-package'] ?? null);
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
        unset($context['add-case-context'], $context['generate-package']);
        $context['no-association-links'] = true;
        $data = [
            '@context' => (null !== $addContext)
                ? 'https://purl.imsglobal.org/spec/case/v1p0/context/imscasev1p0_context_v1p0.jsonld'
                : null,
            'uri' => (null !== $jsonLd)
                ? $this->api1Uris->getUri($object, 'api_v1p0_cfpackage')
                : null,
            'type' => (null !== $jsonLd)
                ? 'CFPackage'
                : null,
            'CFDocument' => $this->normalizer->normalize($object, $format, $context),
        ];

        $items = $this->docRepository->findAllItemsForCFPackage($object, Query::HYDRATE_OBJECT);
        foreach ($items as $key => $obj) {
            $this->entityManager->detach($obj);
            $data['CFItems'][] = $this->normalizer->normalize($obj, $format, $context);
            unset($items[$key]);
        }

        $items = $this->docRepository->findAllAssociationsIterator($object, Query::HYDRATE_OBJECT);
        foreach ($items as $key => $obj) {
            $this->entityManager->detach($obj);
            if (!$this->canListDocument($obj, 'origin') ||
                !$this->canListDocument($obj, 'destination')) {
                // Remove associations to frameworks one can't normally see
                continue;
            }

            $data['CFAssociations'][] = $this->normalizer->normalize($obj, $format, $context);
        }

        foreach (['CFConcepts', 'CFSubjects', 'CFLicenses', 'CFItemTypes', 'CFAssociationGroupings'] as $defType) {
            $defs = match ($defType) {
                'CFConcepts' => $this->docRepository->findAllUsedConcepts($object, Query::HYDRATE_OBJECT),
                'CFSubjects' => $object->getSubjects(),
                'CFLicenses' => array_values($this->docRepository->findAllUsedLicences($object, Query::HYDRATE_OBJECT)),
                'CFItemTypes' => $this->docRepository->findAllUsedItemTypes($object, Query::HYDRATE_OBJECT),
                'CFAssociationGroupings' => $this->docRepository->findAllUsedAssociationGroups($object, Query::HYDRATE_OBJECT),
            };

            foreach ($defs as $obj) {
                $data['CFDefinitions'][$defType][] = $this->normalizer->normalize($obj, $format, $context);
            }
        }

        $items = $this->docRepository->findAllUsedRubrics($object, Query::HYDRATE_OBJECT);
        foreach ($items as $obj) {
            $data['CFRubrics'][] = $this->normalizer->normalize($obj, $format, $context);
        }

        return Collection::removeEmptyElements($data);
    }

    public function setNormalizer(NormalizerInterface $normalizer): void
    {
        $this->normalizer = $normalizer;
    }

    protected function canListDocument(LsAssociation $obj, string $which): bool
    {
        $target = match ($which) {
            'origin' => $obj->getOrigin(),
            'destination' => $obj->getDestination(),
            default => throw new \InvalidArgumentException('Expected "origin" or "destination"'),
        };

        if (!is_object($target)) {
            return true;
        }

        $targetDoc = match (true) {
            $target instanceof LsDoc => $target,
            $target instanceof LsItem => $target->getLsDoc(),
        };

        if (LsDoc::ADOPTION_STATUS_PRIVATE_DRAFT !== $targetDoc->getAdoptionStatus()) {
            return true;
        }

        if ($obj->getLsDoc()?->getId() === $targetDoc->getId()) {
            // Even if private draft, we can view if the targetDoc is the same as this one
            return true;
        }

        return $this->authorizationChecker->isGranted(Permission::FRAMEWORK_LIST, $targetDoc);
    }
}
