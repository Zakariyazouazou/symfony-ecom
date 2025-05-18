<?php

namespace App\Entity;

use App\Repository\OrderItemsRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Product;  

#[ORM\Entity(repositoryClass: OrderItemsRepository::class)]
class OrderItems
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'order_Id', targetEntity: Orders::class)]
    private ?Orders $order_id = null;

    #[ORM\ManyToOne(inversedBy: 'product_Id', targetEntity: Product::class)]
    private ?product $product_id = null;

    #[ORM\Column(nullable: true)]
    private ?int $quantity = null;

    #[ORM\Column(nullable: true)]
    private ?float $unit_price = null;

    #[ORM\Column(nullable: true)]
    private ?float $total_price = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrderId(): ?orders
    {
        return $this->order_id;
    }

    public function setOrderId(?orders $order_id): static
    {
        $this->order_id = $order_id;

        return $this;
    }

    public function getProductId(): ?Product
    {
        return $this->product_id;
    }

    public function setProductId(?Product $product_id): static
    {
        $this->product_id = $product_id;

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(?int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getUnitPrice(): ?float
    {
        return $this->unit_price;
    }

    public function setUnitPrice(?float $unit_price): static
    {
        $this->unit_price = $unit_price;

        return $this;
    }

    public function getTotalPrice(): ?float
    {
        return $this->total_price;
    }

    public function setTotalPrice(?float $total_price): static
    {
        $this->total_price = $total_price;

        return $this;
    }
}
