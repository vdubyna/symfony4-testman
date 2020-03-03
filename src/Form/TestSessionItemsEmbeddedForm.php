<?php

namespace App\Form;

use App\Entity\TestSessionTemplateItem;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TestSessionItemsEmbeddedForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('category')
            ->add('level', ChoiceType::class, [
                'help'     => 'Complexity level from 1 to 6',
                'mapped'   => true,
                'expanded' => false,
                'multiple' => false,
                'choices'  => [
                    '1' => '1',
                    '2' => '2',
                    '3' => '3',
                    '4' => '4',
                    '5' => '5',
                    '6' => '6',
                ],
            ])
            ->add('cutoff', IntegerType::class, [
                'label' => 'Number of questions',
                'help'  => 'Limit of generated questions per level',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => TestSessionTemplateItem::class,
        ]);
    }
}
