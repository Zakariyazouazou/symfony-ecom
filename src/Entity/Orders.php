<?php

namespace App\Entity;

use App\Repository\OrdersRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrdersRepository::class)]
#[ORM\Table(name: 'ordres')]
class Orders
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'user_Id')]
    private ?User $user_id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customer_email = null;

    #[ORM\Column(nullable: true)]
    private ?float $total_amount = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $status = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripe_payment_id = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updated_at = null;

    /**
     * @var Collection<int, OrderItems>
     */
    #[ORM\OneToMany(targetEntity: OrderItems::class, mappedBy: 'order_id')]
    private Collection $order_Id;

    public function __construct()
    {
        $this->order_Id = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): ?User
    {
        return $this->user_id;
    }

    public function setUserId(?User $user_id): static
    {
        $this->user_id = $user_id;

        return $this;
    }

    public function getCustomerEmail(): ?string
    {
        return $this->customer_email;
    }

    public function setCustomerEmail(?string $customer_email): static
    {
        $this->customer_email = $customer_email;

        return $this;
    }

    public function getTotalAmount(): ?float
    {
        return $this->total_amount;
    }

    public function setTotalAmount(?float $total_amount): static
    {
        $this->total_amount = $total_amount;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getStripePaymentId(): ?string
    {
        return $this->stripe_payment_id;
    }

    public function setStripePaymentId(?string $stripe_payment_id): static
    {
        $this->stripe_payment_id = $stripe_payment_id;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(?\DateTimeImmutable $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updated_at): static
    {
        $this->updated_at = $updated_at;

        return $this;
    }

    /**
     * @return Collection<int, OrderItems>
     */
    public function getOrderId(): Collection
    {
        return $this->order_Id;
    }

    public function addOrderId(OrderItems $orderId): static
    {
        if (!$this->order_Id->contains($orderId)) {
            $this->order_Id->add($orderId);
            $orderId->setOrderId($this);
        }

        return $this;
    }

    public function removeOrderId(OrderItems $orderId): static
    {
        if ($this->order_Id->removeElement($orderId)) {
            // set the owning side to null (unless already changed)
            if ($orderId->getOrderId() === $this) {
                $orderId->setOrderId(null);
            }
        }

        return $this;
    }
}
