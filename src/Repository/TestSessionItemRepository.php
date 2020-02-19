<?php

namespace App\Repository;

use App\Entity\TestSessionItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method TestSessionItem|null find($id, $lockMode = null, $lockVersion = null)
 * @method TestSessionItem|null findOneBy(array $criteria, array $orderBy = null)
 * @method TestSessionItem[]    findAll()
 * @method TestSessionItem[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TestSessionItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TestSessionItem::class);
    }

    // /**
    //  * @return TestSessionItem[] Returns an array of TestSessionItem objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('t.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?TestSessionItem
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
