<?php

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $sku = null;

    #[ORM\Column(nullable: true)]
    private ?float $price = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private ?int $stock = 0;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, Category>
     */
    #[ORM\ManyToMany(targetEntity: Category::class, inversedBy: 'products')]
    private Collection $categories;

    /**
     * @var Collection<int, ProductImage>
     */
    #[ORM\OneToMany(targetEntity: ProductImage::class, mappedBy: 'product_id')]
    private Collection $filename;

    /**
     * @var Collection<int, ProductImage>
     */
    #[ORM\OneToMany(targetEntity: ProductImage::class, mappedBy: 'product_id')]
    private Collection $Product_Id;

    /**
     * @var Collection<int, Orders>
     */
    #[ORM\OneToMany(targetEntity: Orders::class, mappedBy: 'user_id')]
    private Collection $customer_email;

    /**
     * @var Collection<int, Orders>
     */
    #[ORM\OneToMany(targetEntity: Orders::class, mappedBy: 'user_id')]
    private Collection $user_Id;

    /**
     * @var Collection<int, OrderItems>
     */
    #[ORM\OneToMany(targetEntity: OrderItems::class, mappedBy: 'product_id')]
    private Collection $product_Id;

    public function __construct()
    {
        $this->categories = new ArrayCollection();
        $this->filename = new ArrayCollection();
        $this->Product_Id = new ArrayCollection();
        $this->customer_email = new ArrayCollection();
        $this->user_Id = new ArrayCollection();
        $this->product_Id = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function setSku(?string $sku): static
    {
        $this->sku = $sku;

        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(?float $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getStock(): ?int
    {
        return $this->stock;
    }

    public function setStock(?int $stock): static
    {
        $this->stock = $stock;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeImmutable $createdAt): static
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

    /**
     * @return Collection<int, Category>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(Category $category): static
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
        }

        return $this;
    }

    public function removeCategory(Category $category): static
    {
        $this->categories->removeElement($category);

        return $this;
    }

    /**
     * @return Collection<int, ProductImage>
     */
    public function getFilename(): Collection
    {
        return $this->filename;
    }

    public function addFilename(ProductImage $filename): static
    {
        if (!$this->filename->contains($filename)) {
            $this->filename->add($filename);
            $filename->setProductId($this);
        }

        return $this;
    }

    public function removeFilename(ProductImage $filename): static
    {
        if ($this->filename->removeElement($filename)) {
            // set the owning side to null (unless already changed)
            if ($filename->getProductId() === $this) {
                $filename->setProductId(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ProductImage>
     */
    public function getProductId(): Collection
    {
        return $this->Product_Id;
    }

    public function addProductId(ProductImage $productId): static
    {
        if (!$this->Product_Id->contains($productId)) {
            $this->Product_Id->add($productId);
            $productId->setProductId($this);
        }

        return $this;
    }

    public function removeProductId(ProductImage $productId): static
    {
        if ($this->Product_Id->removeElement($productId)) {
            // set the owning side to null (unless already changed)
            if ($productId->getProductId() === $this) {
                $productId->setProductId(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Orders>
     */
    public function getCustomerEmail(): Collection
    {
        return $this->customer_email;
    }

    public function addCustomerEmail(Orders $customerEmail): static
    {
        if (!$this->customer_email->contains($customerEmail)) {
            $this->customer_email->add($customerEmail);
            $customerEmail->setUserId($this);
        }

        return $this;
    }

    public function removeCustomerEmail(Orders $customerEmail): static
    {
        if ($this->customer_email->removeElement($customerEmail)) {
            // set the owning side to null (unless already changed)
            if ($customerEmail->getUserId() === $this) {
                $customerEmail->setUserId(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Orders>
     */
    public function getUserId(): Collection
    {
        return $this->user_Id;
    }

    public function addUserId(Orders $userId): static
    {
        if (!$this->user_Id->contains($userId)) {
            $this->user_Id->add($userId);
            $userId->setUserId($this);
        }

        return $this;
    }

    public function removeUserId(Orders $userId): static
    {
        if ($this->user_Id->removeElement($userId)) {
            // set the owning side to null (unless already changed)
            if ($userId->getUserId() === $this) {
                $userId->setUserId(null);
            }
        }

        return $this;
    }
}
