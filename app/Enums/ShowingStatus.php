<?php

namespace App\Enums;

class ShowingStatus
{
    const REQUESTED = 'requested';
    const APPROVED  = 'approved';
    const DECLINED  = 'declined';
    const CANCELED  = 'canceled';
    const COMPLETED = 'completed';

    public static function all(): array
    {
        return [
            self::REQUESTED,
            self::APPROVED,
            self::DECLINED,
            self::CANCELED,
            self::COMPLETED,
        ];
    }
}
