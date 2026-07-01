<?php

namespace App\Form;

use App\Entity\Etablissement;
use App\Enum\DbEngine;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EtablissementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => "Nom de l'établissement",
            ])
            ->add('dbEngine', ChoiceType::class, [
                'label' => 'Moteur de base de données pour les projets élèves',
                'choices' => [
                    'MySQL / MariaDB' => DbEngine::Mysql,
                    'PostgreSQL' => DbEngine::Postgresql,
                ],
                'choice_value' => static fn (?DbEngine $engine) => $engine?->value,
                'help' => 'Choix global, verrouillé une fois des projets provisionnés.',
            ])
            ->add('webRootBase', TextType::class, [
                'label' => 'Racine des dépôts web sur la VM de TP',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Etablissement::class,
        ]);
    }
}
