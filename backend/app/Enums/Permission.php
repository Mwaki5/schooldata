<?php

namespace App\Enums;

/**
 * The application's fixed permission catalog.
 *
 * Permissions are hardcoded so they cannot be altered through the database
 * or the API; the migration mirrors this enum into Spatie's tables, where
 * roles bundle them and users are granted roles. Routes are gated on these
 * permission names, never on role names.
 *
 * Every domain carries a view / manage pair: view admits the read endpoints
 * (index, show), manage the writing ones (store, update, destroy). The two
 * read-only domains carry view alone, and the student's self-service area
 * is a single portal permission.
 */
enum Permission: string
{
    case AcademicYearsView = 'academic-years.view';
    case AcademicYearsManage = 'academic-years.manage';
    case AcademicSessionsView = 'academic-sessions.view';
    case AcademicSessionsManage = 'academic-sessions.manage';
    case ActivitiesView = 'activities.view';
    case CertificationLevelsView = 'certification-levels.view';
    case CertificationLevelsManage = 'certification-levels.manage';
    case ClassesView = 'classes.view';
    case ClassesManage = 'classes.manage';
    case CourseFeeAssignmentsView = 'course-fee-assignments.view';
    case CourseFeeAssignmentsManage = 'course-fee-assignments.manage';
    case CourseUnitsView = 'course-units.view';
    case CourseUnitsManage = 'course-units.manage';
    case CoursesView = 'courses.view';
    case CoursesManage = 'courses.manage';
    case CurriculumsView = 'curricula.view';
    case CurriculumsManage = 'curricula.manage';
    case DepartmentsView = 'departments.view';
    case DepartmentsManage = 'departments.manage';
    case EnrolmentsView = 'enrolments.view';
    case EnrolmentsManage = 'enrolments.manage';
    case ExamBodiesView = 'exam-bodies.view';
    case ExamBodiesManage = 'exam-bodies.manage';
    case ExamBodyGradesView = 'exam-body-grades.view';
    case ExamBodyGradesManage = 'exam-body-grades.manage';
    case FeeStructuresView = 'fee-structures.view';
    case FeeStructuresManage = 'fee-structures.manage';
    case RolesView = 'roles.view';
    case SessionOfferingsView = 'session-offerings.view';
    case SessionOfferingsManage = 'session-offerings.manage';
    case StaffView = 'staff.view';
    case StaffManage = 'staff.manage';
    case StudentsView = 'students.view';
    case StudentsManage = 'students.manage';
    case StudentPortal = 'student-portal';
    case TermEventsView = 'term-events.view';
    case TermEventsManage = 'term-events.manage';
    case UnitGradesView = 'unit-grades.view';
    case UnitGradesManage = 'unit-grades.manage';
    case UnitsView = 'units.view';
    case UnitsManage = 'units.manage';
    case UsersView = 'users.view';
    case UsersManage = 'users.manage';

    /**
     * Human-readable label for the permission, derived from its name:
     * "academic-years.view" reads "View Academic Years".
     */
    public function label(): string
    {
        if (! str_contains($this->value, '.')) {
            return 'Student Portal';
        }

        [$domain, $action] = explode('.', $this->value);

        return ucfirst($action).' '.str($domain)->replace('-', ' ')->title();
    }
}
