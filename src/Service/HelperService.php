<?php

namespace App\Service;

use App\Entity\Activity;
use App\Entity\ActivityReportObserver;
use App\Entity\Battle;
use App\Entity\BattleReportObserver;
use App\Entity\Character;
use App\Entity\Skill;
use App\Entity\SkillType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;


class HelperService {

	/*
	This service exists purely to prevent code duplication and circlic service requiremenets.
	Things that should exist in multiple services but can't due to circlic loading should be here.
	*/

	protected EntityManagerInterface $em;
	protected Geography $geo;
	protected History $history;

	public function __construct(EntityManagerInterface $em, Geography $geo, History $history) {
		$this->em = $em;
		$this->geo = $geo;
		$this->history = $history;
	}

	private function newObserver($type): true|BattleReportObserver|ActivityReportObserver {
		if ($type === 'battle') {
			return new BattleReportObserver;
		}
		if ($type === 'act') {
			return new ActivityReportObserver;
		}
		return true;
	}

	public function addObservers($thing, $report): void {
		if ($thing instanceof Battle) {
			$type = 'battle';
		} elseif ($thing instanceof Activity) {
			$type = 'act';
		}
		$added = new ArrayCollection;
		$someone = null;
		if ($type === 'battle') {
			foreach ($thing->getGroups() as $group) {
				foreach ($group->getCharacters() as $char) {
					if (!$someone) {
						$someone = $char;
					}
					if (!$added->contains($char)) {
						$obs = new BattleReportObserver;
						$this->em->persist($obs);
						$obs->setReport($report);
						$obs->setCharacter($char);
						$added->add($char);
					}
				}
			}
		} elseif ($type === 'act') {
			foreach ($thing->getParticipants() as $part) {
				$char = $part->getCharacter();
				if (!$someone) {
					$someone = $char;
				}
				if (!$added->contains($char)) {
					$obs = $this->newObserver($type);
					$this->em->persist($obs);
					$obs->setReport($report);
					$obs->setCharacter($char);
					$added->add($char);
				}
			}
		}
		$dist = $this->geo->calculateInteractionDistance($someone);
		$nearby = $this->geo->findCharactersNearMe($someone, $dist, false, false, false, true);
		foreach ($nearby as $each) {
			$char = $each['character'];
			if (!$added->contains($char)) {
				$obs = $this->newObserver($type);
				$this->em->persist($obs);
				$obs->setReport($report);
				$obs->setCharacter($char);
				$added->add($char);
			}
		}
		if ($thing->getPlace()) {
			foreach ($thing->getPlace()->getCharactersPresent() as $char) {
				if (!$added->contains($char)) {
					$obs = $this->newObserver($type);
					$this->em->persist($obs);
					$obs->setReport($report);
					$obs->setCharacter($char);
					$added->add($char);
				}
			}
		}
		if ($thing->getSettlement()) {
			foreach ($thing->getSettlement()->getCharactersPresent() as $char) {
				if (!$added->contains($char)) {
					$obs = $this->newObserver($type);
					$this->em->persist($obs);
					$obs->setReport($report);
					$obs->setCharacter($char);
					$added->add($char);
				}
			}
		}
	}

	public function trainSkill(Character $char, SkillType $type=null, $pract = 0, $theory = 0): bool {
		if (!$type) {
			# Not all weapons have skills, this just catches those.
			return false;
		}
		$query = $this->em->createQuery('SELECT s FROM BM2SiteBundle:Skill s WHERE s.character = :me AND s.type = :type ORDER BY s.id ASC')->setParameters(['me'=>$char, 'type'=>$type])->setMaxResults(1);
		$training = $query->getResult();
		if ($pract && $pract < 1) {
			$pract = 1;
		} elseif ($pract) {
			$pract = round($pract);
		}
		if ($theory && $theory < 1) {
			$theory = 1;
		} elseif ($theory) {
			$theory = round($theory);
		}
		if (!$training) {
			echo 'making new skill - ';
			$training = new Skill();
			$this->em->persist($training);
			$training->setCharacter($char);
			$training->setType($type);
			$training->setCategory($type->getCategory());
			$training->setPractice($pract);
			$training->setTheory($theory);
			$training->setPracticeHigh($pract);
			$training->setTheoryHigh($theory);
		} else {
			$training = $training[0];
			echo 'updating skill '.$training->getId().' - ';
			if ($pract) {
				$newPract = $training->getPractice() + $pract;
				$training->setPractice($newPract);
				if ($newPract > $training->getPracticeHigh()) {
					$training->setPracticeHigh($newPract);
				}
			}
			if ($theory) {
				$newTheory = $training->getTheory() + $theory;
				$training->getTheory($newTheory);
				if ($newTheory > $training->getTheoryHigh()) {
					$training->setTheoryHigh($newTheory);
				}
			}
		}
		$training->setUpdated(new \DateTime('now'));
		$this->em->flush();
		return true;
	}

}
