<?php

namespace App\Services\Offers;

class OfferStateMachineService
{
    public const APPROVED_STATUSES = [
        'draft',
        'submitted',
        'countered',
        'accepted',
        'rejected',
        'withdrawn',
        'expired',
        'cancelled',
    ];

    public const ACTIVE_STATUSES = [
        'draft',
        'submitted',
        'countered',
    ];

    public const FINAL_STATUSES = [
        'accepted',
        'rejected',
        'withdrawn',
        'expired',
        'cancelled',
    ];

    public const ALLOWED_TRANSITIONS = [
        'draft'      => ['submitted'],
        'submitted'  => ['countered', 'accepted', 'rejected', 'withdrawn', 'expired'],
        'countered'  => ['accepted', 'rejected', 'countered', 'withdrawn', 'expired'],
        'accepted'   => ['cancelled'],
        'rejected'   => [],
        'withdrawn'  => [],
        'expired'    => [],
        'cancelled'  => [],
    ];

    public function isApprovedStatus(string $status): bool
    {
        return in_array($status, self::APPROVED_STATUSES, true);
    }

    public function isActiveStatus(string $status): bool
    {
        return in_array($status, self::ACTIVE_STATUSES, true);
    }

    public function isFinalStatus(string $status): bool
    {
        return in_array($status, self::FINAL_STATUSES, true);
    }

    public function canTransition(string $from, string $to): bool
    {
        return $this->validateTransition($from, $to)['allowed'];
    }

    public function validateTransition(string $from, string $to): array
    {
        if (!$this->isApprovedStatus($from)) {
            return [
                'allowed'     => false,
                'from_status' => $from,
                'to_status'   => $to,
                'reason'      => "Unknown from-status: '{$from}' is not a recognised offer status.",
            ];
        }

        if (!$this->isApprovedStatus($to)) {
            return [
                'allowed'     => false,
                'from_status' => $from,
                'to_status'   => $to,
                'reason'      => "Unknown to-status: '{$to}' is not a recognised offer status.",
            ];
        }

        if (
            $this->isFinalStatus($from)
            && !($from === 'accepted' && $to === 'cancelled')
        ) {
            return [
                'allowed'     => false,
                'from_status' => $from,
                'to_status'   => $to,
                'reason'      => "Transition from '{$from}' to '{$to}' is not permitted: '{$from}' is a final state.",
            ];
        }

        if (!in_array($to, self::ALLOWED_TRANSITIONS[$from], true)) {
            return [
                'allowed'     => false,
                'from_status' => $from,
                'to_status'   => $to,
                'reason'      => "Transition from '{$from}' to '{$to}' is a forbidden transition.",
            ];
        }

        return [
            'allowed'     => true,
            'from_status' => $from,
            'to_status'   => $to,
            'reason'      => '',
        ];
    }
}
