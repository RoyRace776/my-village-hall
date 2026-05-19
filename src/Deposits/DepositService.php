<?php

declare(strict_types=1);

namespace MYVH\Deposits;

use DateTime;
use MYVH\Rooms\RoomDepositRepository;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

if (!defined('ABSPATH')) {
    exit;
}

class DepositService {
    private RoomDepositRepository $room_deposit_repository;
    private LoggerInterface $logger;

    public function __construct(RoomDepositRepository $room_deposit_repository, ?LoggerInterface $logger = null) {
        $this->room_deposit_repository = $room_deposit_repository;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Evaluate whether a deposit applies for a room and booking end datetime.
     *
     * @param int $room_id
     * @param DateTime $end
     * @return array{amount: float, action: string}|null
     */
    public function evaluate(int $room_id, DateTime $end): ?array {
        $config = $this->room_deposit_repository->get($room_id);

        if (empty($config['enabled'])) {
            return null;
        }

        $amount = (float) ($config['amount'] ?? 0.0);
        if ($amount <= 0.0) {
            return null;
        }

        $configured_days = is_array($config['days'] ?? null) ? $config['days'] : [];
        if (!empty($configured_days)) {
            $end_day = strtolower($end->format('D'));
            if (!in_array($end_day, $configured_days, true)) {
                return null;
            }
        }

        $end_after = $config['end_after'] ?? null;
        if (is_string($end_after) && $end_after !== '') {
            $end_time = $end->format('H:i');
            if ($end_time <= $end_after) {
                return null;
            }
        }

        $action = (string) ($config['action'] ?? 'auto_add');
        if ($action !== 'require_review') {
            $action = 'auto_add';
        }

        return [
            'amount' => $amount,
            'action' => $action,
        ];
    }
}