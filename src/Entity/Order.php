<?php

namespace App\Entity;

use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')]
class Order
{
    #[ORM\ManyToOne(inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $customerName = null;

    #[ORM\Column(length: 30)]
    private ?string $customerPhone = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $customerAddress = null;

    #[ORM\Column(length: 30, options: ['default' => 'personal'])]
    private string $orderType = 'personal';

    #[ORM\Column]
    private ?int $total = null;

    #[ORM\Column(length: 30)]
    private ?string $status = null;

    #[ORM\Column(length: 30, options: ['default' => 'COD'])]
    private ?string $paymentMethod = 'COD';

    #[ORM\Column(length: 30, options: ['default' => 'Pending'])]
    private ?string $paymentStatus = 'Pending';

    // Payment information fields
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $paymentAccountName = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $paymentReferenceNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $paymentReceipt = null;

    /**
     * @var Collection<int, OrderItem>
     */
    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'parentOrder')]
    private Collection $orderItems;

    public function __construct()
    {
        $this->orderItems = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomerName(): ?string
    {
        return $this->customerName;
    }

    public function setCustomerName(string $customerName): static
    {
        $this->customerName = $customerName;

        return $this;
    }

    public function getCustomerPhone(): ?string
    {
        return $this->customerPhone;
    }

    public function setCustomerPhone(string $customerPhone): static
    {
        $this->customerPhone = $customerPhone;

        return $this;
    }

    public function getCustomerAddress(): ?string
    {
        return $this->customerAddress;
    }

    public function setCustomerAddress(?string $customerAddress): static
    {
        $this->customerAddress = $customerAddress;

        return $this;
    }

    public function getOrderType(): string
    {
        return $this->orderType;
    }

    public function setOrderType(string $orderType): static
    {
        if (!in_array($orderType, ['personal', 'staff_assisted', 'walk_in'], true)) {
            throw new \InvalidArgumentException('Invalid order type.');
        }

        $this->orderType = $orderType;

        return $this;
    }

    public function getTotal(): ?int
    {
        return $this->total;
    }

    public function setTotal(int $total): static
    {
        $this->total = $total;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(string $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;

        return $this;
    }

    public function getPaymentStatus(): ?string
    {
        return $this->paymentStatus;
    }

    public function setPaymentStatus(string $paymentStatus): static
    {
        $this->paymentStatus = $paymentStatus;

        return $this;
    }

    public function getPaymentAccountName(): ?string
    {
        return $this->paymentAccountName;
    }

    public function setPaymentAccountName(?string $paymentAccountName): static
    {
        $this->paymentAccountName = $paymentAccountName;

        return $this;
    }

    public function getPaymentReferenceNumber(): ?string
    {
        return $this->paymentReferenceNumber;
    }

    public function setPaymentReferenceNumber(?string $paymentReferenceNumber): static
    {
        $this->paymentReferenceNumber = $paymentReferenceNumber;

        return $this;
    }

    public function getPaymentReceipt(): ?string
    {
        return $this->paymentReceipt;
    }

    public function setPaymentReceipt(?string $paymentReceipt): static
    {
        $this->paymentReceipt = $paymentReceipt;

        return $this;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getOrderItems(): Collection
    {
        return $this->orderItems;
    }

    public function addOrderItem(OrderItem $orderItem): static
    {
        if (!$this->orderItems->contains($orderItem)) {
            $this->orderItems->add($orderItem);
            $orderItem->setParentOrder($this);
        }

        return $this;
    }

    public function removeOrderItem(OrderItem $orderItem): static
    {
        if ($this->orderItems->removeElement($orderItem)) {
            if ($orderItem->getParentOrder() === $this) {
                $orderItem->setParentOrder(null);
            }
        }

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }
}
