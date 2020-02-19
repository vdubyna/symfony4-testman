<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\TestSessionTemplateItemRepository")
 */
class TestSessionTemplateItem
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Category")
     * @ORM\JoinColumn(nullable=false)
     */
    private $category;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $level;

    /**
     * @ORM\Column(type="integer")
     */
    private $cutoff;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\TestSessionTemplate", inversedBy="items")
     * @ORM\JoinColumn(nullable=false)
     */
    private $testSessionTemplate;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getLevel(): ?string
    {
        return $this->level;
    }

    public function setLevel(string $level): self
    {
        $this->level = $level;

        return $this;
    }

    public function getCutoff(): ?int
    {
        return $this->cutoff;
    }

    public function setCutoff(int $cutoff): self
    {
        $this->cutoff = $cutoff;

        return $this;
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
}
