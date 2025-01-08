<?php

namespace App\Repository\Framework;

use App\Entity\Framework\LsAssociation;
use App\Entity\Framework\LsItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LsItem>
 *
 * @method LsItem|null findOneByIdentifier(string $identifier)
 */
class LsItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LsItem::class);
    }

    /**
     * @return LsItem[]
     */
    public function findAllByIdentifierOrHumanCodingSchemeByValue(string $key): array
    {
        $qry = $this->createQueryBuilder('i');
        $qry->select('i')
            ->where($qry->expr()->orX(
                $qry->expr()->eq('i.humanCodingScheme', ':humanCodingScheme'),
                $qry->expr()->eq('i.identifier', ':identifier')
            ))
            ->setParameter('humanCodingScheme', $key)
            ->setParameter('identifier', $key)
        ;

        return $qry->getQuery()->getResult();
    }

    /**
     * @param string $lsDocId
     * @param string $key
     *
     * @return LsItem[]
     */
    public function findByAllIdentifierOrHumanCodingSchemeByLsDoc($lsDocId, $key): array
    {
        $qry = $this->createQueryBuilder('i');
        $qry->select('i')
            ->where($qry->expr()->orX(
                $qry->expr()->eq('i.humanCodingScheme', ':humanCodingScheme'),
                $qry->expr()->eq('i.identifier', ':identifier')
            ), 'i.lsDoc = :lsDocId')
            ->setParameter('humanCodingScheme', $key)
            ->setParameter('identifier', $key)
            ->setParameter('lsDocId', $lsDocId)
        ;

        return $qry->getQuery()->getResult();
    }

    /**
     * @return LsAssociation[]
     */
    public function findChildAssociations(LsItem $parent, LsItem $child): array
    {
        $associations = [];
        foreach ($child->getAssociations() as $association) {
            if (LsAssociation::CHILD_OF === $association->getType()
                && null !== $association->getDestinationLsItem()
                && $association->getDestinationLsItem()->getId() === $parent->getId()) {
                $associations[] = $association;
            }
        }

        return $associations;
    }

    public function removeAssociation(LsAssociation $association): void
    {
        $this->getEntityManager()->getRepository(LsAssociation::class)->removeAssociation($association);
    }

    public function removeChild(LsItem $parent, LsItem $child): void
    {
        $associations = $this->findChildAssociations($parent, $child);
        foreach ($associations as $association) {
            $this->removeAssociation($association);
        }
    }

    public function removeItemAndChildren(LsItem $lsItem): bool
    {
        $children = $lsItem->getChildren();
        foreach ($children as $child) {
            $this->removeItemAndChildren($child);
        }

        return $this->removeItem($lsItem);
    }

    public function removeItem(LsItem $lsItem): bool
    {
        $hasChildren = $lsItem->getChildren();
        if ($hasChildren->isEmpty()) {
            $this->getEntityManager()->getRepository(LsAssociation::class)->removeAllAssociations($lsItem);
            $this->getEntityManager()->remove($lsItem);

            return true;
        }

        return false;
    }

    public function findExactMatches(string $identifier): array
    {
        $assocRepo = $this->getEntityManager()->getRepository(LsAssociation::class);

        $item = $this->findOneByIdentifier($identifier);
        if (null === $item) {
            return [];
        }

        /** @psalm-suppress InvalidArrayOffset */
        $matched = [$item->getId() => $item];
        $matchedCount = 0;

        while (count($matched) !== $matchedCount) {
            $matchedCount = count($matched);

            $fromCriteria = new Criteria();
            $fromCriteria->where(Criteria::expr()->in('originLsItem', array_keys($matched)));
            $fromCriteria->andWhere(Criteria::expr()->eq('type', LsAssociation::EXACT_MATCH_OF));
            $results = $assocRepo->matching($fromCriteria);
            foreach ($results as $assoc) {
                /** @var LsAssociation $assoc */
                $item = $assoc->getDestinationLsItem();
                if (null !== $item) {
                    $matched[$item->getId()] = $item;
                }
            }

            $toCriteria = new Criteria();
            $toCriteria->where(Criteria::expr()->in('destinationLsItem', array_keys($matched)));
            $toCriteria->andWhere(Criteria::expr()->eq('type', LsAssociation::EXACT_MATCH_OF));
            $results = $assocRepo->matching($toCriteria);
            foreach ($results as $assoc) {
                /** @var LsAssociation $assoc */
                $item = $assoc->getOriginLsItem();
                if (null !== $item) {
                    $matched[$item->getId()] = $item;
                }
            }
        }

        return $matched;
    }

    /**
     * @param string[] $identifiers
     *
     * @return LsItem[]
     */
    public function findByIdentifiers(array $identifiers): array
    {
        if (0 === count($identifiers)) {
            return [];
        }

        $qb = $this->createQueryBuilder('t', 't.identifier');
        $qb->where($qb->expr()->in('t.identifier', $identifiers));

        return $qb->getQuery()->getResult();
    }
}
