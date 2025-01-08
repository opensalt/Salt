<?php

namespace App\Serializer\CaseJson;

use App\Entity\Framework\LsAssociation;
use App\Entity\Framework\LsDoc;
use App\Entity\Framework\LsItem;
use App\Security\Permission;

trait AssociationLinkTrait
{
    protected function createAssociationLinks(LsDoc|LsItem $object, array $context = []): ?array
    {
        if (null === ($context['case-json-ld'] ?? null)) {
            return null;
        }

        if (null !== ($context['no-association-links'] ?? null)) {
            return null;
        }

        $associationSet = [];

        $associations = $object->getAssociations();
        foreach ($associations as $association) {
            $link = $this->createAssociationLink($association, 'destination');
            if (null !== $link) {
                $associationSet[] = $link;
            }
        }

        $associations = $object->getInverseAssociations();
        foreach ($associations as $association) {
            $link = $this->createAssociationLink($association, 'origin');
            if (null !== $link) {
                $associationSet[] = $link;
            }
        }

        if (0 === count($associationSet)) {
            return null;
        }

        return $associationSet;
    }

    private function createAssociationLink(LsAssociation $association, string $which): ?array
    {
        if (!in_array($which, ['origin', 'destination'])) {
            throw new \InvalidArgumentException('Expecting "origin" or "destination" for which part of the association is wanted');
        }

        $source = $association->getOrigin();
        $target = $association->getDestination();
        if ('origin' === $which) {
            $source = $association->getDestination();
            $target = $association->getOrigin();
        }

        if (null === $target) {
            return null;
        }

        /** @var LsDoc $associationDoc */
        $associationDoc = $association->getLsDoc();

        $sourceDocId = match (true) {
            $source instanceof LsDoc => $source->getId(),
            $source instanceof LsItem => $source->getLsDoc()->getId(),
            default => throw new \InvalidArgumentException('$source is not an LsDoc nor LsItem'),
        };

        // Check that the doc the association is in is allowed to be viewed
        if (LsDoc::ADOPTION_STATUS_PRIVATE_DRAFT === $associationDoc->getAdoptionStatus()
            && $sourceDocId !== $associationDoc->getId()
            && !$this->authorizationChecker->isGranted(Permission::FRAMEWORK_LIST, $associationDoc)) {
            return null;
        }

        // Check that the target of the association link is allowed to be viewed
        if (is_object($target)) {
            $targetDoc = match (true) {
                $target instanceof LsDoc => $target,
                $target instanceof LsItem => $target->getLsDoc(),
            };
            $targetDocId = $targetDoc->getId();

            if (LsDoc::ADOPTION_STATUS_PRIVATE_DRAFT === $targetDoc->getAdoptionStatus()
                && $sourceDocId !== $targetDocId
                && $associationDoc->getId() !== $targetDocId
                && !$this->authorizationChecker->isGranted(Permission::FRAMEWORK_LIST, $targetDoc)
            ) {
                return null;
            }
        }

        $targetLink = $this->api1Uris->getNodeLinkUri($which, $association);
        $associationType = $association->getNormalizedType($association->getType());

        return [
            'associationType' => match ($which) {
                'destination' => $associationType,
                'origin' => match ($associationType) {
                    'exactMatchOf' => 'exactMatchOf',
                    'isRelatedTo' => 'isRelatedTo',
                    'isPeerOf' => 'isPeerOf',
                    'replacedBy' => 'replaces',
                    'hasSkillLevel' => 'skillLevelFor',
                    'isPartOf' => 'hasPart',
                    'precedes' => 'hasPredecessor',
                    'isChildOf' => 'isParentOf',
                    default => throw new \InvalidArgumentException('Unknown association type'),
                },
            },
            'title' => $targetLink['title'],
            'identifier' => $targetLink['identifier'],
            'uri' => $targetLink['uri'],
        ];
    }
}
