<?php

namespace MYVH\Bookings;

class Booking
{
    private int $id;
    private int $customerId;
    private int $roomId;
    private int $organisationId;

    private string $status;

    private \DateTimeImmutable $start;
    private \DateTimeImmutable $end;

    private ?string $adminEmail;

    private function __construct(
        int $id,
        int $customerId,
        int $roomId,
        int $organsationId,
        string $status,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        ?string $adminEmail
    ) {
        $this->id = $id;
        $this->customerId = $customerId;
        $this->roomId = $roomId;
        $this->organisationId = $organsationId;
        $this->status = $status;
        $this->start = $start;
        $this->end = $end;
        $this->adminEmail = $adminEmail;
    }

    /**
     * Factory: create from DB row (array)
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) $data['Id'],
            (int) $data['CustomerId'],
            (int) $data['RoomId'],
            (int) $data['OrganisationId'],
            (string) $data['Status'],
            new \DateTimeImmutable($data['Start']),
            new \DateTimeImmutable($data['End']),
            isset($data['AdminEmail']) ? (string) $data['AdminEmail'] : null
        );
    }

    /**
     * Optional: convert back to array (for persistence)
     */
    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'customer_id'   => $this->customerId,
            'room_id'       => $this->roomId,
            'organisation_id' => $this->organisationId,
            'status'        => $this->status,
            'start'         => $this->start->format('Y-m-d H:i:s'),
            'end'           => $this->end->format('Y-m-d H:i:s'),
            'admin_email'   => $this->adminEmail,
        ];
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

    public function status(): string
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

    public function confirm(): void
    {
        if ($this->isCancelled()) {
            throw new \DomainException('Cannot confirm a cancelled booking.');
        }

        $this->status = BookingStatus::CONFIRMED;
    }

    public function cancel(): void
    {
        if ($this->isCancelled()) {
            return;
        }

        $this->status = BookingStatus::CANCELLED;
    }
}