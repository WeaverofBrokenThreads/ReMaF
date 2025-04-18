<?php

namespace App\Form;

use App\Entity\Association;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use App\Entity\AssociationType;

class AssocCreationType extends AbstractType {

	public function configureOptions(OptionsResolver $resolver): void {
		$resolver->setDefaults(array(
			'intention'       	=> 'newassoc_1779',
			'translation_domain' => 'orgs',
		));
		$resolver->setRequired(['types', 'assocs']);
	}

	public function buildForm(FormBuilderInterface $builder, array $options): void {
		$types = $options['types'];
		$assocs = $options['assocs'];
		$builder->add('name', TextType::class, array(
			'label'=>'assoc.form.new.name',
			'required'=>true,
			'attr' => array(
				'size'=>40,
				'maxlength'=>255,
				'title'=>'assoc.help.name'
			)
		));
		$builder->add('formal_name', TextType::class, array(
			'label'=>'assoc.form.new.formalname',
			'required'=>true,
			'attr' => array(
				'size'=>80,
				'maxlength'=>255,
				'title'=>'assoc.help.formalname'
			)
		));
		$builder->add('faith_name', TextType::class, array(
			'label'=>'assoc.form.new.faithname',
			'required'=>false,
			'attr' => array(
				'size'=>40,
				'maxlength'=>255,
				'title'=>'assoc.help.faithname'
			)
		));
		$builder->add('follower_name', TextType::class, array(
			'label'=>'assoc.form.new.followername',
			'required'=>false,
			'attr' => array(
				'size'=>40,
				'maxlength'=>255,
				'title'=>'assoc.help.followername'
			)
		));
		$builder->add('type', EntityType::class, array(
			'label'=>'assoc.form.new.type',
			'required'=>true,
			'placeholder' => 'assoc.form.select',
			'attr' => array('title'=>'assoc.help.type'),
			'class' => AssociationType::class,
			'choice_translation_domain' => true,
			'choice_label' => 'name',
			'choices' => $types
		));
		$builder->add('motto', TextType::class, array(
			'label'=>'assoc.form.new.motto',
			'required'=>false,
			'attr' => array(
				'size'=>80,
				'title'=>'assoc.help.motto')
		));
		$builder->add('founder', TextType::class, array(
			'label'=>'assoc.form.new.founder',
			'required'=>true,
			'attr' => array(
				'size'=>40,
				'maxlength'=>160,
				'title'=>'assoc.help.founder'
			)
		));
		$builder->add('public', CheckboxType::class, array(
			'label'=>'assoc.form.new.public',
			'attr' => array('title'=>'assoc.help.public'),
			'data' => true
		));
		$builder->add('short_description', TextareaType::class, array(
			'label'=>'assoc.form.description.short',
			'attr' => array('title'=>'assoc.help.shortdesc'),
			'required'=>true,
		));
		$builder->add('description', TextareaType::class, array(
			'label'=>'assoc.form.description.full',
			'attr' => array('title'=>'assoc.help.longdesc'),
			'required'=>true,
		));
		$builder->add('superior', EntityType::class, array(
			'label'=>'assoc.form.new.superior',
			'required'=>false,
			'placeholder' => 'assoc.form.empty',
			'attr' => array('title'=>'assoc.help.type'),
			'class' => Association::class,
			'choice_translation_domain' => true,
			'choice_label' => 'name',
			'choices' => $assocs
		));
		$builder->add('submit', SubmitType::class, array('label'=>'assoc.form.submit'));
	}
}
