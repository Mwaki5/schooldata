<?php

namespace App\Enums;

/**
 * How a payment or refund was made. The method is part of the
 * duplicate-entry guard: a transaction reference is unique within its
 * method, so the same bank slip cannot be entered twice.
 */
enum PaymentMethod: string
{
    case Cash = 'CASH';
    case Bank = 'BANK';
    case MobileMoney = 'MOBILE_MONEY';
    case Card = 'CARD';

    /**
     * The methods as plain strings, for validation.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $method): string => $method->value, self::cases());
    }

    /**
     * Human-readable label for the method: "Mobile money".
     */
    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->value();
    }
}
