<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\TestSessionTemplateRepository")
 */
class TestSessionTemplate
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\Column(type="integer")
     */
    private $timeLimit;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\TestSessionTemplateItem", mappedBy="testSessionTemplate", orphanRemoval=true, cascade={"persist"})
     */
    private $items;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\TestSession", mappedBy="testSessionTemplate")
     */
    private $testSessions;

    /**
     * @ORM\Column(type="float")
     */
    private $cutoffSuccess;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->testSessions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getTimeLimit(): ?int
    {
        return $this->timeLimit;
    }

    public function setTimeLimit(int $timeLimit): self
    {
        $this->timeLimit = $timeLimit;

        return $this;
    }

    /**
     * @return Collection|TestSessionTemplateItem[]
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(TestSessionTemplateItem $item): self
    {
        if (!$this->items->contains($item)) {
            $this->items[] = $item;
            $item->setTestSessionTemplate($this);
        }

        return $this;
    }

    public function removeItem(TestSessionTemplateItem $item): self
    {
        if ($this->items->contains($item)) {
            $this->items->removeElement($item);
            // set the owning side to null (unless already changed)
            if ($item->getTestSessionTemplate() === $this) {
                $item->setTestSessionTemplate(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|TestSession[]
     */
    public function getTestSessions(): Collection
    {
        return $this->testSessions;
    }

    public function addTestSession(TestSession $testSession): self
    {
        if (!$this->testSessions->contains($testSession)) {
            $this->testSessions[] = $testSession;
            $testSession->setTestSessionTemplate($this);
        }

        return $this;
    }

    public function removeTestSession(TestSession $testSession): self
    {
        if ($this->testSessions->contains($testSession)) {
            $this->testSessions->removeElement($testSession);
            // set the owning side to null (unless already changed)
            if ($testSession->getTestSessionTemplate() === $this) {
                $testSession->setTestSessionTemplate(null);
            }
        }

        return $this;
    }

    public function getCutoffSuccess(): ?float
    {
        return $this->cutoffSuccess;
    }

    public function setCutoffSuccess(?float $cutoffSuccess): self
    {
        $this->cutoffSuccess = $cutoffSuccess;

        return $this;
    }

    public function __toString()
    {
        return $this->getName();
    }
}
