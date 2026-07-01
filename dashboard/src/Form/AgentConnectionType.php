<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Non lié à AgentConnection (data_class) : le token est un champ à saisie
 * unique en clair, chiffré côté contrôleur avant stockage — jamais bindé
 * directement sur l'entité (voir AgentTokenEncryptor).
 */
class AgentConnectionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('baseUrl', TextType::class, [
                'label' => "URL de l'agent",
                'attr' => ['placeholder' => 'https://tp-vm.local:8000'],
            ])
            ->add('token', PasswordType::class, [
                'label' => "Jeton bearer (affiché une seule fois par install.sh)",
                'mapped' => false,
                'required' => false,
                'help' => 'Laisser vide pour conserver le jeton déjà enregistré.',
            ])
        ;
    }
}
