<?php
namespace MYVH\Rooms;

if (!defined('ABSPATH')) {
    exit;
}

class RoomColour {

    /**
     * @return string[]
     */
    public static function palette(): array {
        return [
            '#c97a4a',
            '#4f7cac',
            '#6c9a5b',
            '#a85d7f',
            '#8d6cab',
            '#c2a444',
            '#4d9a9a',
            '#b86b4b',
        ];
    }

    public static function normalise($colour): string {
        $value = strtolower(trim((string) $colour));

        if ($value === '') {
            return '';
        }

        if (!preg_match('/^#[0-9a-f]{6}$/', $value)) {
            return '';
        }

        return $value;
    }

    public static function is_valid($colour): bool {
        return self::normalise($colour) !== '';
    }

    public static function fallback($room_id = 0): string {
        $palette = self::palette();
        $index = max(0, ((int) $room_id) - 1) % count($palette);

        return $palette[$index];
    }

    public static function resolve($colour, $room_id = 0): string {
        $normalised = self::normalise($colour);

        if ($normalised !== '') {
            return $normalised;
        }

        return self::fallback($room_id);
    }
}
