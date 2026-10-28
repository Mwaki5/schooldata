<?php

namespace App\Enums;

/**
 * What an invoice charges for. The kind is a fixed vocabulary so
 * billing can be reported on later; the free-text description on the
 * invoice carries the specifics ("Term 2 tuition", "Hostel Block A").
 *
 * `FeeInvoice` is the structured one: it is raised from a fee
 * structure, whose live item amounts decide the charge. Every other
 * type is a free-form "other invoice" the finance desk amounts by hand.
 */
enum InvoiceType: string
{
    case FeeInvoice = 'FEE_INVOICE';
    case Penalty = 'PENALTY';
    case Hostel = 'HOSTEL';
    case Transport = 'TRANSPORT';
    case ExamFee = 'EXAM_FEE';
    case Other = 'OTHER';

    /**
     * The types as plain strings, for validation.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $type): string => $type->value, self::cases());
    }

    /**
     * The types an "other invoice" may carry — everything except the
     * structured fee invoice, which has its own endpoint and rules.
     *
     * @return list<string>
     */
    public static function otherValues(): array
    {
        return array_values(array_filter(
            self::values(),
            fn (string $value): bool => $value !== self::FeeInvoice->value,
        ));
    }
}
