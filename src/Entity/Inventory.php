<?php

namespace App\Entity;

use App\Repository\InventoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InventoryRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Inventory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\ManyToOne(inversedBy: 'createdInventories')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $creator = null;

    #[ORM\ManyToOne(inversedBy: 'inventories')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Category $category = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isPublic = false;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    private int $version = 1;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $customIdFormat = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => false])]
    private ?bool $customString1State = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customString1Name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $customString1Description = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => false])]
    private ?bool $customString1ShowInTable = false;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $customString1MinLength = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $customString1MaxLength = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $customString1Regex = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => false])]
    private ?bool $customString2State = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customString2Name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $customString2Description = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => false])]
    private ?bool $customString2ShowInTable = false;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $customString2MinLength = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $customString2MaxLength = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $customString2Regex = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => false])]
    private ?bool $customString3State = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customString3Name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $customString3Description = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => false])]
    private ?bool $customString3ShowInTable = false;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $customString3MinLength = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $customString3MaxLength = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $customString3Regex = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => false])]
    private ?bool $customText1State = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customText1Name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $customText1Description = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => false])]
    private ?bool $customText1ShowInTable = false;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => false])]
    private ?bool $customText2State = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customText2Name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $customText2Description = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => false])]
    private ?bool $customText2ShowInTable = false;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => false])]
    private ?bool $customText3State = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customText3Name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $customText3Description = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => false])]
    private ?bool $customText3ShowInTable = false;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => false])]
    private ?bool $customInt1State = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customInt1Name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $customInt1Description = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => false])]
    private ?bool $customInt1ShowInTable = false;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $customInt1MinValue = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $customInt1MaxValue = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => false])]
    private ?bool $customInt2State = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customInt2Name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $customInt2Description = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => false])]
    private ?bool $customInt2ShowInTable = false;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $customInt2MinValue = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $customInt2MaxValue = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => false])]
    private ?bool $customInt3State = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customInt3Name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $customInt3Description = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => false])]
    private ?bool $customInt3ShowInTable = false;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $customInt3MinValue = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $customInt3MaxValue = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => false])]
    private ?bool $customBool1State = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customBool1Name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $customBool1Description = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => false])]
    private ?bool $customBool1ShowInTable = false;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => false])]
    private ?bool $customBool2State = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customBool2Name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $customBool2Description = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => false])]
    private ?bool $customBool2ShowInTable = false;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => false])]
    private ?bool $customBool3State = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customBool3Name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $customBool3Description = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => false])]
    private ?bool $customBool3ShowInTable = false;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => false])]
    private ?bool $customLink1State = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customLink1Name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $customLink1Description = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => false])]
    private ?bool $customLink1ShowInTable = false;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => false])]
    private ?bool $customLink2State = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customLink2Name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $customLink2Description = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => false])]
    private ?bool $customLink2ShowInTable = false;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => false])]
    private ?bool $customLink3State = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customLink3Name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $customLink3Description = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => false])]
    private ?bool $customLink3ShowInTable = false;

    #[ORM\OneToMany(targetEntity: Item::class, mappedBy: 'inventory', cascade: ['persist', 'remove'])]
    private Collection $items;

    #[ORM\OneToMany(targetEntity: InventoryAccess::class, mappedBy: 'inventory', cascade: ['persist', 'remove'])]
    private Collection $accesses;

    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'inventory', cascade: ['persist', 'remove'])]
    private Collection $comments;

    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'inventories')]
    #[ORM\JoinTable(name: 'inventory_tags')]
    private Collection $tags;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->accesses = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->tags = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): static
    {
        $this->imageUrl = $imageUrl;

        return $this;
    }

    public function getCreator(): ?User
    {
        return $this->creator;
    }

    public function setCreator(?User $creator): static
    {
        $this->creator = $creator;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function setIsPublic(bool $isPublic): static
    {
        $this->isPublic = $isPublic;

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

    public function getCustomIdFormat(): ?array
    {
        return $this->customIdFormat;
    }

    public function setCustomIdFormat(?array $customIdFormat): static
    {
        $this->customIdFormat = $customIdFormat;

        return $this;
    }

    /**
     * @return Collection<int, Item>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(Item $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setInventory($this);
        }

        return $this;
    }

    public function removeItem(Item $item): static
    {
        if ($this->items->removeElement($item)) {
            if ($item->getInventory() === $this) {
                $item->setInventory(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, InventoryAccess>
     */
    public function getAccesses(): Collection
    {
        return $this->accesses;
    }

    public function addAccess(InventoryAccess $access): static
    {
        if (!$this->accesses->contains($access)) {
            $this->accesses->add($access);
            $access->setInventory($this);
        }

        return $this;
    }

    public function removeAccess(InventoryAccess $access): static
    {
        if ($this->accesses->removeElement($access)) {
            if ($access->getInventory() === $this) {
                $access->setInventory(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setInventory($this);
        }

        return $this;
    }

    public function removeComment(Comment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            if ($comment->getInventory() === $this) {
                $comment->setInventory(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        $this->tags->removeElement($tag);

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

    public function getCustomString1State(): ?bool { return $this->customString1State; }
    public function setCustomString1State(?bool $customString1State): static { $this->customString1State = $customString1State; return $this; }
    public function getCustomString1Name(): ?string { return $this->customString1Name; }
    public function setCustomString1Name(?string $customString1Name): static { $this->customString1Name = $customString1Name; return $this; }
    public function getCustomString1Description(): ?string { return $this->customString1Description; }
    public function setCustomString1Description(?string $customString1Description): static { $this->customString1Description = $customString1Description; return $this; }
    public function getCustomString1ShowInTable(): ?bool { return $this->customString1ShowInTable; }
    public function setCustomString1ShowInTable(?bool $customString1ShowInTable): static { $this->customString1ShowInTable = $customString1ShowInTable; return $this; }
    public function getCustomString1MinLength(): ?int { return $this->customString1MinLength; }
    public function setCustomString1MinLength(?int $customString1MinLength): static { $this->customString1MinLength = $customString1MinLength; return $this; }
    public function getCustomString1MaxLength(): ?int { return $this->customString1MaxLength; }
    public function setCustomString1MaxLength(?int $customString1MaxLength): static { $this->customString1MaxLength = $customString1MaxLength; return $this; }
    public function getCustomString1Regex(): ?string { return $this->customString1Regex; }
    public function setCustomString1Regex(?string $customString1Regex): static { $this->customString1Regex = $customString1Regex; return $this; }

    public function getCustomString2State(): ?bool { return $this->customString2State; }
    public function setCustomString2State(?bool $customString2State): static { $this->customString2State = $customString2State; return $this; }
    public function getCustomString2Name(): ?string { return $this->customString2Name; }
    public function setCustomString2Name(?string $customString2Name): static { $this->customString2Name = $customString2Name; return $this; }
    public function getCustomString2Description(): ?string { return $this->customString2Description; }
    public function setCustomString2Description(?string $customString2Description): static { $this->customString2Description = $customString2Description; return $this; }
    public function getCustomString2ShowInTable(): ?bool { return $this->customString2ShowInTable; }
    public function setCustomString2ShowInTable(?bool $customString2ShowInTable): static { $this->customString2ShowInTable = $customString2ShowInTable; return $this; }
    public function getCustomString2MinLength(): ?int { return $this->customString2MinLength; }
    public function setCustomString2MinLength(?int $customString2MinLength): static { $this->customString2MinLength = $customString2MinLength; return $this; }
    public function getCustomString2MaxLength(): ?int { return $this->customString2MaxLength; }
    public function setCustomString2MaxLength(?int $customString2MaxLength): static { $this->customString2MaxLength = $customString2MaxLength; return $this; }
    public function getCustomString2Regex(): ?string { return $this->customString2Regex; }
    public function setCustomString2Regex(?string $customString2Regex): static { $this->customString2Regex = $customString2Regex; return $this; }

    public function getCustomString3State(): ?bool { return $this->customString3State; }
    public function setCustomString3State(?bool $customString3State): static { $this->customString3State = $customString3State; return $this; }
    public function getCustomString3Name(): ?string { return $this->customString3Name; }
    public function setCustomString3Name(?string $customString3Name): static { $this->customString3Name = $customString3Name; return $this; }
    public function getCustomString3Description(): ?string { return $this->customString3Description; }
    public function setCustomString3Description(?string $customString3Description): static { $this->customString3Description = $customString3Description; return $this; }
    public function getCustomString3ShowInTable(): ?bool { return $this->customString3ShowInTable; }
    public function setCustomString3ShowInTable(?bool $customString3ShowInTable): static { $this->customString3ShowInTable = $customString3ShowInTable; return $this; }
    public function getCustomString3MinLength(): ?int { return $this->customString3MinLength; }
    public function setCustomString3MinLength(?int $customString3MinLength): static { $this->customString3MinLength = $customString3MinLength; return $this; }
    public function getCustomString3MaxLength(): ?int { return $this->customString3MaxLength; }
    public function setCustomString3MaxLength(?int $customString3MaxLength): static { $this->customString3MaxLength = $customString3MaxLength; return $this; }
    public function getCustomString3Regex(): ?string { return $this->customString3Regex; }
    public function setCustomString3Regex(?string $customString3Regex): static { $this->customString3Regex = $customString3Regex; return $this; }

    public function getCustomText1State(): ?bool { return $this->customText1State; }
    public function setCustomText1State(?bool $customText1State): static { $this->customText1State = $customText1State; return $this; }
    public function getCustomText1Name(): ?string { return $this->customText1Name; }
    public function setCustomText1Name(?string $customText1Name): static { $this->customText1Name = $customText1Name; return $this; }
    public function getCustomText1Description(): ?string { return $this->customText1Description; }
    public function setCustomText1Description(?string $customText1Description): static { $this->customText1Description = $customText1Description; return $this; }
    public function getCustomText1ShowInTable(): ?bool { return $this->customText1ShowInTable; }
    public function setCustomText1ShowInTable(?bool $customText1ShowInTable): static { $this->customText1ShowInTable = $customText1ShowInTable; return $this; }

    public function getCustomText2State(): ?bool { return $this->customText2State; }
    public function setCustomText2State(?bool $customText2State): static { $this->customText2State = $customText2State; return $this; }
    public function getCustomText2Name(): ?string { return $this->customText2Name; }
    public function setCustomText2Name(?string $customText2Name): static { $this->customText2Name = $customText2Name; return $this; }
    public function getCustomText2Description(): ?string { return $this->customText2Description; }
    public function setCustomText2Description(?string $customText2Description): static { $this->customText2Description = $customText2Description; return $this; }
    public function getCustomText2ShowInTable(): ?bool { return $this->customText2ShowInTable; }
    public function setCustomText2ShowInTable(?bool $customText2ShowInTable): static { $this->customText2ShowInTable = $customText2ShowInTable; return $this; }

    public function getCustomText3State(): ?bool { return $this->customText3State; }
    public function setCustomText3State(?bool $customText3State): static { $this->customText3State = $customText3State; return $this; }
    public function getCustomText3Name(): ?string { return $this->customText3Name; }
    public function setCustomText3Name(?string $customText3Name): static { $this->customText3Name = $customText3Name; return $this; }
    public function getCustomText3Description(): ?string { return $this->customText3Description; }
    public function setCustomText3Description(?string $customText3Description): static { $this->customText3Description = $customText3Description; return $this; }
    public function getCustomText3ShowInTable(): ?bool { return $this->customText3ShowInTable; }
    public function setCustomText3ShowInTable(?bool $customText3ShowInTable): static { $this->customText3ShowInTable = $customText3ShowInTable; return $this; }

    public function getCustomInt1State(): ?bool { return $this->customInt1State; }
    public function setCustomInt1State(?bool $customInt1State): static { $this->customInt1State = $customInt1State; return $this; }
    public function getCustomInt1Name(): ?string { return $this->customInt1Name; }
    public function setCustomInt1Name(?string $customInt1Name): static { $this->customInt1Name = $customInt1Name; return $this; }
    public function getCustomInt1Description(): ?string { return $this->customInt1Description; }
    public function setCustomInt1Description(?string $customInt1Description): static { $this->customInt1Description = $customInt1Description; return $this; }
    public function getCustomInt1ShowInTable(): ?bool { return $this->customInt1ShowInTable; }
    public function setCustomInt1ShowInTable(?bool $customInt1ShowInTable): static { $this->customInt1ShowInTable = $customInt1ShowInTable; return $this; }
    public function getCustomInt1MinValue(): ?int { return $this->customInt1MinValue; }
    public function setCustomInt1MinValue(?int $customInt1MinValue): static { $this->customInt1MinValue = $customInt1MinValue; return $this; }
    public function getCustomInt1MaxValue(): ?int { return $this->customInt1MaxValue; }
    public function setCustomInt1MaxValue(?int $customInt1MaxValue): static { $this->customInt1MaxValue = $customInt1MaxValue; return $this; }

    public function getCustomInt2State(): ?bool { return $this->customInt2State; }
    public function setCustomInt2State(?bool $customInt2State): static { $this->customInt2State = $customInt2State; return $this; }
    public function getCustomInt2Name(): ?string { return $this->customInt2Name; }
    public function setCustomInt2Name(?string $customInt2Name): static { $this->customInt2Name = $customInt2Name; return $this; }
    public function getCustomInt2Description(): ?string { return $this->customInt2Description; }
    public function setCustomInt2Description(?string $customInt2Description): static { $this->customInt2Description = $customInt2Description; return $this; }
    public function getCustomInt2ShowInTable(): ?bool { return $this->customInt2ShowInTable; }
    public function setCustomInt2ShowInTable(?bool $customInt2ShowInTable): static { $this->customInt2ShowInTable = $customInt2ShowInTable; return $this; }
    public function getCustomInt2MinValue(): ?int { return $this->customInt2MinValue; }
    public function setCustomInt2MinValue(?int $customInt2MinValue): static { $this->customInt2MinValue = $customInt2MinValue; return $this; }
    public function getCustomInt2MaxValue(): ?int { return $this->customInt2MaxValue; }
    public function setCustomInt2MaxValue(?int $customInt2MaxValue): static { $this->customInt2MaxValue = $customInt2MaxValue; return $this; }

    public function getCustomInt3State(): ?bool { return $this->customInt3State; }
    public function setCustomInt3State(?bool $customInt3State): static { $this->customInt3State = $customInt3State; return $this; }
    public function getCustomInt3Name(): ?string { return $this->customInt3Name; }
    public function setCustomInt3Name(?string $customInt3Name): static { $this->customInt3Name = $customInt3Name; return $this; }
    public function getCustomInt3Description(): ?string { return $this->customInt3Description; }
    public function setCustomInt3Description(?string $customInt3Description): static { $this->customInt3Description = $customInt3Description; return $this; }
    public function getCustomInt3ShowInTable(): ?bool { return $this->customInt3ShowInTable; }
    public function setCustomInt3ShowInTable(?bool $customInt3ShowInTable): static { $this->customInt3ShowInTable = $customInt3ShowInTable; return $this; }
    public function getCustomInt3MinValue(): ?int { return $this->customInt3MinValue; }
    public function setCustomInt3MinValue(?int $customInt3MinValue): static { $this->customInt3MinValue = $customInt3MinValue; return $this; }
    public function getCustomInt3MaxValue(): ?int { return $this->customInt3MaxValue; }
    public function setCustomInt3MaxValue(?int $customInt3MaxValue): static { $this->customInt3MaxValue = $customInt3MaxValue; return $this; }

    public function getCustomBool1State(): ?bool { return $this->customBool1State; }
    public function setCustomBool1State(?bool $customBool1State): static { $this->customBool1State = $customBool1State; return $this; }
    public function getCustomBool1Name(): ?string { return $this->customBool1Name; }
    public function setCustomBool1Name(?string $customBool1Name): static { $this->customBool1Name = $customBool1Name; return $this; }
    public function getCustomBool1Description(): ?string { return $this->customBool1Description; }
    public function setCustomBool1Description(?string $customBool1Description): static { $this->customBool1Description = $customBool1Description; return $this; }
    public function getCustomBool1ShowInTable(): ?bool { return $this->customBool1ShowInTable; }
    public function setCustomBool1ShowInTable(?bool $customBool1ShowInTable): static { $this->customBool1ShowInTable = $customBool1ShowInTable; return $this; }

    public function getCustomBool2State(): ?bool { return $this->customBool2State; }
    public function setCustomBool2State(?bool $customBool2State): static { $this->customBool2State = $customBool2State; return $this; }
    public function getCustomBool2Name(): ?string { return $this->customBool2Name; }
    public function setCustomBool2Name(?string $customBool2Name): static { $this->customBool2Name = $customBool2Name; return $this; }
    public function getCustomBool2Description(): ?string { return $this->customBool2Description; }
    public function setCustomBool2Description(?string $customBool2Description): static { $this->customBool2Description = $customBool2Description; return $this; }
    public function getCustomBool2ShowInTable(): ?bool { return $this->customBool2ShowInTable; }
    public function setCustomBool2ShowInTable(?bool $customBool2ShowInTable): static { $this->customBool2ShowInTable = $customBool2ShowInTable; return $this; }

    public function getCustomBool3State(): ?bool { return $this->customBool3State; }
    public function setCustomBool3State(?bool $customBool3State): static { $this->customBool3State = $customBool3State; return $this; }
    public function getCustomBool3Name(): ?string { return $this->customBool3Name; }
    public function setCustomBool3Name(?string $customBool3Name): static { $this->customBool3Name = $customBool3Name; return $this; }
    public function getCustomBool3Description(): ?string { return $this->customBool3Description; }
    public function setCustomBool3Description(?string $customBool3Description): static { $this->customBool3Description = $customBool3Description; return $this; }
    public function getCustomBool3ShowInTable(): ?bool { return $this->customBool3ShowInTable; }
    public function setCustomBool3ShowInTable(?bool $customBool3ShowInTable): static { $this->customBool3ShowInTable = $customBool3ShowInTable; return $this; }

    public function getCustomLink1State(): ?bool { return $this->customLink1State; }
    public function setCustomLink1State(?bool $customLink1State): static { $this->customLink1State = $customLink1State; return $this; }
    public function getCustomLink1Name(): ?string { return $this->customLink1Name; }
    public function setCustomLink1Name(?string $customLink1Name): static { $this->customLink1Name = $customLink1Name; return $this; }
    public function getCustomLink1Description(): ?string { return $this->customLink1Description; }
    public function setCustomLink1Description(?string $customLink1Description): static { $this->customLink1Description = $customLink1Description; return $this; }
    public function getCustomLink1ShowInTable(): ?bool { return $this->customLink1ShowInTable; }
    public function setCustomLink1ShowInTable(?bool $customLink1ShowInTable): static { $this->customLink1ShowInTable = $customLink1ShowInTable; return $this; }

    public function getCustomLink2State(): ?bool { return $this->customLink2State; }
    public function setCustomLink2State(?bool $customLink2State): static { $this->customLink2State = $customLink2State; return $this; }
    public function getCustomLink2Name(): ?string { return $this->customLink2Name; }
    public function setCustomLink2Name(?string $customLink2Name): static { $this->customLink2Name = $customLink2Name; return $this; }
    public function getCustomLink2Description(): ?string { return $this->customLink2Description; }
    public function setCustomLink2Description(?string $customLink2Description): static { $this->customLink2Description = $customLink2Description; return $this; }
    public function getCustomLink2ShowInTable(): ?bool { return $this->customLink2ShowInTable; }
    public function setCustomLink2ShowInTable(?bool $customLink2ShowInTable): static { $this->customLink2ShowInTable = $customLink2ShowInTable; return $this; }

    public function getCustomLink3State(): ?bool { return $this->customLink3State; }
    public function setCustomLink3State(?bool $customLink3State): static { $this->customLink3State = $customLink3State; return $this; }
    public function getCustomLink3Name(): ?string { return $this->customLink3Name; }
    public function setCustomLink3Name(?string $customLink3Name): static { $this->customLink3Name = $customLink3Name; return $this; }
    public function getCustomLink3Description(): ?string { return $this->customLink3Description; }
    public function setCustomLink3Description(?string $customLink3Description): static { $this->customLink3Description = $customLink3Description; return $this; }
    public function getCustomLink3ShowInTable(): ?bool { return $this->customLink3ShowInTable; }
    public function setCustomLink3ShowInTable(?bool $customLink3ShowInTable): static { $this->customLink3ShowInTable = $customLink3ShowInTable; return $this; }
}
