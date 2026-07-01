<?php

namespace App\Repository;

use App\Entity\CredentialReveal;
use App\Entity\Projet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CredentialReveal>
 */
class CredentialRevealRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CredentialReveal::class);
    }

    public function findOneByToken(string $token): ?CredentialReveal
    {
        return $this->findOneBy(['revealToken' => $token]);
    }

    public function findLatestForProjet(Projet $projet): ?CredentialReveal
    {
        return $this->findOneBy(['projet' => $projet], ['createdAt' => 'DESC']);
    }

    /**
     * @return CredentialReveal[]
     */
    public function findExpiredAndUnviewed(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.viewedAt IS NULL')
            ->andWhere('c.expiresAt < :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }
}
