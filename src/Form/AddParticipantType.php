<?php

namespace App\Form;

use App\Entity\Character;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;


class AddParticipantType extends AbstractType {

	public function configureOptions(OptionsResolver $resolver): void {
		$resolver->setDefaults(array(
			'intention'       => 'new_conversation_134',
			'translation_domain' => 'conversations',
			'contacts'	=> []
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options): void {
		$recipients = $options['contacts'];
		$builder->add('contacts', EntityType::class, array(
			'required' => false,
			'multiple'=>true,
			'expanded'=>true,
			'label' => false,
			'placeholder' => 'add.empty',
			'class' => Character::class,
			'choice_label' => 'name',
			'query_builder'=>function(EntityRepository $er) use ($recipients) {
				$qb = $er->createQueryBuilder('c');
				$qb->where('c IN (:recipients)');
				$qb->orderBy('c.name', 'ASC');
				$qb->setParameter('recipients', $recipients);
				return $qb;
			},
		));

		$builder->add('submit', SubmitType::class, array('label'=>'add.submit', 'attr'=>array('class'=>'cmsg_button')));
	}


}
