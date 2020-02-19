<?php

namespace App\Repository;

use App\Entity\TestSessionTemplateItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method TestSessionTemplateItem|null find($id, $lockMode = null, $lockVersion = null)
 * @method TestSessionTemplateItem|null findOneBy(array $criteria, array $orderBy = null)
 * @method TestSessionTemplateItem[]    findAll()
 * @method TestSessionTemplateItem[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TestSessionTemplateItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TestSessionTemplateItem::class);
    }

    // /**
    //  * @return TestSessionTemplateItem[] Returns an array of TestSessionTemplateItem objects
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
    public function findOneBySomeField($value): ?TestSessionTemplateItem
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
