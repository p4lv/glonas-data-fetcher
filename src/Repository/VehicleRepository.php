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

    /**
     * Find vehicles with pagination and search support
     *
     * @param string|null $searchQuery Search query (searches in name, plateNumber, externalId)
     * @param int $page Current page number (1-based)
     * @param int $limit Items per page
     * @param string|null $gpsStatusFilter Filter by GPS status (online, offline, unknown)
     * @return array ['results' => Vehicle[], 'total' => int]
     */
    public function findWithPaginationAndSearch(
        ?string $searchQuery,
        int $page = 1,
        int $limit = 25,
        ?string $gpsStatusFilter = null
    ): array {
        $qb = $this->createQueryBuilder('v');

        // Apply search filter if provided
        if ($searchQuery && trim($searchQuery) !== '') {
            $searchTerm = '%' . strtolower(trim($searchQuery)) . '%';

            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('LOWER(v.name)', ':search'),
                    $qb->expr()->like('LOWER(v.plateNumber)', ':search'),
                    $qb->expr()->like('LOWER(v.externalId)', ':search')
                )
            )
            ->setParameter('search', $searchTerm);
        }

        // Apply GPS status filter if provided
        if ($gpsStatusFilter && in_array($gpsStatusFilter, ['online', 'offline', 'unknown'], true)) {
            $qb->andWhere('v.gpsStatus = :status')
               ->setParameter('status', $gpsStatusFilter);
        }

        // Count total results (before pagination)
        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(v.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Apply ordering and pagination
        $offset = ($page - 1) * $limit;
        $results = $qb->orderBy('v.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'results' => $results,
            'total' => $total,
        ];
    }

    /**
     * Get statistics about GPS status
     *
     * @return array ['online' => int, 'offline' => int, 'unknown' => int, 'total' => int]
     */
    public function getGpsStatusStatistics(): array
    {
        $qb = $this->createQueryBuilder('v');
        $qb->select('v.gpsStatus', 'COUNT(v.id) as count')
           ->groupBy('v.gpsStatus');

        $results = $qb->getQuery()->getResult();

        $stats = [
            'online' => 0,
            'offline' => 0,
            'unknown' => 0,
            'total' => 0,
        ];

        foreach ($results as $result) {
            $status = $result['gpsStatus'] ?? 'unknown';
            $count = (int)$result['count'];
            $stats[$status] = $count;
            $stats['total'] += $count;
        }

        return $stats;
    }
}
