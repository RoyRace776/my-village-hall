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
    private readonly string $description;
    private readonly bool $isPublic;
    private readonly string $roomName;
    private readonly string $venueName;
    private readonly bool $roomIsPublic;
    private readonly int $recurringPatternId;

    private function __construct(
        int $id,
        int $customerId,
        int $roomId,
        int $organisationId,
        BookingStatus $status,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        ?string $adminEmail,
        string $description,
        bool $isPublic,
        string $roomName,
        string $venueName,
        bool $roomIsPublic,
        int $recurringPatternId = 0
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
        $this->description = $description;
        $this->isPublic = $isPublic;
        $this->roomName = $roomName;
        $this->venueName = $venueName;
        $this->roomIsPublic = $roomIsPublic;
        $this->recurringPatternId = $recurringPatternId;
    }

    /**
     * Factory: create from DB row (array)
     */
    public static function fromDatabaseRow(array $data): self
    {
        return new self(
            (int) ($data['Id'] ?? 0),
            (int) ($data['CustomerId'] ?? 0),
            (int) ($data['RoomId'] ?? 0),
            (int) ($data['OrganisationId'] ?? 0),
            $data['Status'] instanceof BookingStatus
                ? $data['Status']
                : BookingStatus::from((string) ($data['Status'] ?? '')),
            $data['Start'] instanceof \DateTimeImmutable
                ? $data['Start']
                : new \DateTimeImmutable((string) ($data['Start'] ?? 'now')),
            $data['End'] instanceof \DateTimeImmutable
                ? $data['End']
                : new \DateTimeImmutable((string) ($data['End'] ?? 'now')),
            isset($data['AdminEmail']) ? (string) $data['AdminEmail'] : null,
            (string) ($data['Description'] ?? ''),
            !empty($data['Public']),
            (string) ($data['RoomName'] ?? ''),
            (string) ($data['VenueName'] ?? ''),
            !empty($data['IsPublic']),
            (int) ($data['RecurringPatternId'] ?? 0)
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

    public function description(): string
    {
        return $this->description;
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function roomName(): string
    {
        return $this->roomName;
    }

    public function venueName(): string
    {
        return $this->venueName;
    }

    public function roomIsPublic(): bool
    {
        return $this->roomIsPublic;
    }

    public function recurringPatternId(): int
    {
        return $this->recurringPatternId;
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
            $this->adminEmail,
            $this->description,
            $this->isPublic,
            $this->roomName,
            $this->venueName,
            $this->roomIsPublic,
            $this->recurringPatternId
        );
    }
}
