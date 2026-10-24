<?php

namespace App\Enums;

enum TransactionKind: string
{
    case Buy = 'buy';
    case Sell = 'sell';
    case Deposit = 'deposit';
    case Withdrawal = 'withdrawal';
    case Adjustment = 'adjustment';
    case Reversal = 'reversal';

    public function isPositive(): bool
    {
        return $this === self::Buy || $this === self::Deposit;
    }

    public function isNegative(): bool
    {
        return $this === self::Sell || $this === self::Withdrawal;
    }
}
