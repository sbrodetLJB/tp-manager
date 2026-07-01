<?php

namespace App\Form;

use App\Entity\Projet;
use App\Enum\SshAuthMethod;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProjetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom du projet',
                'attr' => ['placeholder' => 'ex: site-vitrine'],
            ])
            ->add('sshAuthMethod', ChoiceType::class, [
                'label' => 'Authentification SSH/SFTP de l\'élève',
                'choices' => [
                    'Mot de passe généré' => SshAuthMethod::Password,
                    'Clé publique (saisie au moment du provisioning)' => SshAuthMethod::PublicKey,
                ],
                'choice_value' => static fn (?SshAuthMethod $method) => $method?->value,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Projet::class,
        ]);
    }
}
