<?php

namespace App\Entity;

class CharacterBackground {
	private ?string $appearance = null;
	private ?string $personality = null;
	private ?string $secrets = null;
	private ?string $retirement = null;
	private ?string $death = null;
	private ?int $id = null;
	private ?Character $character = null;

	/**
	 * Get appearance
	 *
	 * @return string|null
	 */
	public function getAppearance(): ?string {
		return $this->appearance;
	}

	/**
	 * Set appearance
	 *
	 * @param string|null $appearance
	 *
	 * @return CharacterBackground
	 */
	public function setAppearance(?string $appearance = null): static {
		$this->appearance = $appearance;

		return $this;
	}

	/**
	 * Get personality
	 *
	 * @return string|null
	 */
	public function getPersonality(): ?string {
		return $this->personality;
	}

	/**
	 * Set personality
	 *
	 * @param string|null $personality
	 *
	 * @return CharacterBackground
	 */
	public function setPersonality(?string $personality = null): static {
		$this->personality = $personality;

		return $this;
	}

	/**
	 * Get secrets
	 *
	 * @return string|null
	 */
	public function getSecrets(): ?string {
		return $this->secrets;
	}

	/**
	 * Set secrets
	 *
	 * @param string|null $secrets
	 *
	 * @return CharacterBackground
	 */
	public function setSecrets(?string $secrets = null): static {
		$this->secrets = $secrets;

		return $this;
	}

	/**
	 * Get retirement
	 *
	 * @return string|null
	 */
	public function getRetirement(): ?string {
		return $this->retirement;
	}

	/**
	 * Set retirement
	 *
	 * @param string|null $retirement
	 *
	 * @return CharacterBackground
	 */
	public function setRetirement(?string $retirement = null): static {
		$this->retirement = $retirement;

		return $this;
	}

	/**
	 * Get death
	 *
	 * @return string|null
	 */
	public function getDeath(): ?string {
		return $this->death;
	}

	/**
	 * Set death
	 *
	 * @param string|null $death
	 *
	 * @return CharacterBackground
	 */
	public function setDeath(?string $death = null): static {
		$this->death = $death;

		return $this;
	}

	/**
	 * Get id
	 *
	 * @return int|null
	 */
	public function getId(): ?int {
		return $this->id;
	}

	/**
	 * Get character
	 *
	 * @return Character|null
	 */
	public function getCharacter(): ?Character {
		return $this->character;
	}

	/**
	 * Set character
	 *
	 * @param Character|null $character
	 *
	 * @return CharacterBackground
	 */
	public function setCharacter(?Character $character = null): static {
		$this->character = $character;

		return $this;
	}
}
