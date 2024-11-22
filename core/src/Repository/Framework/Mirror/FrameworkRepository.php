<?php

namespace App\Repository\Framework\Mirror;

use App\Entity\Framework\Mirror\Framework;
use App\Entity\Framework\Mirror\Server;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FrameworkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Framework::class);
    }

    public function findNext(): ?Framework
    {
        return $this->createQueryBuilder('f')
            ->join('f.server', 's')
            ->andWhere('f.nextCheck < :now')
            ->andWhere('f.nextCheck IS NOT NULL')
            ->andWhere('f.include = 1')
            ->andWhere('f.status != :framework_suspended')
            ->andWhere('(f.status = :framework_scheduled OR s.status != :server_suspended)')
            ->addOrderBy('f.priority', 'DESC')
            ->addOrderBy('f.lastCheck', 'ASC')
            ->getQuery()
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('framework_suspended', Framework::STATUS_SUSPENDED)
            ->setParameter('framework_scheduled', Framework::STATUS_SCHEDULED)
            ->setParameter('server_suspended', Server::STATUS_SUSPENDED)
            ->setMaxResults(1)
            ->getOneOrNullResult()
        ;
    }

    public function markAsProcessing(Framework $framework): bool
    {
        $ret = $this->getEntityManager()->createQuery(<<<xENDx
                UPDATE {$this->getEntityName()} f
                   SET f.nextCheck = :nextCheck,
                       f.status = :status
                 WHERE f.id = :id
                   AND f.nextCheck = :checkAt
            xENDx)
            ->setParameter('nextCheck', new \DateTimeImmutable('now + 1 hour'))
            ->setParameter('status', Framework::STATUS_PROCESSING)
            ->setParameter('id', $framework->getId())
            ->setParameter('checkAt', $framework->getNextCheck())
            ->execute()
        ;

        return 1 === $ret;
    }
}
