<?php

namespace App\Enums;

/**
 * The application's built-in role catalog.
 *
 * These four roles are seeded into Spatie's tables with default
 * permission bundles and human labels; after seeding they live in the
 * database like any other role — their permissions are edited through
 * the roles screen, and custom roles can be created alongside them.
 * The admin role is the one exception: it is locked and always holds
 * every permission except the student portal.
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
     * The permissions this role is seeded with — the default bundle a
     * fresh database starts from. Assignments are editable afterwards,
     * so the seeder only applies these when it first creates the role.
     *
     * A user may hold several roles; their effective permissions are the
     * union of every role's bundle. Routes and UI check these permission
     * names, never the role names themselves.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            // Every permission except the student portal, which renders
            // the signed-in account's own student data and only makes
            // sense on student accounts.
            self::Admin => array_values(array_filter(
                Permission::cases(),
                fn (Permission $permission): bool => $permission !== Permission::StudentPortal,
            )),
            // The grading console: read it, and award or edit marks.
            self::Trainer => [
                Permission::UnitGradesView,
                Permission::UnitGradesUpdate,
            ],
            self::Student => [Permission::StudentPortal],
            self::Finance => [
                Permission::FeeStructuresView,
                Permission::FeeStructuresCreate,
                Permission::FeeStructuresUpdate,
                Permission::FeeStructuresDelete,
                Permission::CourseFeeAssignmentsView,
                Permission::CourseFeeAssignmentsCreate,
                Permission::CourseFeeAssignmentsUpdate,
                Permission::CourseFeeAssignmentsDelete,
            ],
        };
    }
}
