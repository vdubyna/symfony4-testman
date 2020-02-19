<?php

namespace App\Repository;

use App\Entity\TestSessionTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method TestSessionTemplate|null find($id, $lockMode = null, $lockVersion = null)
 * @method TestSessionTemplate|null findOneBy(array $criteria, array $orderBy = null)
 * @method TestSessionTemplate[]    findAll()
 * @method TestSessionTemplate[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TestSessionTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TestSessionTemplate::class);
    }

    // /**
    //  * @return TestSessionTemplate[] Returns an array of TestSessionTemplate objects
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
    public function findOneBySomeField($value): ?TestSessionTemplate
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
