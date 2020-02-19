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
     * @ORM\Column(type="datetime")
     */
    private $executeAt;

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

    public function getExecuteAt(): ?\DateTimeInterface
    {
        return $this->executeAt;
    }

    public function setExecuteAt(\DateTimeInterface $executeAt): self
    {
        $this->executeAt = $executeAt;

        return $this;
    }
}
