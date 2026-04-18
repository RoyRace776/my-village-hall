<?php

namespace MYVH\Bookings;

class Booking
{
    private readonly int $id;
    private readonly int $customerId;
    private readonly int $roomId;
    private readonly int $organisationId;
    private readonly BookingStatus $status;
    private readonly \DateTimeImmutable $start;
    private readonly \DateTimeImmutable $end;
    private readonly ?string $adminEmail;

    private function __construct(
        int $id,
        int $customerId,
        int $roomId,
        int $organisationId,
        BookingStatus $status,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        ?string $adminEmail
    ) {
        if ($end <= $start) {
            throw new \InvalidArgumentException('Booking end must be after start.');
        }

        $this->id = $id;
        $this->customerId = $customerId;
        $this->roomId = $roomId;
        $this->organisationId = $organisationId;
        $this->status = $status;
        $this->start = $start;
        $this->end = $end;
        $this->adminEmail = $adminEmail;
    }

    /**
     * Factory: create from DB row (array)
     */
    public static function fromDatabaseRow(array $data): self
    {
        return new self(
            $data['Id'],
            $data['CustomerId'],
            $data['RoomId'],
            $data['OrganisationId'],
            $data['Status'],
            $data['Start'],
            $data['End'],
            $data['AdminEmail'] ?? null
        );
    }

    // -------------------------
    // Getters
    // -------------------------

    public function id(): int
    {
        return $this->id;
    }

    public function customerId(): int
    {
        return $this->customerId;
    }

    public function roomId(): int
    {
        return $this->roomId;
    }

    public function organisationId(): int
    {
        return $this->organisationId;
    }

    public function status(): BookingStatus
    {
        return $this->status;
    }

    public function start(): \DateTimeImmutable
    {
        return $this->start;
    }

    public function end(): \DateTimeImmutable
    {
        return $this->end;
    }

    public function adminEmail(): ?string
    {
        return $this->adminEmail;
    }

    // -------------------------
    // Domain logic
    // -------------------------

    public function isPending(): bool
    {
        return $this->status === BookingStatus::PENDING;
    }

    public function isConfirmed(): bool
    {
        return $this->status === BookingStatus::CONFIRMED;
    }

    public function isCancelled(): bool
    {
        return $this->status === BookingStatus::CANCELLED;
    }

    public function hasAdminEmail(): bool
    {
        return !empty($this->adminEmail);
    }

    public function durationInMinutes(): int
    {
        return (int) (($this->end->getTimestamp() - $this->start->getTimestamp()) / 60);
    }

    public function overlaps(self $other): bool
    {
        return $this->start < $other->end() && $this->end > $other->start();
    }

    // -------------------------
    // State changes (optional but powerful)
    // -------------------------

    public function confirm(): self
    {
        if ($this->isCancelled()) {
            throw new \DomainException('Cannot confirm a cancelled booking.');
        }

        return $this->withStatus(BookingStatus::CONFIRMED);
    }

    public function cancel(): self
    {
        return $this->withStatus(BookingStatus::CANCELLED);
    }

    private function withStatus(BookingStatus $status): self
    {
        return new self(
            $this->id,
            $this->customerId,
            $this->roomId,
            $this->organisationId,
            $status,
            $this->start,
            $this->end,
            $this->adminEmail
        );
    }
}