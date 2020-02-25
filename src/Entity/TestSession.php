<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\TestSessionRepository")
 */
class TestSession
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\TestSessionTemplate", inversedBy="testSessions")
     * @ORM\JoinColumn(nullable=false)
     */
    private $testSessionTemplate;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $email;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\TestSessionItem", mappedBy="testSession", orphanRemoval=true)
     */
    private $testSessionItems;

    /**
     * @ORM\Column(type="integer")
     */
    private $timeLimit;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $startedAt;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $finishedAt;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $uuid;

    /**
     * @ORM\Column(type="integer")
     */
    private $questionsCount;

    public function __construct()
    {
        $this->testSessionItems = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTestSessionTemplate(): ?TestSessionTemplate
    {
        return $this->testSessionTemplate;
    }

    public function setTestSessionTemplate(?TestSessionTemplate $testSessionTemplate): self
    {
        $this->testSessionTemplate = $testSessionTemplate;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * @return Collection|TestSessionItem[]
     */
    public function getTestSessionItems(): Collection
    {
        return $this->testSessionItems;
    }

    public function addTestSessionItem(TestSessionItem $testSessionItem): self
    {
        if (!$this->testSessionItems->contains($testSessionItem)) {
            $this->testSessionItems[] = $testSessionItem;
            $testSessionItem->setTestSession($this);
        }

        return $this;
    }

    public function removeTestSessionItem(TestSessionItem $testSessionItem): self
    {
        if ($this->testSessionItems->contains($testSessionItem)) {
            $this->testSessionItems->removeElement($testSessionItem);
            // set the owning side to null (unless already changed)
            if ($testSessionItem->getTestSession() === $this) {
                $testSessionItem->setTestSession(null);
            }
        }

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

    public function getStartedAt(): ?\DateTimeInterface
    {
        return $this->startedAt;
    }

    public function setStartedAt(?\DateTimeInterface $startedAt): self
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getFinishedAt(): ?\DateTimeInterface
    {
        return $this->finishedAt;
    }

    public function setFinishedAt(?\DateTimeInterface $finishedAt): self
    {
        $this->finishedAt = $finishedAt;

        return $this;
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): self
    {
        $this->uuid = $uuid;

        return $this;
    }

    public function getQuestionsCount(): ?int
    {
        return $this->questionsCount;
    }

    public function setQuestionsCount(int $questionsCount): self
    {
        $this->questionsCount = $questionsCount;

        return $this;
    }
}
