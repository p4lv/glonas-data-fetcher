<?php

namespace App\Repository;

use App\Entity\CommandHistory;
use App\Entity\Vehicle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommandHistory>
 */
class CommandHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommandHistory::class);
    }

    public function save(CommandHistory $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CommandHistory $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByVehicleAndDateRange(Vehicle $vehicle, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.vehicle = :vehicle')
            ->andWhere('c.sentAt >= :from')
            ->andWhere('c.sentAt <= :to')
            ->setParameter('vehicle', $vehicle)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('c.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findLatestByVehicle(Vehicle $vehicle, int $limit = 50): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.vehicle = :vehicle')
            ->setParameter('vehicle', $vehicle)
            ->orderBy('c.sentAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
