<?php

namespace App\Controller;

use App\Entity\Heraldry;
use App\Form\HeraldryType;
use App\Service\PaymentManager;
use Doctrine\ORM\EntityManagerInterface;
use DOMDocument;
use DOMElement;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class HeraldryController extends AbstractController {

	#[Route ('/heraldry/', name:'maf_heraldry')]
	public function indexAction(): Response {
		$user = $this->getUser();

		return $this->render('Heraldry/index.html.twig', [
			'crests'=>$user->getCrests()
		]);
	}

	#[Route ('/heraldry/create', name:'maf_heraldry_create')]
	public function createAction(EntityManagerInterface $em, PaymentManager $pay, TranslatorInterface $trans, Request $request): RedirectResponse|Response {
		$user = $this->getUser();
		$banner = new Heraldry;
		$crestfee = $pay->getCostOfHeraldry();

		$form = $this->createForm(HeraldryType::class, $banner);
		$form->handleRequest($request);
		if ($form->isSubmitted() && $form->isValid()) {
			$valid=true;
			// TODO: repeat validation (if you have a charge, it needs a colour, etc.) - this should be in the form somewhere
			if ($user->getCredits() < $crestfee) {
				$form->addError(new FormError($trans->trans("design.poor"), null, array("%amount%"=>$user->getCredits())));
				$valid = false;
			}

			if ($valid) {
				$svg = $this->createSVG($banner);
				$banner->setSvg($svg);
				$banner->setUser($user);
				$em->persist($banner);
				$em->flush();
				if (!$pay->spend($user, "heraldry", $crestfee, false)) {
					throw new Exception("payment failed even after checking");
				}
				$this->addFlash('notice', $trans->trans('design.saved', array(), 'heraldry'));
				return $this->redirectToRoute('maf_heraldry');
			}
		}

		return $this->render('Heraldry/create.html.twig', [
			'crestfee' => $crestfee,
			'form' => $form->createView()
		]);
	}

	#[Route ('/heraldry/validate', name:'maf_heraldry_validate', defaults:['_format'=>'json'])]
	public function validateAction(EntityManagerInterface $em, Request $request): Response {
		$exists = $em->getRepository('App\Entity\Heraldry')->findOneBy(array(
			'shield_colour' => $request->query->get('shield_colour'),
			'pattern' => $request->query->get('pattern'),
			'pattern_colour' => $request->query->get('pattern_colour'),
			'charge' => $request->query->get('charge'),
			'charge_colour' => $request->query->get('charge_colour'),
		));
		if ($exists) {
			$valid=false;
		} else {
			$valid=true;
		}
		return new Response(json_encode($valid));
	}

	#[Route ('/heraldry/crest/{id}', name:'maf_heraldry_crest', requirements:['id'=>'\d+'])]
	public function crestAction(Heraldry $id): Response {
		$data=array(
			'<?xml version="1.0" encoding="UTF-8" standalone="no"?>',
			'<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">',
			$id->getSvg()
		);
		$response = new Response();
		$response->setContent(implode("\n", $data));
		$response->setStatusCode(200);
		$response->headers->set('Content-Type', 'image/svg+xml');
		return $response;
	}


	private function createSVG($banner): bool|string {
		$basedir = __DIR__."/../../public/heraldry-svg/";

		$xml = new DOMDocument('1.0', 'UTF-8');
		$svg = $xml->appendChild(new DOMElement("svg"));
		$svg->setAttribute("viewBox", "0 0 300 350");
		$svg->setAttribute("xmlns", "http://www.w3.org/2000/svg");
		$svg->setAttribute("xmlns:xlink", "http://www.w3.org/1999/xlink");

		$defs = $svg->appendChild(new DOMElement("defs"));


		$file=$basedir."shields/".$banner->getShield().".svg";
		if (!file_exists($file)) {
			throw new Exception("svg file shields/".$banner->getShield()." does not exit");
		}
		$doc = new DOMDocument();
		$doc->load($file);

		$path = $doc->getElementsByTagName("path")->item(0);
		$node = $xml->importNode($path, true);
		$shield_def = $defs->appendChild($node);
		$shield = $svg->appendChild(new DOMElement("use"));
		$shield->setAttribute("xlink:href", "#".$banner->getShield());
		$shield->setAttribute("fill", $banner->getShieldColour());
		$shield->setAttribute("stroke", "black");
		$shield->setAttribute("stroke-width", "2");

		$clip = $defs->appendChild(new DOMElement("clipPath"));
		$clip->setAttribute("id", "boundary");
		$clippath = $clip->appendChild(new DOMElement("use"));
		$clippath->setAttribute("xlink:href", "#".$banner->getShield());

		if ($banner->getPattern() && $banner->getPatternColour()) {
			$file=$basedir."patterns/".$banner->getPattern().".svg";
			if (!file_exists($file)) {
				throw new Exception("svg file patterns/".$banner->getPattern()." does not exit");
			}
			$doc = new DOMDocument();
			$doc->load($file);

			$path = $doc->getElementsByTagName("path")->item(0);
			$pattern = $xml->importNode($path, true);
			$pattern->setAttribute("fill", $banner->getPatternColour());
			$pattern->setAttribute("clip-path", 'url(#boundary)');
			$child = $svg->appendChild($pattern);
		}

		if ($banner->getCharge() && $banner->getChargeColour()) {
			$file=$basedir."charges/".$banner->getCharge().".svg";
			if (!file_exists($file)) {
				throw new Exception("svg file charges/".$banner->getCharge()." does not exit");
			}
			$doc = new DOMDocument();
			#$doc->validateOnParse = true; #SVG fails to validate. TODO: Research the heck out of this.
			$doc->load($file);

			$paths = $doc->getElementsByTagName("path");
			foreach ($paths as $path) {
				$charge = $xml->importNode($path, true);
				if ($path->getAttribute("id")=="fill") {
					$charge->setAttribute("fill", $banner->getChargeColour());
				} else {
					if ($banner->getChargeColour()=="rgb(0,0,0)") {
						$charge->setAttribute("fill", "rgb(60,60,60)");
					} else {
						$charge->setAttribute("fill", "black");
					}
				}
				$child = $svg->appendChild($charge);
			}
		}

		if ($banner->getShading()) {
			$file=$basedir."shading/".$banner->getShield().".svg";
			if (!file_exists($file)) {
				throw new Exception("svg file shading/".$banner->getShield()." does not exit");
			}
			$doc = new DOMDocument();
			$doc->load($file);

			$path = $doc->getElementsByTagName("g")->item(0);
			$shading = $xml->importNode($path, true);
			$child = $svg->appendChild($shading);
		}

		return $xml->saveXML($svg);
	}

	private function XmltoArray($xml) {
		$array = json_decode(json_encode($xml), TRUE);

		foreach ( array_slice($array, 0) as $key => $value ) {
			if ( empty($value) ) $array[$key] = NULL;
			elseif ( is_array($value) ) $array[$key] = $this->XmltoArray($value);
		}

		return $array;
	}

}
