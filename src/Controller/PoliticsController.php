<?php

namespace App\Controller;

use App\Entity\Action;
use App\Entity\Character;
use App\Entity\Listing;
use App\Entity\Partnership;
use App\Entity\Place;
use App\Entity\PlacePermission;
use App\Entity\RealmPosition;
use App\Entity\Settlement;
use App\Entity\SettlementPermission;
use App\Form\CharacterSelectType;
use App\Form\ListingType;
use App\Form\PartnershipsType;
use App\Form\PrisonersManageType;
use App\Service\ActionManager;
use App\Service\CharacterManager;
use App\Service\Dispatcher\Dispatcher;
use App\Service\GameRequestManager;
use App\Service\History;
use App\Service\Politics;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class PoliticsController extends AbstractController {

	private Dispatcher $disp;
	private EntityManagerInterface $em;
	private History $hist;
	private Politics $pol;
	private TranslatorInterface $trans;
	
	private $hierarchy=array();
	
	public function __construct(Dispatcher $disp, EntityManagerInterface $em, History $hist, Politics $pol, TranslatorInterface $trans) {
		$this->disp = $disp;
		$this->em = $em;
		$this->hist = $hist;
		$this->pol = $pol;
		$this->trans = $trans;
	}
	
	#[Route ('/politics', name:'maf_politics')]
	public function indexAction() {
		$character = $this->disp->gateway();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		return $this->render('Politics/politics.html.twig');
	}

	#[Route ('/politics/realms', name:'maf_politics_realms')]
	public function realmsAction() {
		$character = $this->disp->gateway();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		return $this->render('Politics/realms.html.twig');
	}

	#[Route ('/politics/relations', name:'maf_politics_relations')]
	public function relationsAction() {
		$character = $this->disp->gateway();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		return $this->render('Politics/relations.html.twig');
	}

	#[Route ('/politics/assocs', name:'maf_politics_assocs')]
	public function associationsAction() {
		$character = $this->disp->gateway();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		return $this->render('Politics/assocs.html.twig');
	}

	#[Route ('/politics/hierarchy', name:'maf_politics_hierarchy')]
	public function hierarchyAction() {
		$character = $this->disp->gateway();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$this->addToHierarchy($character);

	   	$descriptorspec = array(
			   0 => array("pipe", "r"),  // stdin
			   1 => array("pipe", "w"),  // stdout
			   2 => array("pipe", "w") // stderr
			);

	   	$process = proc_open('dot -Tsvg', $descriptorspec, $pipes, '/tmp', array());

	   	if (is_resource($process)) {
	   		$dot = $this->renderView('Politics/hierarchy.dot.twig', array('hierarchy'=>$this->hierarchy, 'me'=>$character));

	   		fwrite($pipes[0], $dot);
	   		fclose($pipes[0]);

	   		$svg = stream_get_contents($pipes[1]);
	   		fclose($pipes[1]);

	   		$return_value = proc_close($process);
	   	}

		return $this->render('Politics/hierarchy.html.twig', [
			'svg'=>$svg
		]);
	}

	private function addToHierarchy(Character $character) {
		if (!isset($this->hierarchy[$character->getId()])) {
			$this->hierarchy[$character->getId()] = $character;
			if ($liege = $character->findLiege()) {
				if (!($liege instanceof Character)) {
					foreach ($liege as $one) {
						$this->addToHierarchy($one);
					}
				} else {
					$this->addToHierarchy($liege);
				}
			}
			foreach ($character->findVassals() as $vassal) {
				$this->addToHierarchy($vassal);
			}
		}
	}

	#[Route ('/politics/vassals', name:'maf_politics_vassals')]
	public function vassalsAction() {
		$character = $this->disp->gateway();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		return $this->render('Politics/vassals.html.twig', [
			'vassals'=>$character->findVassals()
		]);
	}

	#[Route ('/politics/disown/{vassal}', name:'maf_politics_disown', requirements:['vassal'=>'\d+'])]
	public function disownAction(Request $request, Character $vassal) {
		$character = $this->disp->gateway();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		if (!$character->findVassals()->contains($vassal)) {
			throw new AccessDeniedHttpException("error.noaccess.vassal");
		}

		$form = $this->createFormBuilder()
				->add('submit', 'submit', array('label'=>$this->trans->trans('vassals.disown.submit', array(), "politics")))
				->getForm();
		$form->handleRequest($request);
		if ($form->isSubmitted() && $form->isValid()) {
			$this->pol->disown($vassal);
			$em = $this->em;
			$em->flush();
			return $this->render('Politics/disown.html.twig', [
				'vassal'=>$vassal,
				'success'=>true
			]);
		}

		return $this->render('Politics/disown.html.twig', [
			'vassal'=>$vassal,
			'form'=>$form->createView()
		]);
	}

	#[Route ('/politics/oath/offer', name:'maf_politics_oath_offer')]
	public function offerOathAction(GameRequestManager $grm, Request $request) {
		$character = $this->disp->gateway('hierarchyOfferOathTest');
		if (!$character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$em = $this->em;
		$others = $this->disp->getActionableCharacters();
		$options = [];
		if ($character->getInsideSettlement()) {
			$options[] = $character->getInsideSettlement();
		}
		if ($character->getInsidePlace()) {
			$options[] = $character->getInsidePlace();
		}
		foreach ($others as $other) {
			$otherchar = $other['character'];
			if ($otherchar->getOwnedSettlements()){
				foreach ($otherchar->getOwnedSettlements() as $settlement) {
					if (!in_array($settlement, $options)) {
						$options[] = $settlement;
					}
				}
			}
			if ($otherchar->getPositions()) {
				foreach ($otherchar->getPositions() as $pos) {
					if (($pos->getRuler() || $pos->getHaveVassals()) && !in_array($pos, $options)) {
						$options[] = $pos;
					}
				}
			}
			if ($otherchar->getOwnedPlaces()) {
				foreach ($otherchar->getOwnedPlaces() as $place) {
					$type = $place->getType();
					if ($type->getName() != 'embassy' && $type->getVassals()) {
						$options[] = $place;
					}
				}
			}
			if ($otherchar->getAmbassadorships()) {
				foreach ($otherchar->getAmbassadorships() as $place) {
					$options[] = $place;
				}
			}
		}
		$form = $this->createFormBuilder()
			->add('liege', ChoiceType::class, [
			'label'=>'oath.offerto',
			'required'=>true,
			'empty_value'=>'oath.choose',
			'translation_domain'=>'politics',
			'choices'=>$options,
			'choices_as_values'=>true,
			'choice_label' => function ($choice, $key, $value) {
				if ($choice instanceof Settlement) {
					if ($choice->getRealm()) {
						return $choice->getName().' ('.$choice->getRealm()->getName().')';
					} else {
						return $choice->getName();
					}
				}
				if ($choice instanceof RealmPosition) {
					if ($choice->getName() == 'ruler') {
						return 'Ruler of '.$choice->getRealm()->getName();
					}
					return $choice->getName().' ('.$choice->getRealm()->getName().')';
				}
				if ($choice instanceof Place) {
					if ($choice->getRealm()) {
						if ($choice->getHouse()) {
							return $choice->getName().' - '.ucfirst($choice->getType()->getName()).' of '.$choice->getHouse()->getName().' in '.$choice->getRealm()->getName();
						} else {
							return $choice->getName().' - '.ucfirst($choice->getType()->getName()).' in '.$choice->getRealm()->getName();
						}
					} elseif ($choice->getHouse()) {
						return $choice->getName().' - '.ucfirst($choice->getType()->getName()).' of '.$choice->getHouse()->getName();
					} else {
						return $choice->getName().' - '.ucfirst($choice->getType()->getName());
					}
				}
				return 'Other';
			},
			'group_by' => function($choice, $key, $value) {
				if ($choice instanceof Settlement) {
					return 'Settlements';
				}
				if ($choice instanceof RealmPosition) {
					if ($choice->getRuler()) {
						return 'Rulers';
					}
					return 'Other Positions';
				}
				if ($choice instanceof Place) {
					return 'Places';
				}
				return 'Other';
			},
		])->add('message', TextareaType::class, [
			'label' => 'oath.message',
			'translation_domain'=>'politics',
			'required' => true
		])->add('submit', SubmitType::class, [
			'label'=>'oath.submit',
			'translation_domain'=>'politics',
		])->getForm();

		$form->handleRequest($request);
		if ($form->isSubmitted() && $form->isValid()) {
			$data = $form->getData();
			$grm->newOathOffer($character, $data['message'], $data['liege']);
			$this->addFlash('success', $this->trans->trans('oath.offered', array(), 'politics'));
			return $this->redirectToRoute('maf_politics_relations');
		}

		return $this->render('Politics/offerOath.html.twig', [
			'form' => $form->createView(),
		]);
	}

	#[Route ('/politics/oath/break', name:'maf_politics_oath_break')]
	public function breakoathAction(Request $request) {
		$character = $this->disp->gateway('hierarchyIndependenceTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		if ($request->isMethod('POST')) {
			$this->pol->breakoath($character);
			$this->em->flush();
			$this->addFlash('success', $this->trans->trans('oath.broken', array(), 'politics'));
			return $this->redirectToRoute('maf_politics_relations');
		}

		return $this->render('Politics/breakoath.html.twig');
	}

	#[Route ('/politics/successor', name:'maf_politics_successor')]
	public function successorAction(Request $request) {
		$character = $this->disp->gateway('InheritanceSuccessorTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$em = $this->em;
		$others = $this->disp->getActionableCharacters();
		$availableLords = array();
		foreach ($others as $other) {
			if (!$other['character']->isNPC() && $other['character'] != $character->getSuccessor()) {
				$availableLords[] = $other['character'];
			}
		}
		foreach ($character->getUser()->getCharacters() as $mychar) {
			if (!$mychar->isNPC() && !$mychar->getRetired() && $mychar != $character && $mychar->isAlive() && $mychar != $character->getSuccessor()) {
				$availableLords[] = $mychar;
			}
		}
		foreach ($character->getPartnerships() as $partnership) {
			$mychar = $partnership->getOtherPartner($character);
			if (!$mychar->isNPC() && !$mychar->getRetired() && $mychar != $character && $mychar->isAlive() && $mychar != $character->getSuccessor()) {
				$availableLords[] = $mychar;
			}
		}

		$form = $this->createForm(CharacterSelectType::class, null, [
			'empty' => 'successor.choose',
			'characters'=>$availableLords,
			'label' => 'successor.submit',
			'submit' => 'successor.submit',
			'domain' => 'politics'
		]);
		$form->handleRequest($request);
		if ($form->isSubmitted() && $form->isValid()) {
			$data = $form->getData();
			$successor = $data['target'];

			if ($character->getSuccessor()) {
				$this->hist->logEvent(
					$character->getSuccessor(),
					'politics.successor.removed',
					array('%link-character%'=>$character->getId()),
					History::MEDIUM
				);

				// FIXME: can $successor be NULL? (i.e. setting no successor) => in such case, this throws an exception
				// 		 (and the one in the else as well)
				$this->hist->logEvent(
					$character,
					'politics.successor.changed',
					array('%link-character-1%'=>$character->getSuccessor()->getId(), '%link-character-2%'=>$successor->getId()),
					History::LOW
				);
			} else {
				$this->hist->logEvent(
					$character,
					'politics.successor.set',
					array('%link-character%'=>$successor->getId()),
					History::LOW
				);
			}

			$character->setSuccessor($successor);

			// message to new successor
			$this->hist->logEvent(
				$successor,
				'politics.successor.new',
				array('%link-character%'=>$character->getId()),
				History::MEDIUM
			);

			$em->flush();

			#TODO: Convert this to a redirect and flash.
			return $this->render('Politics/successor.html.twig', [
				'success'=>true
			]);
		}

		return $this->render('Politics/successor.html.twig', [
			'form'=>$form->createView()
		]);
	}
	
	#[Route ('/politics/partner/{type}', name:'maf_politics_partners', defaults: ['type'=>null])]
	public function partnersAction(Request $request, $type=null) {
		$character = $this->disp->gateway('partnershipsTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$em = $this->em;
		$newavailable = false;
		$formOldView=null; $formNewView=null;

		if ($type==null || $type=='old') {
			$query = $em->createQuery('SELECT DISTINCT p FROM App:Partnership p JOIN p.partners c WHERE c = :me AND p.end_date IS NULL');
			$query->setParameter('me', $character);
			$currentRelations = $query->getResult();
			$formOld = $this->createForm(PartnershipsType::class, null, ['me'=>$character, 'newpartners'=>false, 'others'=>$currentRelations]);
			$formOldView = $formOld->createView();
		}
		if ($type==null || $type=='new') {
			// FIXME: shouldn't this be in the dispatcher?
			$others = $this->disp->getActionableCharacters();
			$choices=array();
			if ($character->getPartnerships()) {
				foreach ($character->getPartnerships() as $partnership) {
					if (!$partnership->getEndDate()) {
						$existingpartners[] = $partnership->getOtherPartner($character);
					}
				}
			}
			foreach ($others as $other) {
				$char = $other['character'];
				if ($character->getNonHeteroOptions()) {
					if (!$char->isNPC() && $char->isActive(true) && !in_array($char, $existingpartners)) {
						$choices[$char->getId()] = $char->getName();
					}
				} else {
					if (!$char->isNPC() && $char->isActive(true) && !in_array($char, $existingpartners) && $char->getMale() != $character->getMale()) {
						$choices[$char->getId()] = $char->getName();
					}
				}
				// TODO: filter out existing partnerships
			}
			$formNew = $this->createForm(PartnershipsType::class, null, ['me'=>$character, 'newpartners'=>true, 'others'=>$choices]);
			$formNewView = $formNew->createView();
			if (!empty($choices)) {
				$newavailable=true;
			}
		}

		/* TODO: This is a complete rework of how these were handled. It WILL need testing, and MAY need redesigned. --Andrew 20230524 */

		$formOld->handleRequest($request);
		if ($formOld->isSubmitted() && $formOld->isValid()) {
			$data = $formOld->getData();

			foreach ($data['partnership'] as $id=>$change) {
				if (!$change) continue;
				$relation = $em->getRepository(Partnership::class)->find($id);
				switch ($change) {
					case 'public':
						// TODO: event posting
						$relation->setPublic(true);
						break;
					case 'nosex':
						$relation->setWithSex(false);
						break;
					case 'cancel':
						// TODO: event posting
						$relation->setActive(false);
						$relation->setEndDate(new \DateTime("now"));
						break;
					case 'withdraw':
						// TODO: notify other
						$em->remove($relation);
						break;
					case 'accept':
						$relation->setActive(true);
						$relation->setStartDate(new \DateTime("now"));
						if (in_array($relation->getType(), array("marriage", "engagement")) && $relation->getPublic()) {
							if ($relation->getType()=="marriage") {
								$priority = History::HIGH;
							} else {
								$priority = History::MEDIUM;
							}
							foreach ($relation->getPartners() as $partner) {
								$other = $relation->getOtherPartner($partner);
								$this->hist->logEvent(
									$partner,
									'event.character.public.'.$relation->getType(),
									array('%link-character%'=>$other->getId()),
									$priority, true
								);
							}
						} else {
							foreach ($relation->getPartners() as $partner) {
								$other = $relation->getOtherPartner($partner);
								$this->hist->logEvent(
									$partner,
									'event.character.secret.'.$relation->getType(),
									array('%link-character%'=>$other->getId()),
									HISTORY::MEDIUM, false
								);
							}
						}
						break;
					case 'reject':
						// inform the other
						$other = $relation->getOtherPartner($character);
						$this->hist->logEvent(
							$other,
							'event.character.rejected.'.$relation->getType(),
							array('%link-character%'=>$character->getId()),
							HISTORY::HIGH, false, 20
						);
						$em->remove($relation);
						break;
				}
			}
			$em->flush();

			return $this->redirectToRoute('maf_politics_partners');
		}
		$formNew->handleRequest($request);
		if ($formNew->isSubmitted() && $formNew->isSubmitted()) {
			$data = $formNew->getData();

			$partner = $em->getRepository(Character::class)->find($data['partner']);
			$relation = new Partnership;
			$relation->setType($data['type']);
			$relation->setPublic($data['public']);
			$relation->setWithSex($data['sex']);
			$relation->setActive(false);
			$relation->setInitiator($character);
			$relation->setPartnerMayUseCrest($data['crest']);
			$relation->addPartner($character);
			$relation->addPartner($partner);
			$em->persist($relation);

			// inform the other
			$this->hist->logEvent(
				$partner,
				'event.character.proposed.'.$relation->getType(),
				array('%link-character%'=>$character->getId()),
				HISTORY::HIGH, false, 20
			);
			$em->flush();

			return $this->redirectToRoute('maf_politics_partners');
		}
		return $this->render('Politics/partners.html.twig', [
			'newavailable' => $newavailable,
			'form_old'=>$formOldView,
			'form_new'=>$formNewView
		]);
	}
	
	#[Route ('/politics/lists', name:'maf_politics_lists')]
	public function listsAction() {
		$character = $this->disp->gateway();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		return $this->render('Politics/lists.html.twig', [
			'listings' => $character->getUser()->getListings(),
		]);
	}

	#[Route ('/politics/list/{id}', name:'maf_politics_list', requirements:['id'=>'\d+'])]
	public function listAction($id, Request $request) {
		$character = $this->disp->gateway();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$em = $this->em;
		$using = false;
		$usingPlaces = false;

		if ($id>0) {
			$listing = $character->getUser()->getListings()->filter(
				function($entry) use ($id) {
					return ($entry->getId()==$id);
				}
			)->first();
			if (!$listing) {
				throw $this->createNotFoundException('error.notfound.listing');
			}
			$can_delete = true;
			if ($id > 0) {
				$locked_reasons = array();
				if (!$listing->getDescendants()->isEmpty()) {
					$can_delete = false;
					$locked_reasons[] = "descendants";
				}
				$using = $em->getRepository(SettlementPermission::class)->findBy(['listing'=>$listing]);
				if ($using && !empty($using)) {
					$can_delete = false;
					$locked_reasons[] = "used";
				}
				$usingPlaces = $em->getRepository(PlacePermission::class)->findBy(['listing'=>$listing]);
				if ($usingPlaces && !empty($usingPlaces)) {
					$can_delete = false;
					$locked_reasons[] = "used";
				}
			}
			$is_new = false;
		} else {
			$listing = new Listing;
			$listing->setName('new list'); // this prevents SQL errors below, somehow the required for name doesn't catch
			$can_delete = false;
			$locked_reasons = array();
			$is_new = true;
		}

		$available = array();
		foreach ($character->getUser()->getListings() as $l) {
			if ($listing != $l) {
				$available[] = $l->getId();
			}
		}

		$form = $this->createForm(ListingType::class, $listing, ['em'=>$em, 'available'=>$available]);
		$form->handleRequest($request);
		if ($form->isSubmitted() && $form->isValid()) {
			$data = $form->getData();

			if ($id==0) {
				$listing->setOwner($character->getUser());
				$listing->setCreator($character);
				$em->persist($listing);
			}
			if ($listing->getInheritFrom()) {
				// check for loops
				$seen = new ArrayCollection;
				$seen->add($listing);
				$current = $listing;
				while ($parent = $current->getInheritFrom()) {
					if ($seen->contains($parent)) {
						// loop!
						$listing->setInheritFrom(null);
						// FIXME: is never actually displayed due to the redirect below :-(
						$form->addError(new FormError("listing.loop"));
					}
					$seen->add($parent);
					$current = $parent;
				}
			}
			foreach ($listing->getMembers() as $member) {
				if (!$member->getId()) {
					if ($id==0) {
						$member->setListing($listing);
					}
					$em->persist($member);
				} elseif (!$member->getTargetRealm() && !$member->getTargetCharacter()) {
					$listing->removeMember($member);
					$em->remove($member);
				}
			}
			$em->flush();
			$this->addFlash('success', $this->trans->trans('lists.updated', array(), 'politics'));
			return $this->redirectToRoute('maf_politics_list', array('id'=>$listing->getId()));
		}

		if ($can_delete) {
			$form_delete = $this->createFormBuilder()
				->add('submit', 'submit', array(
					'label'=>'lists.delete.submit',
					'translation_domain' => 'politics'
					))
				->getForm();
			$form_delete->handleRequest($request);
			if ($form_delete->isSubmitted() && $form_delete->isValid()) {
				$name = $listing->getName();
				foreach ($listing->getMembers() as $member) {
					$em->remove($member);
				}
				$em->remove($listing);
				$em->flush();
				$this->addFlash('notice', $this->trans->trans('lists.delete.done', array("%name%"=>$name), 'politics'));
				return $this->redirectToRoute('maf_politics_lists');
			}
		}

		$used_by = array();
		if ($using) foreach ($using as $perm) {
			if ($perm->getSettlement()) {
				if (!in_array($perm->getSettlement(), $used_by)) {
					$used_by[] = $perm->getSettlement();
				}
			} elseif ($perm->getOccupiedSettlement()) {
				if (!in_array($perm->getOccupiedSettlement(), $used_by)) {
					$used_by[] = $perm->getOccupiedSettlement();
				}
			}
		}
		$usedByPlaces = [];
		if ($usingPlaces) foreach ($usingPlaces as $perm) {
			if ($perm->getPlace()) {
				if (!in_array($perm->getPlace(), $usedByPlaces)) {
					$usedByPlaces[] = $perm->getPlace();
				}
			} elseif ($perm->getOccupiedSettlement()) {
				if (!in_array($perm->getOccupiedPlace(), $usedByPlaces)) {
					$usedByPlaces[] = $perm->getOccupiedPlace();
				}
			}
		}

		return $this->render('Politics/list.html.twig', [
			'listing' => $listing,
			'used_by' => $used_by,
			'used_by_places' => $usedByPlaces,
			'can_delete' => $can_delete,
			'locked_reasons' => $locked_reasons,
			'is_new' => $is_new,
			'form' => $form->createView(),
			'form_delete' => $can_delete?$form_delete->createView():null
		]);
	}
	
	#[Route ('/politics/prisoners', name:'maf_politics_prisoners')]
	public function prisonersAction(ActionManager $actMan, CharacterManager $charMan, Request $request) {
		$character = $this->disp->gateway('personalPrisonersTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		$results = array();
		$others = $this->disp->getActionableCharacters();
		$prisoners = $character->getPrisoners();
		$form = $this->createForm(PrisonersManageType::class, null, ['prisoners' => $prisoners, 'others' => $others]);
		$form->handleRequest($request);
		if ($form->isSubmitted() && $form->isValid()) {
			$data = $form->getData();
			$change_others = false;

			foreach ($data['prisoners'] as $id=>$do) {
				$prisoner = $prisoners[$id];
				switch ($do['action']) {
					case 'free':
						$prisoner->setPrisonerOf(null);
						$character->removePrisoner($prisoner);
						$this->hist->logEvent(
							$prisoner,
							'event.character.prison.free',
							null,
							History::HIGH, true, 30
						);
						$change_others = true;
						$results[] = array('prisoner'=>$prisoner, 'action'=>'free');
						break;
					case 'execute':
						if ($do['method']) {
							$prisoner->setPrisonerOf(null);
							$character->removePrisoner($prisoner);
							$this->hist->logEvent(
								$character,
								'event.character.prison.killer.'.$do['method'],
								array('%link-character%'=>$prisoner->getId()),
								History::MEDIUM, true
							);
							$charMan->kill($prisoner, $character, false, 'prison.kill.'.$do['method']);
							$results[] = array('prisoner'=>$prisoner, 'action'=>'execute');
						}
						break;
					case 'assign':
						if ($data['assignto'] && !$prisoner->hasAction('personal.prisonassign')) {
							$prisoner->setPrisonerOf($data['assignto']);
							$character->removePrisoner($prisoner);
							$data['assignto']->addPrisoner($prisoner);

							// 2 hour blocking action
							$act = new Action;
							$act->setType('personal.prisonassign')->setCharacter($prisoner);
							$complete = new \DateTime("now");
							$complete->add(new \DateInterval("PT2H"));
							$act->setComplete($complete);
							$act->setBlockTravel(false);
							$actMan->queue($act);

							$this->hist->logEvent(
								$prisoner,
								'event.character.prison.assign',
								array('%link-character%'=>$data['assignto']->getId()),
								History::MEDIUM, true, 20
							);
							$this->hist->logEvent(
								$data['assignto'],
								'event.character.prison.received',
								array('%link-character-1%'=>$character->getId(), '%link-character-2%'=>$prisoner->getId()),
								History::MEDIUM, true, 20
							);
							$results[] = array('prisoner'=>$prisoner, 'action'=>'assign', 'target'=>$data['assignto']);
						}
						break;
				}
			}
			$this->em->flush();
			if ($change_others) {
				$others = $this->disp->getActionableCharacters();
			}
			$form = $this->createForm(PrisonersManageType::class, null, ['prisoners' => $prisoners, 'others' => $others]);
		}

		return $this->render('Politics/prisoners.html.twig', [
			'form' => $form->createView(),
			'results' => $results
		]);
	}

	#[Route ('/politics/claims', name:'maf_politics_claims')]
	public function claimsAction(Request $request) {
		$character = $this->disp->gateway('personalClaimsTest');
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		return $this->render('Politics/claims.html.twig', [
			'claims'=>$character->getSettlementClaims()
		]);
	}
	
	#[Route ('/politics/claim/settlement/{settlement}', name:'maf_politics_claim_settlement', requirements:['settlement'=>'\d+'])]
	public function claimaddAction(Settlement $settlement) {
		$character = $this->disp->gateway();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		if (!$settlement) {
			throw $this->createNotFoundException('error.notfound.settlement');
		}
		$already = false;
		foreach ($character->getSettlementClaims() as $claim) {
			if ($claim->getSettlement() == $settlement) {
				$already = true;
				break;
			}
		}

		if ($already) {
			$this->addFlash('error', $this->trans->trans('claim.already', array(), 'politics'));
		} else {
			$heralds = $character->getAvailableEntourageOfType('Herald');
			if ($heralds->count() > 0) {
				$em = $this->em;
				$em->remove($heralds->first());
				$this->pol->addClaim($character, $settlement);
				$this->hist->logEvent(
					$settlement,
					'event.settlement.claim.added',
					array('%link-character%'=>$character->getId()),
					History::MEDIUM, true, 90
				);
				$em->flush();
				$this->addFlash('success', $this->trans->trans('claim.added', array(), 'politics'));
			} else {
				$this->addFlash('error', $this->trans->trans('claim.noherald', array(), 'politics'));
			}
		}

		return $this->redirectToRoute('maf_settlement', array('id'=>$settlement->getId()));
	}

	#[Route ('/politics/claim/settlement/cancel/{settlement}', name:'maf_politics_claim_settlement_cancel', requirements:['settlement'=>'\d+'])]
	public function claimcancelAction(Settlement $settlement) {
		$character = $this->disp->gateway();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

		if (!$settlement) {
			throw $this->createNotFoundException('error.notfound.settlement');
		}

		if ($this->pol->removeClaim($character, $settlement)) {
			$this->em->flush();
			$this->addFlash('success', $this->trans->trans('claim.cancelled', array(), 'politics'));
			$this->hist->logEvent(
				$settlement,
				'event.settlement.claim.cancelled',
				array('%link-character%'=>$character->getId()),
				History::MEDIUM, true, 90
			);
			$this->em->flush();
		} else {
			$this->addFlash('error', $this->trans->trans('claim.donthave', array(), 'politics'));
		}

		return $this->redirectToRoute('maf_settlement', array('id'=>$settlement->getId()));
	}

}
