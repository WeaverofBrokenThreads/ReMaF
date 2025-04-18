<?php

namespace App\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class NewsEdition {
	private int $number;
	private bool $collection;
	private ?int $published_cycle = null;
	private ?DateTime $published;
	private ?int $id = null;
	private Collection $articles;
	private Collection $readers;
	private ?NewsPaper $paper = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->articles = new ArrayCollection();
		$this->readers = new ArrayCollection();
	}

	public function isPublished(): DateTime {
		return $this->getPublished();
	}

	/**
	 * Get published
	 *
	 * @return DateTime|null
	 */
	public function getPublished(): ?DateTime {
		return $this->published;
	}

	/**
	 * Set published
	 *
	 * @param DateTime|null $published
	 *
	 * @return NewsEdition
	 */
	public function setPublished(?DateTime $published): static {
		$this->published = $published;

		return $this;
	}

	/**
	 * Get number
	 *
	 * @return integer
	 */
	public function getNumber(): int {
		return $this->number;
	}

	/**
	 * Set number
	 *
	 * @param integer $number
	 *
	 * @return NewsEdition
	 */
	public function setNumber(int $number): static {
		$this->number = $number;

		return $this;
	}

	/**
	 * Get collection
	 *
	 * @return boolean
	 */
	public function getCollection(): bool {
		return $this->collection;
	}

	public function isCollection(): ?bool {
		return $this->collection;
	}

	/**
	 * Set collection
	 *
	 * @param boolean $collection
	 *
	 * @return NewsEdition
	 */
	public function setCollection(bool $collection): static {
		$this->collection = $collection;

		return $this;
	}

	/**
	 * Get published_cycle
	 *
	 * @return int|null
	 */
	public function getPublishedCycle(): ?int {
		return $this->published_cycle;
	}

	/**
	 * Set published_cycle
	 *
	 * @param integer|null $publishedCycle
	 *
	 * @return NewsEdition
	 */
	public function setPublishedCycle(?int $publishedCycle): static {
		$this->published_cycle = $publishedCycle;

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
	 * Add articles
	 *
	 * @param NewsArticle $articles
	 *
	 * @return NewsEdition
	 */
	public function addArticle(NewsArticle $articles): static {
		$this->articles[] = $articles;

		return $this;
	}

	/**
	 * Remove articles
	 *
	 * @param NewsArticle $articles
	 */
	public function removeArticle(NewsArticle $articles): void {
		$this->articles->removeElement($articles);
	}

	/**
	 * Get articles
	 *
	 * @return ArrayCollection|Collection
	 */
	public function getArticles(): ArrayCollection|Collection {
		return $this->articles;
	}

	/**
	 * Add readers
	 *
	 * @param NewsReader $readers
	 *
	 * @return NewsEdition
	 */
	public function addReader(NewsReader $readers): static {
		$this->readers[] = $readers;

		return $this;
	}

	/**
	 * Remove readers
	 *
	 * @param NewsReader $readers
	 */
	public function removeReader(NewsReader $readers): void {
		$this->readers->removeElement($readers);
	}

	/**
	 * Get readers
	 *
	 * @return ArrayCollection|Collection
	 */
	public function getReaders(): ArrayCollection|Collection {
		return $this->readers;
	}

	/**
	 * Get paper
	 *
	 * @return NewsPaper|null
	 */
	public function getPaper(): ?NewsPaper {
		return $this->paper;
	}

	/**
	 * Set paper
	 *
	 * @param NewsPaper|null $paper
	 *
	 * @return NewsEdition
	 */
	public function setPaper(?NewsPaper $paper = null): static {
		$this->paper = $paper;

		return $this;
	}
}
