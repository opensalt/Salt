<?php

namespace App\Repository\Framework\Mirror;

use App\DTO\Mirror\ServerListFrameworkItem;
use App\DTO\Mirror\ServerListItem;
use App\Entity\Framework\Mirror\Framework;
use App\Entity\Framework\Mirror\Server;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Server[]    findAll()
 * @method Server|null findOneByUrl(string $url)
 */
class ServerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Server::class);
    }

    public function findFrameworkOnServer(string $identifier, Server $server): ?Framework
    {
        return $this->getEntityManager()->getRepository(Framework::class)
            ->findOneBy(['identifier' => $identifier, 'server' => $server])
        ;
    }

    /**
     * @return ServerListItem[]
     */
    public function findAllForList(): array
    {
        $servers = array_map(
            fn ($rec) => $rec[0],
            $this->createQueryBuilder('server')
                ->select(sprintf(
                    'server.id, NEW %s(server.id, server.url)',
                    ServerListItem::class
                ))
                ->indexBy('server', 'server.id')
                ->getQuery()
                ->getResult()
        );

        $frameworks = $this->createQueryBuilder('server')
            ->select(sprintf(
                'NEW %s(server.id, framework.status, framework.include)',
                ServerListFrameworkItem::class
            ))
            ->leftJoin('server.frameworks', 'framework')
            ->getQuery()
            ->getResult()
        ;

        foreach ($frameworks as $framework) {
            $servers[$framework->serverId]->frameworks[] = $framework;
        }

        return $servers;
    }

    public function findNext(): ?Server
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.nextCheck < :now')
            ->andWhere('s.nextCheck IS NOT NULL')
            ->andWhere('s.checkServer = 1')
            ->addOrderBy('s.priority', 'DESC')
            ->addOrderBy('s.lastCheck', 'ASC')
            ->getQuery()
            ->setParameter('now', new \DateTimeImmutable())
            ->setMaxResults(1)
            ->getOneOrNullResult()
        ;
    }
}
