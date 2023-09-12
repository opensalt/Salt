<?php

namespace App\Repository\Framework;

use App\Entity\Framework\LsAssociation;
use App\Entity\Framework\LsDoc;
use App\Entity\Framework\LsItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method LsAssociation|null findOneByIdentifier(string $identifier)
 */
class LsAssociationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LsAssociation::class);
    }

    public function removeAssociation(LsAssociation $lsAssociation): void
    {
        $this->_em->remove($lsAssociation);
        $origin = $lsAssociation->getOrigin();
        if (is_object($origin)) {
            $origin->removeAssociation($lsAssociation);
        }
        $dest = $lsAssociation->getDestination();
        if (is_object($dest)) {
            $dest->removeInverseAssociation($lsAssociation);
        }
    }

    /**
     * Remove all associations from the object.
     */
    public function removeAllAssociations(LsItem|LsDoc $object): void
    {
        foreach ($object->getAssociations() as $association) {
            $this->removeAssociation($association);
        }
        foreach ($object->getInverseAssociations() as $association) {
            $this->removeAssociation($association);
        }
    }

    /**
     * Remove all associations of a specific type from the object.
     *
     * @return LsAssociation[]
     */
    public function removeAllAssociationsOfType(LsItem|LsDoc $object, string $type): array
    {
        $deleted = [];
        foreach ($object->getAssociations() as $association) {
            if ($association->getType() === $type) {
                $this->removeAssociation($association);
                $deleted[] = $association;
            }
        }

        return $deleted;
    }

    /**
     * @return LsAssociation[]
     */
    public function findAllChildAssociationsFor(string $identifier): array
    {
        $qry = $this->createQueryBuilder('a')
            ->where('a.destinationNodeIdentifier = :identifier')
            ->andWhere('a.type = :type')
            ->setParameter('identifier', $identifier)
            ->setParameter('type', LsAssociation::CHILD_OF)
            ->getQuery();

        return $qry->getResult();
    }

    /**
     * @return LsAssociation[]
     */
    public function findAllAssociationsFor(string $identifier): array
    {
        $item = $this->getEntityManager()->getRepository(LsItem::class)
            ->findOneBy(['identifier' => str_replace('_', '', $identifier)]);

        if (null === $item) {
            return [];
        }

        $qry = $this->createQueryBuilder('a')
            ->where('a.originLsItem = :id')
            ->orWhere('a.destinationLsItem = :id')
            ->setParameter('id', $item->getId())
            ->getQuery();

        return $qry->getResult();
    }

    /**
     * @param string[] $identifiers
     *
     * @return LsAssociation[]
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
