<?php

namespace App\Enums;

/**
 * The application's fixed role catalog.
 *
 * Roles are hardcoded so they cannot be altered through the database or
 * the API; the roles endpoint simply renders this enum.
 */
enum Role: string
{
    case Admin = 'admin';
    case Trainer = 'trainer';
    case Student = 'student';
    case Finance = 'finance';

    /**
     * Human-readable label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Trainer => 'Trainer',
            self::Student => 'Student',
            self::Finance => 'Finance',
        };
    }

    /**
     * Short description of what the role is for.
     */
    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Full access to every module and setting.',
            self::Trainer => 'Teaches classes and manages academic delivery.',
            self::Student => 'Enrolled learner with view-only access to own data.',
            self::Finance => 'Manages fees, invoicing and financial records.',
        };
    }

    /**
     * The hardcoded catalog served by GET /api/roles.
     *
     * Ids are stable positional values (1-4) so clients can key by them.
     *
     * @return list<array{id: int, name: string, slug: string, description: string}>
     */
    public static function catalog(): array
    {
        return array_map(
            fn (self $role, int $index): array => [
                'id' => $index + 1,
                'name' => $role->label(),
                'slug' => $role->value,
                'description' => $role->description(),
            ],
            self::cases(),
            array_keys(self::cases()),
        );
    }
}
