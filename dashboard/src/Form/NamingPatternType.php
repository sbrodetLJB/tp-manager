<?php

namespace App\Form;

use App\Entity\NamingPattern;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NamingPatternType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, [
                'label' => 'Nom du gabarit',
                'attr' => ['placeholder' => 'ex: prenom.nom'],
            ])
            ->add('template', TextType::class, [
                'label' => 'Gabarit',
                'help' => 'Jetons disponibles : {prenom} {nom} {initiale_prenom} {initiale_nom} {matricule} {annee}',
                'attr' => ['placeholder' => '{prenom}.{nom}'],
            ])
            ->add('maxLength', IntegerType::class, [
                'label' => 'Longueur maximale du login',
                'required' => false,
                'help' => '32 recommandé (contrainte des comptes Linux).',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => NamingPattern::class,
        ]);
    }
}
