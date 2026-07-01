<?php

namespace App\Repository;

use App\Entity\Eleve;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Eleve>
 */
class EleveRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Eleve::class);
    }

    /**
     * Le login est unique établissement-wide (compte Linux sur une VM
     * partagée) : on vérifie donc sur l'ensemble des élèves, pas seulement
     * dans la classe importée.
     */
    public function loginExists(string $login): bool
    {
        return null !== $this->createQueryBuilder('e')
            ->select('1')
            ->andWhere('e.login = :login')
            ->setParameter('login', $login)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
