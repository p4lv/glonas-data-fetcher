<?php

namespace App\Repository;

use App\Entity\Vehicle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Vehicle>
 */
class VehicleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vehicle::class);
    }

    public function save(Vehicle $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Vehicle $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByExternalId(string $externalId): ?Vehicle
    {
        return $this->findOneBy(['externalId' => $externalId]);
    }

    public function findAllWithRecentPosition(): array
    {
        return $this->createQueryBuilder('v')
            ->where('v.lastPositionTime IS NOT NULL')
            ->orderBy('v.lastPositionTime', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
