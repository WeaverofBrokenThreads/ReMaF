<?php

namespace App\Service;

use App\Entity\Association;
use App\Entity\Character;
use App\Entity\GameRequest;
use App\Entity\House;
use App\Entity\Place;
use App\Entity\Realm;
use App\Entity\RealmPosition;
use App\Entity\Settlement;
use App\Entity\Soldier;
use App\Entity\EquipmentType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Exception;

class GameRequestManager {

	/* This is the GameRequest Manager file, responsible for parsing any and all player to player requests in the game, creating, storing, deleting, etc.
	While the bulk of a GameRequest is setup to have foreign key constraints in the database, meaning it's relatively robust, certain parts of it do not.
	Each field will be covered here, so it's both easy to look things up, and so you know what everything is meant to do.
	These are listed in the format you need to type to get/set them, with descriptions following.

			STANDARD INFORMATION
		Id 			-> Unique database ID. Incrememnts from 1, perpetually. Created automatically.
		Type			-> String. Type of request. Unlike other types, accepts any input. Only set by server. Mandatory.
		Created			-> Datetime. When the request was made. Mandatory.
		Expires			-> Datetime. When the request expires. Optional.
		NumberValue		-> Float. For numeric values. Optional.
		StringValue		-> String. For text values. Optional.
		Subject			-> String. The subject of the request. Some will be automated, others not.
		Text			-> Text. The body of a message to accompany a request. Optional.
		Accepted		-> Boolean. Stores whether the request was accepted or not. Defaults to FALSE because Doctrine is buggy (or the PDO is, or PHP is, depending on who you ask).
		Rejected		-> Tracks whether or not a requst has been rejected. Once rejected (or accepted and expired) a request will be purged from teh database.

			REQUESTOR INFORMATION -- Who/what made the request. Only one should be set, as appropriate. Reverses as "Requests".
		FromCharacter		-> Character.
		FromSettlement		-> Settlement.
		FromRealm		-> Realm.
		FromHouse		-> House.
		FromPlace		-> Place.
		FromPosition		-> Realm Position.

			REQUESTEE INFORMATION -- Who/what is a request is made to. Only one should be set, as appropriate. Reverses as "RelatedRequests".
		ToCharacter		-> Character.
		ToSettlement		-> Settlement.
		ToRealm			-> Realm.
		ToHouse			-> House.
		ToPlace			-> Place.
		ToPosition		-> Realm Position.

			REQUESTED INFORMATION -- Who/what is being requested. For sanity's sake, just set one, unless you're feeling brave. Reverses as "PartOfRequests".
		IncludeCharacter	-> Character.
		IncludeSettlement	-> Settlement.
		IncludeRealm		-> Realm.
		IncludeHouse		-> House.
		IncludePlace		-> Place.
		IncludePosition		-> Realm Position.
		IncludeSoldiers		-> Soldiers. Array.
		IncludeEquipment	-> Equipment. Does not reverse.

	It is highly recommended that if you expand this file, use the existing code as a guide for how to make new requests.

	For simplicity of use, the only other service this file should interact with is Doctrine's Entity Manager. This ensures that you can load this service into any other service without creating a dependency loop.
	All acceptance/refusal logic should be handled in the GameRequest Controller. In short the GameRequest logic is as follows:

		1. User inputs the data for the request for a form and submits it from a controller route.
		2. That route verifies the data submitted is accurate and then submits it to this Service.
		3. This service builds the GameRequest and stores it in the database.
		4. Another route, likely the maf_gamerequest_manage route allows the receiving user to interact with pending/active requests.
		5. That controller presents the approve/deny actions to the user.
		6. When a user accepts/denies a request, it is handled by the GameRequest Controller, either by the maf_gamerequest_approve or the maf_gamerequest_deny routes, respectively.
		   These routes verify the user has authority to handle that request, carry out all actions of the request, and reroute the user to the appropriate page afterwards.
		7. When an approved request reaches it's expiration date OR a week after a denied request was denied has been reached, GameRunner will purge that request from the database on the next hourly turn.

	If you need more detailed information on these, contact a M&F developer--we recommend Andrew. */

	public function __construct(
		private EntityManagerInterface $em) {
	}

	public function findHouseApplicationRequests(Character $char) {
		if ($char->getHouse() && $char->getHouse()->getHead() === $char) {
			$queryString = 'SELECT r FROM App\Entity\GameRequest r WHERE r.to_house = :house';
			$query = $this->em->createQuery($queryString);
			$query->setParameters(['house'=>$char->getHouse()->getId()]);
			try {
				# We try/catch this because doctrine doesn't like to return null. By not like, I mean it won't return null on this type of query.
				return $query->getResult();
			} catch(Exception) {
				# If there's an exception, we drop it--an exception here is Doctrine complaining it can't return null--and return null.
				return null;
			}
		} else {
			return null;
		}
	}

	public function findAllManageableRequests(Character $char, $accepted = false) {
		# TODO: When we have multiple ranks/roles within a single org that can manage different requests, we'll have to build that analysis into the SQL query we make here.

		# Prepare string and param set.
		$queryString = 'SELECT r FROM App\Entity\GameRequest r WHERE ';
		$innerString = 'r.to_character = :char';
		$params = ['char'=>$char];

		# Build a list of all realms we are in, using their IDs.
		$realms = $char->findRealms();
		$realmIDs =  [];
		foreach ($realms as $realm) {
			foreach ($realm->findRulers() as $ruler) {
				if ($char === $ruler) {
					$realmIDs[] = $realm->getId();
				}
			}
		}
		if (!empty($realmIDs)) {
			$innerString .= ' OR r.to_realm IN (:realms)';
			$params['realms'] = $realmIDs;
		}

		# Build a list of all settlements we own, using their IDs.
		$settlementIDs = [];
		foreach ($char->getOwnedSettlements() as $settlement) {
			$settlementIDs[] = $settlement->getId();
		}
		if (!empty($settlementIDs)) {
			$innerString .= ' OR r.to_settlement IN (:settlements)';
			$params['settlements'] = $settlementIDs;
		}

		# Build a list of all places we own, using their IDs.
		$placeIDs = [];
		foreach ($char->getOwnedPlaces() as $place) {
			$placeIDs[] = $place->getId();
		}
		if (!empty($placeIDs)) {
			$innerString .= ' OR r.to_place IN (:places)';
			$params['places'] = $placeIDs;
		}

		# Check if we're in a house, and if we are, check who the head of it is. If we are, grab the ID.
		if ($char->getHouse() && $char->getHouse()->getHead() === $char) {
			$houseID = $char->getHouse()->getId();
			$innerString .= ' OR r.to_house = :house';
			$params['house'] = $houseID;
		}

		# Build a list of all positions we hold, using their IDs.
		$positionIDs = [];
		foreach ($char->getPositions() as $pos) {
			$positionIDs[] = $pos->getId();
		}
		if (!empty($positionIDs)) {
			$innerString .= ' OR r.to_position IN (:positions)';
			$params['positions'] = $positionIDs;
		}

		# Find all associations we can manage.
		$assocIDs = [];
		foreach ($char->getAssociationMemberships() as $mbr) {
			$rank = $mbr->getRank();
			if ($rank) {
				if($rank->getOwner() || $rank->getManager()) {
					$assocIDs[] = $mbr->getAssociation()->getId();
				}
			}
		}
		if (!empty($assocIDs)) {
			$innerString .= ' OR r.to_association IN (:assocs)';
			$params['assocs'] = $assocIDs;
		}

		# Condense the strings so far back into one chunk.
		$queryString .= '('.$innerString.')';

		# Add filter for not yet accepted and already accepted.
		# Not accepted are those we haven't reponded to, while accepted is those we can cancel still.
		if (!$accepted) {
			$queryString .= ' AND r.accepted = FALSE AND r.rejected = FALSE';
		} else {
			$queryString .= ' AND r.accepted = TRUE';
		}

		# Build the query and parameters.
		$query = $this->em->createQuery($queryString);
		$query->setParameters($params);

		try {
			# We try/catch this because doctrine doesn't like to return null. By not like, I mean it won't return null on this type of query.
			return $query->getResult();
		} catch(Exception) {
			# If there's an exception, we drop it--an exception here is Doctrine complaining it can't return null--and return null.
			return null;
		}
	}

	/* THE FOLLOWING IS PROVIDED FOR TEMPLATING PURPOSES ONLY. For performance reasons do not use this function to actually generate a request.
	Use a situational method, like "newRequestFromCharacterToHouse", or make a new situational method if one doesn't exist.*/

	public function makeRequest($type, $expires = null, $numberValue = null, $stringValue = null, $subject = null, $text = null, ?Character $fromChar = null, ?Settlement $fromSettlement = null, ?Realm $fromRealm = null, ?House $fromHouse = null, ?Place $fromPlace=null, ?RealmPosition $fromPos = null, ?Character $toChar = null, ?Settlement $toSettlement = null, ?Realm $toRealm = null, ?House $toHouse = null, ?Place $toPlace = null, ?RealmPosition $toPos = null, ?Character $includeChar = null, ?Settlement $includeSettlement = null, ?Realm $includeRealm = null, ?House $includeHouse = null, ?Place $includePlace=null, ?RealmPosition $includePos = null, ?Soldier $includeSoldiers = null, ?EquipmentType $includeEquipment = null) {
		$GR = new GameRequest();
		$this->em->persist($GR);
		$GR->setType($type);
		$GR->setCreated(new \DateTime("now"));
		$GR->setAccepted(FALSE);
		$GR->setRejected(FALSE);
		if ($expires) {
			$GR->setExpires($expires);
		}
		if ($numberValue) {
			$GR->setNumberValue($numberValue);
		}
		if ($stringValue) {
			$GR->setStringValue($stringValue);
		}
		if ($subject) {
			$GR->setSubject($subject);
		}
		if ($text) {
			$GR->setText($text);
		}
		if ($fromChar) {
			$GR->setFromCharacter($fromChar);
		}
		if ($fromSettlement) {
			$GR->setFromSettlement($fromSettlement);
		}
		if ($fromRealm) {
			$GR->setFromRealm($fromRealm);
		}
		if ($fromHouse) {
			$GR->setFromHouse($fromHouse);
		}
		if ($fromPlace) {
			$GR->setFromPlace($fromPlace);
		}
		if ($fromPos) {
			$GR->setFromPosition($fromPos);
		}
		if ($toChar) {
			$GR->setToCharacter($toChar);
		}
		if ($toSettlement) {
			$GR->setToSettlement($toSettlement);
		}
		if ($toRealm) {
			$GR->setToRealm($toRealm);
		}
		if ($toHouse) {
			$GR->setToHouse($toHouse);
		}
		if ($toPlace) {
			$GR->setToPlace($toPlace);
		}
		if ($toPos) {
			$GR->setToPosition($toPos);
		}
		if ($includeChar) {
			$GR->setIncludeCharacter($includeChar);
		}
		if ($includeSettlement) {
			$GR->setIncludeSettlement($includeSettlement);
		}
		if ($includeRealm) {
			$GR->setIncludeRealm($includeRealm);
		}
		if ($includeHouse) {
			$GR->setIncludeHouse($includeHouse);
		}
		if ($includePlace) {
			$GR->setIncludePlace($includePlace);
		}
		if ($includePos) {
			$GR->setIncludePosition($includePos);
		}
		if ($includeSoldiers) {
			foreach ($includeSoldiers as $soldier) {
				$GR->addIncludeSoldier($soldier);
				$soldier->addPartOfRequests($GR);
			}
		}
		if ($includeEquipment) {
			foreach ($includeEquipment as $equip) {
				$GR->addEquipment($equip);
			}
		}
		$this->em->flush();
	}

	public function newRequestFromCharacterToHouse ($type, $expires = null, $numberValue = null, $stringValue = null, $subject = null, $text = null, ?Character $fromChar = null, ?House $toHouse = null, ?Character $includeChar = null, ?Settlement $includeSettlement = null, ?Realm $includeRealm = null, ?House $includeHouse = null, ?Soldier $includeSoldiers = null, ?EquipmentType $includeEquipment = null) {
		$GR = new GameRequest();
		$this->em->persist($GR);
		$GR->setType($type);
		$GR->setCreated(new \DateTime("now"));
		$GR->setAccepted(FALSE);
		$GR->setRejected(FALSE);
		if ($expires) {
			$GR->setExpires($expires);
		}
		if ($numberValue) {
			$GR->setNumberValue($numberValue);
		}
		if ($stringValue) {
			$GR->setStringValue($stringValue);
		}
		if ($subject) {
			$GR->setSubject($subject);
		}
		if ($text) {
			$GR->setText($text);
		}
		$GR->setFromCharacter($fromChar);
		$GR->setToHouse($toHouse);
		if ($includeChar) {
			$GR->setIncludeCharacter($includeChar);
		}
		if ($includeSettlement) {
			$GR->setIncludeSettlement($includeSettlement);
		}
		if ($includeRealm) {
			$GR->setIncludeRealm($includeRealm);
		}
		if ($includeHouse) {
			$GR->setIncludeHouse($includeHouse);
		}
		if ($includeSoldiers) {
			foreach ($includeSoldiers as $soldier) {
				$GR->addIncludeSoldier($soldier);
				$soldier->addPartOfRequests($GR);
			}
		}
		if ($includeEquipment) {
			foreach ($includeEquipment as $equip) {
				$GR->addEquipment($equip);
			}
		}
		$this->em->flush();
	}

	public function newRequestFromCharactertoSettlement ($type, $expires = null, $numberValue = null, $stringValue = null, $subject = null, $text = null, ?Character $fromChar = null, ?Settlement $toSettlement = null) {
		$GR = new GameRequest();
		$this->em->persist($GR);
		$GR->setType($type);
		$GR->setCreated(new \DateTime("now"));
		$GR->setAccepted(FALSE);
		$GR->setRejected(FALSE);
		if ($expires) {
			$GR->setExpires($expires);
		}
		if ($numberValue) {
			$GR->setNumberValue($numberValue);
		}
		if ($stringValue) {
			$GR->setStringValue($stringValue);
		}
		if ($subject) {
			$GR->setSubject($subject);
		}
		if ($text) {
			$GR->setText($text);
		}
		if ($fromChar) {
			$GR->setFromCharacter($fromChar);
		}
		if ($toSettlement) {
			$GR->setToSettlement($toSettlement);
		}
		$this->em->flush();
	}

	public function newRequestFromCharacterToAssociation($type, $expires, $numberValue = null, $stringValue = null, $subject = null, $text = null, ?Character $fromChar = null, ?Association $toAssoc = null, ?Character $includeChar = null, ?Place $includePlace = null) {
		$GR = new GameRequest();
		$this->em->persist($GR);
		$GR->setType($type);
		$GR->setCreated(new \DateTime("now"));
		$GR->setAccepted(FALSE);
		$GR->setRejected(FALSE);
		if ($expires) {
			$GR->setExpires($expires);
		}
		if ($numberValue) {
			$GR->setNumberValue($numberValue);
		}
		if ($stringValue) {
			$GR->setStringValue($stringValue);
		}
		if ($subject) {
			$GR->setSubject($subject);
		}
		if ($text) {
			$GR->setText($text);
		}
		if ($fromChar) {
			$GR->setFromCharacter($fromChar);
		}
		if ($toAssoc) {
			$GR->setToAssociation($toAssoc);
		}
		if ($includeChar) {
			$GR->setIncludeCharacter($includeChar);
		}
		if ($includePlace) {
			$GR->setIncludePlace($includePlace);
		}
		$this->em->flush();
	}

	public function newRequestFromHouseToHouse($type, $expires = null, $numberValue = null, $stringValue = null, $subject = null, $text = null, ?Character $fromChar = null, ?House $fromHouse = null, ?House $toHouse = null, ?Character $includeChar = null, ?Settlement $includeSettlement = null, ?Realm $includeRealm = null, ?Place $includePlace = null, ?RealmPosition $includePos = null) {
		$GR = new GameRequest();
		$this->em->persist($GR);
		$GR->setType($type);
		$GR->setCreated(new \DateTime("now"));
		$GR->setAccepted(FALSE);
		$GR->setRejected(FALSE);
		if ($expires) {
			$GR->setExpires($expires);
		}
		if ($numberValue) {
			$GR->setNumberValue($numberValue);
		}
		if ($stringValue) {
			$GR->setStringValue($stringValue);
		}
		if ($subject) {
			$GR->setSubject($subject);
		}
		if ($text) {
			$GR->setText($text);
		}
		if ($fromChar) {
			$GR->setFromCharacter($fromChar);
		}
		if ($fromHouse) {
			$GR->setFromHouse($fromHouse);
		}
		if ($toHouse) {
			$GR->setToHouse($toHouse);
		}
		if ($includeChar) {
			$GR->setIncludeCharacter($includeChar);
		}
		if ($includeSettlement) {
			$GR->setIncludeSettlement($includeSettlement);
		}
		if ($includeRealm) {
			$GR->setIncludeRealm($includeRealm);
		}
		if ($includePlace) {
			$GR->setIncludePlace($includePlace);
		}
		if ($includePos) {
			$GR->setIncludePosition($includePos);
		}
		$this->em->flush();
	}

	public function newRequestFromRealmToRealm($type, $expires = null, $numberValue = null, $stringValue = null, $subject = null, $text = null, ?Character $fromChar = null, ?Realm $fromRealm = null, ?Realm $toRealm = null, ?Character $includeChar = null, ?Settlement $includeSettlement = null, ?Realm $includeRealm = null, ?Place $includePlace = null, ?RealmPosition $includePos = null) {
		$GR = new GameRequest();
		$this->em->persist($GR);
		$GR->setType($type);
		$GR->setCreated(new \DateTime("now"));
		$GR->setAccepted(FALSE);
		$GR->setRejected(FALSE);
		if ($expires) {
			$GR->setExpires($expires);
		}
		if ($numberValue) {
			$GR->setNumberValue($numberValue);
		}
		if ($stringValue) {
			$GR->setStringValue($stringValue);
		}
		if ($subject) {
			$GR->setSubject($subject);
		}
		if ($text) {
			$GR->setText($text);
		}
		if ($fromChar) {
			$GR->setFromCharacter($fromChar);
		}
		if ($fromRealm) {
			$GR->setFromRealm($fromRealm);
		}
		if ($toRealm) {
			$GR->setToRealm($toRealm);
		}
		if ($includeChar) {
			$GR->setIncludeCharacter($includeChar);
		}
		if ($includeSettlement) {
			$GR->setIncludeSettlement($includeSettlement);
		}
		if ($includeRealm) {
			$GR->setIncludeRealm($includeRealm);
		}
		if ($includePlace) {
			$GR->setIncludePlace($includePlace);
		}
		if ($includePos) {
			$GR->setIncludePosition($includePos);
		}
		$this->em->flush();
	}

	public function newOathOffer(Character $char, $text, $target) {
		# This is a separate function because of the $target variable being of varying types.
		$GR = new GameRequest();
		$this->em->persist($GR);
		$GR->setType('oath.offer');
		$GR->setCreated(new \DateTime("now"));
		$GR->setAccepted(FALSE);
		$GR->setRejected(FALSE);
		$GR->setSubject($char->getName().' Offers an Oath');
		$GR->setText($text);
		$GR->setFromCharacter($char);
		if ($target instanceof Settlement) {
			$GR->setToSettlement($target);
		} elseif ($target instanceof Place) {
			$GR->setToPlace($target);
		} elseif ($target instanceof RealmPosition) {
			$GR->setToPosition($target);
		}
		$this->em->flush();
	}

	public function getAvailableFoodSuppliers(Character $char): ArrayCollection {
		# Build the list of settlements we can get food from...
                $query = $this->em->createQuery('SELECT r FROM App\Entity\GameRequest r WHERE r.type = :type AND r.from_character = :char AND r.accepted = TRUE')->setParameters(array('char'=>$char, 'type'=>'soldier.food'));
                # Doctrine will lose it's mind if it tries to pass a null variable to a query, so we trick it by declaring this as '0'.
                # Doctrine will process this as a integer, and then check to see if any request has an ID that is in 0.
                # Which will never happen.
                $settlements = new ArrayCollection();
                foreach ($query->getResult() as $result) {
			$settlements->add($result->getToSettlement());
                }
		$query2 = $this->em->createQuery(
			'SELECT s FROM App\Entity\Settlement s
			WHERE ((s.owner = :char OR s.steward = :char) AND s.occupant IS NULL)
			OR s.occupant = :char')
		->setParameters(['char'=>$char]);
		foreach ($query2->getResult() as $result2) {
			if (!$settlements->contains($result2)) {
				$settlements->add($result2);
			}
		}
		$liege = $char->findLiege();
		if ($liege instanceof Settlement) {
			if ($liege->getFeedSoldiers() && !$settlements->contains($liege)) {
				$settlements->add($liege);
			}
		}
		return $settlements;
	}
}
