<?php

namespace App\Repository\Framework;

use App\Entity\Framework\LsDefItemType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method LsDefItemType|null findOneByTitle(string $title)
 * @method LsDefItemType|null findOneByIdentifier(string $identifier)
 */
class LsDefItemTypeRepository extends AbstractLsDefinitionRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LsDefItemType::class);
    }

    /**
     * @return array|LsDefItemType[]
     */
    public function getList(): array
    {
        $qb = $this->createQueryBuilder('t', 't.code')
            ->orderBy('t.code')
        ;

        return $qb->getQuery()->getResult();
    }

    /**
     * @param string[] $identifiers
     *
     * @return LsDefItemType[]
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

    /**
     * @return array{'results': LsDefItemType[], 'more': bool}
     */
    public function getSelect2List(?string $search = null, int $limit = 50, int $page = 1): array
    {
        // NOTE: indexing by title makes there only be one value per title
        // this should be changed to handle the doc or something
        $qb = $this->createQueryBuilder('t', 't.title')
            ->orderBy('t.title')
            ->setMaxResults($limit + 1)
            ->setFirstResult(($page - 1) * $limit)
            ->andWhere('t.identifier != :jobIdentifier')
            ->setParameter('jobIdentifier', LsDefItemType::TYPE_JOB_IDENTIFIER)
        ;

        if (!empty($search)) {
            $qb->andWhere('t.title LIKE :search')
                ->setParameter('search', '%'.$search.'%')
                ;
        }

        /** @var LsDefItemType[] $results */
        $results = $qb->getQuery()->getResult();

        if (count($results) > $limit) {
            $more = true;
            array_pop($results);
        } else {
            $more = false;
        }

        return ['results' => $results, 'more' => $more];
    }
}
