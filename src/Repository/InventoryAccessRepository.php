<?php

namespace App\Repository;

use App\Entity\Inventory;
use App\Entity\InventoryAccess;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InventoryAccess>
 */
class InventoryAccessRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InventoryAccess::class);
    }

    /**
     * Проверяет, имеет ли пользователь доступ к инвентарю
     */
    public function hasAccess(Inventory $inventory, ?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $this->findOneBy([
            'inventory' => $inventory,
            'user' => $user,
        ]) !== null;
    }

//    /**
//     * @return InventoryAccess[] Returns an array of InventoryAccess objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('i')
//            ->andWhere('i.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('i.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?InventoryAccess
//    {
//        return $this->createQueryBuilder('i')
//            ->andWhere('i.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
