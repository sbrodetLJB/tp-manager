<?php

namespace App\Command;

use App\Repository\CredentialRevealRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Supprime les CredentialReveal expirés jamais consultés : leur ciphertext
 * reste chiffré (le secret n'est de toute façon jamais exposé sans le jeton),
 * mais on ne garde pas indéfiniment des liens de reveal potentiellement
 * encore valides pour un mot de passe qu'un enseignant n'a jamais récupéré.
 */
#[AsCommand(name: 'app:credentials:purge-expired', description: 'Supprime les identifiants de provisioning expirés et jamais consultés')]
class CredentialsPurgeExpiredCommand extends Command
{
    public function __construct(
        private readonly CredentialRevealRepository $credentialRevealRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $expired = $this->credentialRevealRepository->findExpiredAndUnviewed();

        foreach ($expired as $credentialReveal) {
            $this->entityManager->remove($credentialReveal);
        }
        $this->entityManager->flush();

        $io->success(sprintf('%d identifiant(s) expiré(s) et jamais consulté(s) supprimé(s).', count($expired)));

        return Command::SUCCESS;
    }
}
