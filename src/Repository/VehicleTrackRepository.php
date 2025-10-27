<?php

namespace App\Repository;

use App\Entity\VehicleTrack;
use App\Entity\Vehicle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VehicleTrack>
 */
class VehicleTrackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VehicleTrack::class);
    }

    public function save(VehicleTrack $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(VehicleTrack $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByVehicleAndDateRange(Vehicle $vehicle, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.vehicle = :vehicle')
            ->andWhere('t.timestamp >= :from')
            ->andWhere('t.timestamp <= :to')
            ->setParameter('vehicle', $vehicle)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('t.timestamp', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findLatestByVehicle(Vehicle $vehicle, int $limit = 100): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.vehicle = :vehicle')
            ->setParameter('vehicle', $vehicle)
            ->orderBy('t.timestamp', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
