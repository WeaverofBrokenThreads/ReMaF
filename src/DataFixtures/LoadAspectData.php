<?php

namespace App\DataFixtures;

use App\Entity\AspectType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;


class LoadAspectData extends Fixture {

	private array $positiontypes = array(
		'alcohol',
		'animals',
		'arcane',
		'arts',
		'autumn',
		'badlands',
		'balance',
		'beauty',
		'beginnings',
		'birds',
		'blood',
		'bows',
		'caves',
		'cavalry',
		'chaos',
		'chivalry',
		'coins',
		'cold',
		'commerce',
		'commoners',
		'common sense',
		'conflict',
		'courage',
		'crafts',
		'creation',
		'day',
		'daring',
		'darkness',
		'death',
		'defeat',
		'destruction',
		'discipline',
		'discord',
		'distance',
		'dragons',
		'drinks',
		'dungeons',
		'earth',
		'endings',
		'envy',
		'evil',
		'farming',
		'fertility',
		'fire',
		'first ones',
		'fitness',
		'foresight',
		'forests',
		'fortune',
		'future',
		'gems',
		'good',
		'hate',
		'healing',
		'honor',
		'horizons',
		'humor',
		'hunger',
		'hunting',
		'individuality',
		'infantry',
		'insight',
		'isles',
		'justice',
		'knights',
		'knowledge',
		'land',
		'law',
		'liberty',
		'lies',
		'life',
		'light',
		'lords',
		'luck',
		'malice',
		'marshes',
		'monsters',
		'moon',
		'mortals',
		'mountains',
		'murder',
		'music',
		'nature',
		'neutrality',
		'night',
		'nobility',
		'oceans',
		'order',
		'panic',
		'parties',
		'past',
		'peace',
		'plants',
		'polearms',
		'present',
		'renown',
		'resolution',
		'retribution',
		'revenge',
		'revelry',
		'rivers',
		'roads',
		'ruins',
		'rulers',
		'seas',
		'slaughter',
		'skies',
		'spring',
		'stars',
		'strength',
		'summer',
		'sun',
		'swords',
		'time',
		'trade',
		'travel',
		'truth',
		'ugliness',
		'unarmed',
		'valor',
		'vanity',
		'victory',
		'war',
		'warmth',
		'wanderers',
		'wastelands',
		'water',
		'wealth',
		'weather',
		'weapons',
		'wind',
		'winter',
		'wisdom',
		'zeal',
	);

	public function load(ObjectManager $manager): void {
		foreach ($this->positiontypes as $name) {
			$type = $manager->getRepository(AspectType::class)->findOneBy(['name'=>$name]);
			if (!$type) {
				$type = new AspectType();
				$manager->persist($type);
			}
			$type->setName($name);
			$manager->persist($type);
		}
		$manager->flush();
	}
}
