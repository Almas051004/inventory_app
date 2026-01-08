<?php

namespace App\Entity;

use App\Repository\ItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ItemRepository::class)]
#[ORM\Table(name: 'items')]
#[ORM\UniqueConstraint(name: 'unique_inventory_custom_id', columns: ['inventory_id', 'custom_id'])]
#[ORM\HasLifecycleCallbacks]
class Item
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Inventory $inventory = null;

    #[ORM\Column(length: 255)]
    private ?string $customId = null;

    #[ORM\ManyToOne(inversedBy: 'createdItems')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    private int $version = 1;

    // Значения кастомных полей: строковые (single-line text) - до 3 штук
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customString1Value = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customString2Value = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customString3Value = null;

    // Значения кастомных полей: многострочный текст (multi-line text) - до 3 штук
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $customText1Value = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $customText2Value = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $customText3Value = null;

    // Значения кастомных полей: числовые (numeric) - до 3 штук
    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private ?int $customInt1Value = null;

    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private ?int $customInt2Value = null;

    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private ?int $customInt3Value = null;

    // Значения кастомных полей: булевы (true/false) - до 3 штук
    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $customBool1Value = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $customBool2Value = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $customBool3Value = null;

    // Значения кастомных полей: ссылки (document/image links) - до 3 штук
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $customLink1Value = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $customLink2Value = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $customLink3Value = null;

    // Связь с лайками
    #[ORM\OneToMany(targetEntity: Like::class, mappedBy: 'item', cascade: ['persist', 'remove'])]
    private \Doctrine\Common\Collections\Collection $likes;

    public function __construct()
    {
        $this->likes = new \Doctrine\Common\Collections\ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInventory(): ?Inventory
    {
        return $this->inventory;
    }

    public function setInventory(?Inventory $inventory): static
    {
        $this->inventory = $inventory;

        return $this;
    }

    public function getCustomId(): ?string
    {
        return $this->customId;
    }

    public function setCustomId(string $customId): static
    {
        $this->customId = $customId;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): static
    {
        $this->version = $version;

        return $this;
    }

    public function incrementVersion(): static
    {
        $this->version++;

        return $this;
    }

    // Геттеры и сеттеры для значений кастомных полей
    public function getCustomString1Value(): ?string { return $this->customString1Value; }
    public function setCustomString1Value(?string $customString1Value): static { $this->customString1Value = $customString1Value; return $this; }
    public function getCustomString2Value(): ?string { return $this->customString2Value; }
    public function setCustomString2Value(?string $customString2Value): static { $this->customString2Value = $customString2Value; return $this; }
    public function getCustomString3Value(): ?string { return $this->customString3Value; }
    public function setCustomString3Value(?string $customString3Value): static { $this->customString3Value = $customString3Value; return $this; }

    public function getCustomText1Value(): ?string { return $this->customText1Value; }
    public function setCustomText1Value(?string $customText1Value): static { $this->customText1Value = $customText1Value; return $this; }
    public function getCustomText2Value(): ?string { return $this->customText2Value; }
    public function setCustomText2Value(?string $customText2Value): static { $this->customText2Value = $customText2Value; return $this; }
    public function getCustomText3Value(): ?string { return $this->customText3Value; }
    public function setCustomText3Value(?string $customText3Value): static { $this->customText3Value = $customText3Value; return $this; }

    public function getCustomInt1Value(): ?int { return $this->customInt1Value; }
    public function setCustomInt1Value(?int $customInt1Value): static { $this->customInt1Value = $customInt1Value; return $this; }
    public function getCustomInt2Value(): ?int { return $this->customInt2Value; }
    public function setCustomInt2Value(?int $customInt2Value): static { $this->customInt2Value = $customInt2Value; return $this; }
    public function getCustomInt3Value(): ?int { return $this->customInt3Value; }
    public function setCustomInt3Value(?int $customInt3Value): static { $this->customInt3Value = $customInt3Value; return $this; }

    public function getCustomBool1Value(): ?bool { return $this->customBool1Value; }
    public function setCustomBool1Value(?bool $customBool1Value): static { $this->customBool1Value = $customBool1Value; return $this; }
    public function getCustomBool2Value(): ?bool { return $this->customBool2Value; }
    public function setCustomBool2Value(?bool $customBool2Value): static { $this->customBool2Value = $customBool2Value; return $this; }
    public function getCustomBool3Value(): ?bool { return $this->customBool3Value; }
    public function setCustomBool3Value(?bool $customBool3Value): static { $this->customBool3Value = $customBool3Value; return $this; }

    public function getCustomLink1Value(): ?string { return $this->customLink1Value; }
    public function setCustomLink1Value(?string $customLink1Value): static { $this->customLink1Value = $customLink1Value; return $this; }
    public function getCustomLink2Value(): ?string { return $this->customLink2Value; }
    public function setCustomLink2Value(?string $customLink2Value): static { $this->customLink2Value = $customLink2Value; return $this; }
    public function getCustomLink3Value(): ?string { return $this->customLink3Value; }
    public function setCustomLink3Value(?string $customLink3Value): static { $this->customLink3Value = $customLink3Value; return $this; }

    /**
     * @return \Doctrine\Common\Collections\Collection<int, Like>
     */
    public function getLikes(): \Doctrine\Common\Collections\Collection
    {
        return $this->likes;
    }

    public function addLike(Like $like): static
    {
        if (!$this->likes->contains($like)) {
            $this->likes->add($like);
            $like->setItem($this);
        }

        return $this;
    }

    public function removeLike(Like $like): static
    {
        if ($this->likes->removeElement($like)) {
            if ($like->getItem() === $this) {
                $like->setItem(null);
            }
        }

        return $this;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
        $this->incrementVersion();
    }
}

