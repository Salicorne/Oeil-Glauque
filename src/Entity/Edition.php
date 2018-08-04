<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\EditionRepository")
 */
class Edition
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer")
     */
    private $annee;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\GameSlot", mappedBy="edition")
     */
    private $gameSlots;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $dates;

    public function __construct()
    {
        $this->gameSlots = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getAnnee(): ?int
    {
        return $this->annee;
    }

    public function setAnnee(int $annee): self
    {
        $this->annee = $annee;

        return $this;
    }

    /**
     * @return Collection|GameSlot[]
     */
    public function getGameSlots(): Collection
    {
        return $this->gameSlots;
    }

    public function addGameSlot(GameSlot $gameSlot): self
    {
        if (!$this->gameSlots->contains($gameSlot)) {
            $this->gameSlots[] = $gameSlot;
            $gameSlot->setEdition($this);
        }

        return $this;
    }

    public function removeGameSlot(GameSlot $gameSlot): self
    {
        if ($this->gameSlots->contains($gameSlot)) {
            $this->gameSlots->removeElement($gameSlot);
            // set the owning side to null (unless already changed)
            if ($gameSlot->getEdition() === $this) {
                $gameSlot->setEdition(null);
            }
        }

        return $this;
    }

    public function getDates(): ?string
    {
        return $this->dates;
    }

    public function setDates(string $dates): self
    {
        $this->dates = $dates;

        return $this;
    }
}
