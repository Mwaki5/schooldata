<?php

namespace App\Enums;

/**
 * The application's fixed permission catalog.
 *
 * Permissions are hardcoded so the codebase is the single source of truth:
 * the RolePermissionSeeder mirrors this enum into Spatie's tables, roles
 * bundle them, and users are granted roles. Routes and the UI are gated on
 * these permission names, never on role names.
 *
 * Each resource carries the actions its routes actually support, named
 * after the HTTP verbs they gate:
 *
 * - `view`   admits the read endpoints (index, show)
 * - `create` admits the store endpoint
 * - `update` admits the update endpoint — and state changes such as
 *   awarding marks (unit-grades) or opening the registration stage
 *   (session-offerings), which upsert rather than insert
 * - `delete` admits the destroy endpoint
 *
 * Resources without a given route simply carry no permission for it:
 * activities and users are read-only today, enrolments can only be
 * listed and removed, and the student's self-service area is a single
 * portal permission.
 */
enum Permission: string
{
    case AcademicYearsView = 'academic-years.view';
    case AcademicYearsCreate = 'academic-years.create';
    case AcademicYearsUpdate = 'academic-years.update';
    case AcademicYearsDelete = 'academic-years.delete';
    case AcademicSessionsView = 'academic-sessions.view';
    case AcademicSessionsCreate = 'academic-sessions.create';
    case AcademicSessionsUpdate = 'academic-sessions.update';
    case AcademicSessionsDelete = 'academic-sessions.delete';
    case ActivitiesView = 'activities.view';
    case CertificationLevelsView = 'certification-levels.view';
    case CertificationLevelsCreate = 'certification-levels.create';
    case CertificationLevelsUpdate = 'certification-levels.update';
    case CertificationLevelsDelete = 'certification-levels.delete';
    case ClassesView = 'classes.view';
    case CourseFeeAssignmentsView = 'course-fee-assignments.view';
    case CourseFeeAssignmentsCreate = 'course-fee-assignments.create';
    case CourseFeeAssignmentsUpdate = 'course-fee-assignments.update';
    case CourseFeeAssignmentsDelete = 'course-fee-assignments.delete';
    case CourseUnitsView = 'course-units.view';
    case CourseUnitsCreate = 'course-units.create';
    case CourseUnitsUpdate = 'course-units.update';
    case CourseUnitsDelete = 'course-units.delete';
    case CoursesView = 'courses.view';
    case CoursesCreate = 'courses.create';
    case CoursesUpdate = 'courses.update';
    case CoursesDelete = 'courses.delete';
    case CurriculumsView = 'curricula.view';
    case CurriculumsCreate = 'curricula.create';
    case CurriculumsUpdate = 'curricula.update';
    case CurriculumsDelete = 'curricula.delete';
    case DepartmentsView = 'departments.view';
    case DepartmentsCreate = 'departments.create';
    case DepartmentsUpdate = 'departments.update';
    case DepartmentsDelete = 'departments.delete';
    case EnrolmentsView = 'enrolments.view';
    case EnrolmentsDelete = 'enrolments.delete';
    case ExamBodiesView = 'exam-bodies.view';
    case ExamBodiesCreate = 'exam-bodies.create';
    case ExamBodiesUpdate = 'exam-bodies.update';
    case ExamBodiesDelete = 'exam-bodies.delete';
    case ExamBodyGradesView = 'exam-body-grades.view';
    case ExamBodyGradesCreate = 'exam-body-grades.create';
    case ExamBodyGradesUpdate = 'exam-body-grades.update';
    case ExamBodyGradesDelete = 'exam-body-grades.delete';
    case FeeStructuresView = 'fee-structures.view';
    case FeeStructuresCreate = 'fee-structures.create';
    case FeeStructuresUpdate = 'fee-structures.update';
    case FeeStructuresDelete = 'fee-structures.delete';
    case RolesView = 'roles.view';
    case RolesCreate = 'roles.create';
    case RolesUpdate = 'roles.update';
    case RolesDelete = 'roles.delete';
    case SessionOfferingsView = 'session-offerings.view';
    case SessionOfferingsUpdate = 'session-offerings.update';
    case StaffView = 'staff.view';
    case StaffCreate = 'staff.create';
    case StaffUpdate = 'staff.update';
    case StaffDelete = 'staff.delete';
    case StudentsView = 'students.view';
    case StudentsCreate = 'students.create';
    case StudentsUpdate = 'students.update';
    case StudentsDelete = 'students.delete';
    case StudentPortal = 'student-portal';
    case TermEventsView = 'term-events.view';
    case TermEventsCreate = 'term-events.create';
    case TermEventsUpdate = 'term-events.update';
    case TermEventsDelete = 'term-events.delete';
    case UnitGradesView = 'unit-grades.view';
    case UnitGradesUpdate = 'unit-grades.update';
    case UnitsView = 'units.view';
    case UnitsCreate = 'units.create';
    case UnitsUpdate = 'units.update';
    case UnitsDelete = 'units.delete';
    case UsersView = 'users.view';

    /**
     * The resource this permission belongs to: the part before the dot,
     * or the portal permission's own name.
     */
    public function resource(): string
    {
        return explode('.', $this->value, 2)[0];
    }

    /**
     * The action this permission admits, or null for the portal
     * permission, which is not split by verb.
     */
    public function action(): ?string
    {
        $segments = explode('.', $this->value, 2);

        return $segments[1] ?? null;
    }

    /**
     * Human-readable label for the permission, derived from its name:
     * "academic-years.create" reads "Create Academic Years".
     */
    public function label(): string
    {
        if ($this === self::StudentPortal) {
            return 'Student Portal';
        }

        return ucfirst($this->action().' '.str($this->resource())->replace('-', ' ')->title());
    }

    /**
     * Short label for the action alone, for grouped displays where the
     * resource is the heading: "Create".
     */
    public function actionLabel(): string
    {
        return ucfirst($this->action() ?? 'Access');
    }

    /**
     * Every permission name in the catalog, for validating payloads.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return array_map(
            fn (self $permission): string => $permission->value,
            self::cases(),
        );
    }

    /**
     * The catalog grouped by resource, in enum order, for the roles
     * screen: each group carries its permissions with their action
     * labels so a client can render the checkbox matrix without
     * re-deriving the naming scheme.
     *
     * @return list<array{name: string, label: string, permissions: list<array{name: string, label: string, action: string|null}>}>
     */
    public static function catalog(): array
    {
        $groups = [];

        foreach (self::cases() as $permission) {
            $resource = $permission->resource();

            if (! isset($groups[$resource])) {
                $groups[$resource] = [
                    'name' => $resource,
                    'label' => str($resource)->replace('-', ' ')->title(),
                    'permissions' => [],
                ];
            }

            $groups[$resource]['permissions'][] = [
                'name' => $permission->value,
                'label' => $permission->actionLabel(),
                'action' => $permission->action(),
            ];
        }

        return array_values($groups);
    }
}
