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
     * Find all vehicles ordered by status_checked_at (oldest first, null first)
     * This ensures vehicles that haven't been checked are processed first
     */
    public function findAllOrderedByStatusCheck(): array
    {
        return $this->createQueryBuilder('v')
            ->orderBy('v.statusCheckedAt', 'ASC')  // NULL values come first, then oldest
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
        ?string $gpsStatusFilter = null,
        ?string $deviceTypeFilter = null
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
        if ($gpsStatusFilter && in_array($gpsStatusFilter, ['online', 'offline', 'unknown', 'no_data'], true)) {
            $qb->andWhere('v.gpsStatus = :status')
               ->setParameter('status', $gpsStatusFilter);
        }

        // Apply device type filter if provided
        if ($deviceTypeFilter && in_array($deviceTypeFilter, ['gps_tracker', 'beacon'], true)) {
            $qb->andWhere('v.deviceType = :deviceType')
               ->setParameter('deviceType', $deviceTypeFilter);
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
            'no_data' => 0,
            'unknown' => 0,
            'total' => 0,
        ];

        foreach ($results as $result) {
            $status = $result['gpsStatus'] ?? 'unknown';
            $count = (int)$result['count'];

            // Only count known statuses in their respective buckets
            if (isset($stats[$status])) {
                $stats[$status] = $count;
            }

            $stats['total'] += $count;
        }

        return $stats;
    }

    /**
     * Find paired device by base name (without маяк/asset tracker suffix)
     * Used to link beacon devices to their main GPS trackers
     *
     * @param string $baseName Base name without "(маяк)" or "(asset tracker)"
     * @param string $deviceType Device type to search for ('gps_tracker' or 'beacon')
     * @return Vehicle|null
     */
    public function findPairedDevice(string $baseName, string $deviceType = 'gps_tracker'): ?Vehicle
    {
        return $this->createQueryBuilder('v')
            ->where('v.name = :baseName')
            ->andWhere('v.deviceType = :deviceType')
            ->setParameter('baseName', $baseName)
            ->setParameter('deviceType', $deviceType)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find vehicles with pagination, prioritizing unknown status with null statusCheckedAt
     * Used for synchronization to process never-checked unknown devices first
     *
     * @param int $limit Maximum number of results
     * @param int $offset Starting offset
     * @return array Array of Vehicle entities
     */
    public function findForSyncPrioritized(int $limit, int $offset): array
    {
        return $this->createQueryBuilder('v')
            ->addSelect('
                CASE
                    WHEN v.gpsStatus = :unknownStatus AND v.statusCheckedAt IS NULL THEN 0
                    ELSE 1
                END AS HIDDEN priority
            ')
            ->setParameter('unknownStatus', 'unknown')
            ->orderBy('priority', 'ASC')
            ->addOrderBy('v.statusCheckedAt', 'ASC')  // Within each group, oldest first (NULL values come first)
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get detailed statistics about device types and their GPS statuses
     *
     * @return array [
     *   'gps_tracker' => ['total' => int, 'online' => int, 'offline' => int, 'no_data' => int, 'unknown' => int],
     *   'beacon' => ['total' => int, 'online' => int, 'offline' => int, 'no_data' => int, 'unknown' => int],
     *   'linked_pairs' => int,
     *   'total_devices' => int
     * ]
     */
    public function getDeviceTypeStatistics(): array
    {
        // Get GPS Tracker statistics by status
        $qb = $this->createQueryBuilder('v');
        $trackerStats = $qb->select('v.gpsStatus', 'COUNT(v.id) as count')
            ->where('v.deviceType = :deviceType')
            ->setParameter('deviceType', 'gps_tracker')
            ->groupBy('v.gpsStatus')
            ->getQuery()
            ->getResult();

        // Get Beacon statistics by status
        $qb2 = $this->createQueryBuilder('v');
        $beaconStats = $qb2->select('v.gpsStatus', 'COUNT(v.id) as count')
            ->where('v.deviceType = :deviceType')
            ->setParameter('deviceType', 'beacon')
            ->groupBy('v.gpsStatus')
            ->getQuery()
            ->getResult();

        // Count linked pairs (beacons that have a parent vehicle)
        $qb3 = $this->createQueryBuilder('v');
        $linkedPairs = (int) $qb3->select('COUNT(v.id)')
            ->where('v.deviceType = :deviceType')
            ->andWhere('v.parentVehicle IS NOT NULL')
            ->setParameter('deviceType', 'beacon')
            ->getQuery()
            ->getSingleScalarResult();

        // Process GPS Tracker stats
        $trackerData = [
            'total' => 0,
            'online' => 0,
            'offline' => 0,
            'no_data' => 0,
            'unknown' => 0,
        ];

        foreach ($trackerStats as $stat) {
            $status = $stat['gpsStatus'] ?? 'unknown';
            $count = (int)$stat['count'];

            if (isset($trackerData[$status])) {
                $trackerData[$status] = $count;
            }
            $trackerData['total'] += $count;
        }

        // Process Beacon stats
        $beaconData = [
            'total' => 0,
            'online' => 0,
            'offline' => 0,
            'no_data' => 0,
            'unknown' => 0,
        ];

        foreach ($beaconStats as $stat) {
            $status = $stat['gpsStatus'] ?? 'unknown';
            $count = (int)$stat['count'];

            if (isset($beaconData[$status])) {
                $beaconData[$status] = $count;
            }
            $beaconData['total'] += $count;
        }

        return [
            'gps_tracker' => $trackerData,
            'beacon' => $beaconData,
            'linked_pairs' => $linkedPairs,
            'total_devices' => $trackerData['total'] + $beaconData['total'],
        ];
    }
}
