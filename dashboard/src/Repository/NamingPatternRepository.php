<?php

namespace App\Repository;

use App\Entity\Etablissement;
use App\Entity\NamingPattern;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NamingPattern>
 */
class NamingPatternRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NamingPattern::class);
    }

    /**
     * @return NamingPattern[]
     */
    public function findAllForEtablissement(Etablissement $etablissement): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.etablissement = :etablissement')
            ->setParameter('etablissement', $etablissement)
            ->orderBy('p.label', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
