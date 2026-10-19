<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\AcademicSession;
use App\Models\AcademicYear;
use App\Models\CertificationLevel;
use App\Models\Course;
use App\Models\CourseEnrolment;
use App\Models\Curriculum;
use App\Models\Department;
use App\Models\ExamBody;
use App\Models\ExamBodyGrade;
use App\Models\Staff;
use App\Models\StudentProfile;
use App\Models\TermEvent;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * A coherent Kenyan TVET demo dataset for the modules that ship empty.
 *
 * Reference rows (exam bodies, departments) are resolved by name prefix and
 * created only when missing, so the seeder reuses whatever the UI already
 * created. Every other row is keyed on a natural identifier - name, code,
 * admission number, employment number - which makes re-running the seeder
 * idempotent.
 *
 * The WithoutModelEvents trait is detected by Seeder::call(), so none of
 * these rows reach the activity log: the audit trail stays a record of
 * real human actions only.
 */
class DemoDataSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The single password every demo account shares.
     */
    private const DEMO_PASSWORD = 'password123';

    /**
     * Exam bodies, resolved by name prefix and created only when absent.
     *
     * @var list<array{key: string, prefix: string, name: string, code: string, website: string|null, description: string}>
     */
    private const EXAM_BODIES = [
        [
            'key' => 'knec',
            'prefix' => 'Kenya National',
            'name' => 'Kenya National Examination Council',
            'code' => 'KNEC',
            'website' => 'https://www.knec.ac.ke',
            'description' => 'National examinations and curriculum assessment body.',
        ],
        [
            'key' => 'cdacc',
            'prefix' => 'Curriculum Development',
            'name' => 'Curriculum Development, Assessment and Certification Council',
            'code' => 'CDACC',
            'website' => 'https://www.cdacc.or.ke',
            'description' => 'Competency-based curriculum development and certification.',
        ],
        [
            'key' => 'nitta',
            'prefix' => 'National Industrial',
            'name' => 'National Industrial Training Authority',
            'code' => 'NITA',
            'website' => 'https://www.nita.go.ke',
            'description' => 'Industrial skills standards, testing and certification.',
        ],
        [
            'key' => 'mago',
            'prefix' => 'Mago',
            'name' => 'Mago Technical and Vocational College',
            'code' => 'MAGO',
            'website' => null,
            'description' => 'Host TVET institution and in-house award body.',
        ],
    ];

    /**
     * Departments, resolved by name prefix the same way.
     *
     * @var list<array{key: string, prefix: string, name: string}>
     */
    private const DEPARTMENTS = [
        ['key' => 'computing', 'prefix' => 'Computing', 'name' => 'Computing and Informatics'],
        ['key' => 'electrical', 'prefix' => 'Electrical', 'name' => 'Electrical and Electronics'],
        ['key' => 'fashion', 'prefix' => 'Fashion', 'name' => 'Fashion and Design'],
        ['key' => 'social', 'prefix' => 'Social', 'name' => 'Social Work'],
        ['key' => 'cosmetology', 'prefix' => 'Cosmetology', 'name' => 'Cosmetology'],
    ];

    /**
     * Certification levels per awarding body; keyed `body.level`.
     *
     * @var array<string, array{body: string, name: string, code: string, sequence: int, description: string}>
     */
    private const CERTIFICATION_LEVELS = [
        'knec.diploma' => [
            'body' => 'knec',
            'name' => 'Diploma',
            'code' => 'DIP',
            'sequence' => 1,
            'description' => 'KNEC diploma awarded on completion of a diploma programme.',
        ],
        'knec.certificate' => [
            'body' => 'knec',
            'name' => 'Certificate',
            'code' => 'CERT',
            'sequence' => 2,
            'description' => 'KNEC certificate awarded on completion of a craft programme.',
        ],
        'knec.artisan' => [
            'body' => 'knec',
            'name' => 'Artisan Certificate',
            'code' => 'ART',
            'sequence' => 3,
            'description' => 'KNEC artisan certificate for basic trade programmes.',
        ],
        'knec.higher_diploma' => [
            'body' => 'knec',
            'name' => 'Higher Diploma',
            'code' => 'HDIP',
            'sequence' => 4,
            'description' => 'KNEC higher diploma for advanced diploma study.',
        ],
        'cdacc.diploma' => [
            'body' => 'cdacc',
            'name' => 'Diploma',
            'code' => 'DIP',
            'sequence' => 1,
            'description' => 'CBET diploma issued under competency-based training.',
        ],
        'cdacc.certificate' => [
            'body' => 'cdacc',
            'name' => 'Certificate',
            'code' => 'CERT',
            'sequence' => 2,
            'description' => 'CBET certificate issued under competency-based training.',
        ],
    ];

    /**
     * Academic years; the 2026/2027 year is the one currently running.
     *
     * @var list<array{name: string, start_date: string, end_date: string, description: string, is_active: bool}>
     */
    private const ACADEMIC_YEARS = [
        [
            'name' => '2024/2025 Academic Year',
            'start_date' => '2024-09-02',
            'end_date' => '2025-08-29',
            'description' => 'Historical academic year kept for completed enrolments.',
            'is_active' => false,
        ],
        [
            'name' => '2025/2026 Academic Year',
            'start_date' => '2025-09-01',
            'end_date' => '2026-08-28',
            'description' => 'Preceding academic year, September to August.',
            'is_active' => false,
        ],
        [
            'name' => '2026/2027 Academic Year',
            'start_date' => '2026-09-01',
            'end_date' => '2027-08-31',
            'description' => 'Current academic year, September to August.',
            'is_active' => true,
        ],
    ];

    /**
     * Three terms per academic year; only the term covering today is active.
     *
     * @var list<array{year: string, name: string, start_date: string, end_date: string, is_active: bool}>
     */
    private const ACADEMIC_SESSIONS = [
        ['year' => '2024/2025 Academic Year', 'name' => 'Term 1, 2024/2025', 'start_date' => '2024-09-02', 'end_date' => '2024-12-06', 'is_active' => false],
        ['year' => '2024/2025 Academic Year', 'name' => 'Term 2, 2024/2025', 'start_date' => '2025-01-06', 'end_date' => '2025-04-11', 'is_active' => false],
        ['year' => '2024/2025 Academic Year', 'name' => 'Term 3, 2024/2025', 'start_date' => '2025-04-28', 'end_date' => '2025-08-29', 'is_active' => false],
        ['year' => '2025/2026 Academic Year', 'name' => 'Term 1, 2025/2026', 'start_date' => '2025-09-01', 'end_date' => '2025-12-05', 'is_active' => false],
        ['year' => '2025/2026 Academic Year', 'name' => 'Term 2, 2025/2026', 'start_date' => '2026-01-05', 'end_date' => '2026-04-10', 'is_active' => false],
        ['year' => '2025/2026 Academic Year', 'name' => 'Term 3, 2025/2026', 'start_date' => '2026-04-27', 'end_date' => '2026-08-28', 'is_active' => false],
        ['year' => '2026/2027 Academic Year', 'name' => 'Term 1, 2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2026-12-18', 'is_active' => true],
        ['year' => '2026/2027 Academic Year', 'name' => 'Term 2, 2026/2027', 'start_date' => '2027-01-04', 'end_date' => '2027-04-09', 'is_active' => false],
        ['year' => '2026/2027 Academic Year', 'name' => 'Term 3, 2026/2027', 'start_date' => '2027-04-26', 'end_date' => '2027-08-31', 'is_active' => false],
    ];

    /**
     * Calendar events bound to their academic session; the current term
     * carries the live ones. Names repeat across sessions, so the session
     * is part of the identity.
     *
     * @var list<array{session: string, name: string, start_date: string, end_date: string, is_active: bool}>
     */
    private const TERM_EVENTS = [
        ['session' => 'Term 1, 2026/2027', 'name' => 'Session Registration', 'start_date' => '2026-09-01', 'end_date' => '2026-09-05', 'is_active' => true],
        ['session' => 'Term 1, 2026/2027', 'name' => 'Midterm Exam', 'start_date' => '2026-11-02', 'end_date' => '2026-11-13', 'is_active' => true],
        ['session' => 'Term 1, 2026/2027', 'name' => 'End Term Exams', 'start_date' => '2026-12-14', 'end_date' => '2026-12-18', 'is_active' => false],
        ['session' => 'Term 1, 2026/2027', 'name' => 'School Break', 'start_date' => '2026-12-19', 'end_date' => '2027-01-03', 'is_active' => false],
        ['session' => 'Term 2, 2026/2027', 'name' => 'Session Registration', 'start_date' => '2027-01-04', 'end_date' => '2027-01-08', 'is_active' => false],
        ['session' => 'Term 2, 2026/2027', 'name' => 'Graduation', 'start_date' => '2027-04-08', 'end_date' => '2027-04-08', 'is_active' => false],
    ];

    /**
     * Grading scales per exam body; keyed by body key.
     *
     * @var array<string, list<array{grade: string, start: float, end: float, remark: string, sequence: int}>>
     */
    private const EXAM_BODY_GRADES = [
        'knec' => [
            ['grade' => 'A', 'start' => 80.00, 'end' => 100.00, 'remark' => 'Excellent', 'sequence' => 1],
            ['grade' => 'B+', 'start' => 70.00, 'end' => 79.99, 'remark' => 'Very good', 'sequence' => 2],
            ['grade' => 'B', 'start' => 60.00, 'end' => 69.99, 'remark' => 'Good', 'sequence' => 3],
            ['grade' => 'C+', 'start' => 50.00, 'end' => 59.99, 'remark' => 'Fairly good', 'sequence' => 4],
            ['grade' => 'C', 'start' => 40.00, 'end' => 49.99, 'remark' => 'Average pass', 'sequence' => 5],
            ['grade' => 'D+', 'start' => 35.00, 'end' => 39.99, 'remark' => 'Marginal pass', 'sequence' => 6],
            ['grade' => 'D', 'start' => 30.00, 'end' => 34.99, 'remark' => 'Low pass', 'sequence' => 7],
            ['grade' => 'E', 'start' => 0.00, 'end' => 29.99, 'remark' => 'Below minimum requirement', 'sequence' => 8],
        ],
        'cdacc' => [
            ['grade' => 'Competent with Distinction', 'start' => 80.00, 'end' => 100.00, 'remark' => 'Outstanding performance', 'sequence' => 1],
            ['grade' => 'Competent with Credit', 'start' => 70.00, 'end' => 79.99, 'remark' => 'Above the expected standard', 'sequence' => 2],
            ['grade' => 'Competent', 'start' => 60.00, 'end' => 69.99, 'remark' => 'Meets the occupational standard', 'sequence' => 3],
            ['grade' => 'Not Yet Competent', 'start' => 0.00, 'end' => 59.99, 'remark' => 'Re-assessment required', 'sequence' => 4],
        ],
        'nitta' => [
            ['grade' => 'Distinction', 'start' => 80.00, 'end' => 100.00, 'remark' => 'Distinction standard', 'sequence' => 1],
            ['grade' => 'Credit', 'start' => 70.00, 'end' => 79.99, 'remark' => 'Credit standard', 'sequence' => 2],
            ['grade' => 'Pass', 'start' => 60.00, 'end' => 69.99, 'remark' => 'Pass standard', 'sequence' => 3],
            ['grade' => 'Fail', 'start' => 0.00, 'end' => 59.99, 'remark' => 'Retest required', 'sequence' => 4],
        ],
    ];

    /**
     * Curricula; keyed for the courses and units to reference.
     *
     * @var array<string, array{body: string, name: string}>
     */
    private const CURRICULA = [
        'k_nec_diploma' => ['body' => 'knec', 'name' => 'TVET Diploma Curriculum'],
        'k_nec_certificate' => ['body' => 'knec', 'name' => 'TVET Certificate Curriculum'],
        'k_nec_artisan' => ['body' => 'knec', 'name' => 'Artisan Trade Curriculum'],
        'cbet' => ['body' => 'cdacc', 'name' => 'Competency-Based Education and Training (CBET)'],
        'nita' => ['body' => 'nitta', 'name' => 'National Technical Skills Curriculum'],
    ];

    /**
     * Programmes, each bound to a department, an award level and the
     * curriculum their units are written against.
     *
     * @var array<string, array{initials: string, name: string, department: string|null, level: string, curriculum: string, duration_months: int, description: string}>
     */
    private const COURSES = [
        'DIT' => [
            'initials' => 'DIT',
            'name' => 'Diploma in Information Communication Technology',
            'department' => 'computing',
            'level' => 'knec.diploma',
            'curriculum' => 'k_nec_diploma',
            'duration_months' => 24,
            'description' => 'Software, networking and data skills for the ICT industry.',
        ],
        'CIT' => [
            'initials' => 'CIT',
            'name' => 'Certificate in Information Communication Technology',
            'department' => 'computing',
            'level' => 'knec.certificate',
            'curriculum' => 'k_nec_certificate',
            'duration_months' => 12,
            'description' => 'Foundational ICT support, networking and office productivity.',
        ],
        'DEEE' => [
            'initials' => 'DEEE',
            'name' => 'Diploma in Electrical and Electronic Engineering',
            'department' => 'electrical',
            'level' => 'knec.diploma',
            'curriculum' => 'k_nec_diploma',
            'duration_months' => 24,
            'description' => 'Electrical power, machines and electronics engineering pathway.',
        ],
        'CEIE' => [
            'initials' => 'CEIE',
            'name' => 'Certificate in Electrical Installation and Maintenance',
            'department' => 'electrical',
            'level' => 'knec.certificate',
            'curriculum' => 'k_nec_certificate',
            'duration_months' => 12,
            'description' => 'Domestic and industrial electrical installation skills.',
        ],
        'DFDT' => [
            'initials' => 'DFDT',
            'name' => 'Diploma in Fashion Design and Garment Technology',
            'department' => 'fashion',
            'level' => 'cdacc.diploma',
            'curriculum' => 'cbet',
            'duration_months' => 24,
            'description' => 'Fashion design, garment construction and textile technology.',
        ],
        'CFDT' => [
            'initials' => 'CFDT',
            'name' => 'Certificate in Fashion Design',
            'department' => 'fashion',
            'level' => 'cdacc.certificate',
            'curriculum' => 'cbet',
            'duration_months' => 12,
            'description' => 'Garment construction and fashion essentials certificate.',
        ],
        'CBTH' => [
            'initials' => 'CBTH',
            'name' => 'Certificate in Beauty Therapy and Hairdressing',
            'department' => 'cosmetology',
            'level' => 'cdacc.certificate',
            'curriculum' => 'cbet',
            'duration_months' => 12,
            'description' => 'Beauty therapy, hairdressing and salon practice.',
        ],
        'DSWCD' => [
            'initials' => 'DSWCD',
            'name' => 'Diploma in Social Work and Community Development',
            'department' => 'social',
            'level' => 'knec.diploma',
            'curriculum' => 'k_nec_diploma',
            'duration_months' => 24,
            'description' => 'Community development and social work practice.',
        ],
        'AEM' => [
            'initials' => 'AEM',
            'name' => 'Artisan Certificate in Masonry',
            'department' => null,
            'level' => 'knec.artisan',
            'curriculum' => 'k_nec_artisan',
            'duration_months' => 6,
            'description' => 'Basic masonry and building construction trade skills.',
        ],
    ];

    /**
     * Units per course; the code is assembled as `{course code} {suffix}`.
     *
     * @var array<string, list<array{suffix: string, name: string, core: bool}>>
     */
    private const UNITS = [
        'DIT' => [
            ['suffix' => '1101', 'name' => 'ICT Fundamentals', 'core' => true],
            ['suffix' => '1102', 'name' => 'Programming Fundamentals', 'core' => true],
            ['suffix' => '1103', 'name' => 'Database Systems', 'core' => true],
            ['suffix' => '1104', 'name' => 'Computer Networks', 'core' => true],
            ['suffix' => '1201', 'name' => 'Entrepreneurship for ICT', 'core' => false],
        ],
        'CIT' => [
            ['suffix' => '1101', 'name' => 'ICT Applications', 'core' => true],
            ['suffix' => '1102', 'name' => 'Computer Networking Basics', 'core' => true],
            ['suffix' => '1103', 'name' => 'Web Design', 'core' => true],
            ['suffix' => '1104', 'name' => 'Data Communication', 'core' => true],
            ['suffix' => '1201', 'name' => 'Entrepreneurship Skills', 'core' => false],
        ],
        'DEEE' => [
            ['suffix' => '1101', 'name' => 'Electrical Science I', 'core' => true],
            ['suffix' => '1102', 'name' => 'Electrical Installation I', 'core' => true],
            ['suffix' => '1103', 'name' => 'Electronics I', 'core' => true],
            ['suffix' => '1104', 'name' => 'Electrical Drawing', 'core' => true],
            ['suffix' => '1201', 'name' => 'Project Management', 'core' => false],
        ],
        'CEIE' => [
            ['suffix' => '1101', 'name' => 'Electrical Science', 'core' => true],
            ['suffix' => '1102', 'name' => 'Domestic Installation', 'core' => true],
            ['suffix' => '1103', 'name' => 'Electrical Machine Installation', 'core' => true],
            ['suffix' => '1104', 'name' => 'Workshop Technology', 'core' => true],
            ['suffix' => '1201', 'name' => 'Entrepreneurship Skills', 'core' => false],
        ],
        'DFDT' => [
            ['suffix' => '1101', 'name' => 'Fashion Design I', 'core' => true],
            ['suffix' => '1102', 'name' => 'Garment Construction I', 'core' => true],
            ['suffix' => '1103', 'name' => 'Textile Science', 'core' => true],
            ['suffix' => '1104', 'name' => 'Pattern Drafting', 'core' => true],
            ['suffix' => '1201', 'name' => 'Fashion Entrepreneurship', 'core' => false],
        ],
        'CFDT' => [
            ['suffix' => '1101', 'name' => 'Garment Construction', 'core' => true],
            ['suffix' => '1102', 'name' => 'Fashion Illustration', 'core' => true],
            ['suffix' => '1103', 'name' => 'Textile Studies', 'core' => true],
            ['suffix' => '1104', 'name' => 'Clothing Production', 'core' => true],
            ['suffix' => '1201', 'name' => 'Entrepreneurship Skills', 'core' => false],
        ],
        'CBTH' => [
            ['suffix' => '1101', 'name' => 'Skin Care', 'core' => true],
            ['suffix' => '1102', 'name' => 'Hair Styling', 'core' => true],
            ['suffix' => '1103', 'name' => 'Nail Technology', 'core' => true],
            ['suffix' => '1104', 'name' => 'Salon Management', 'core' => true],
            ['suffix' => '1201', 'name' => 'Business Entrepreneurship', 'core' => false],
        ],
        'DSWCD' => [
            ['suffix' => '1101', 'name' => 'Introduction to Community Development', 'core' => true],
            ['suffix' => '1102', 'name' => 'Social Case Work', 'core' => true],
            ['suffix' => '1103', 'name' => 'Research Methods', 'core' => true],
            ['suffix' => '1104', 'name' => 'Counselling Skills', 'core' => true],
            ['suffix' => '1201', 'name' => 'Entrepreneurship Skills', 'core' => false],
        ],
        'AEM' => [
            ['suffix' => '1101', 'name' => 'Building Construction', 'core' => true],
            ['suffix' => '1102', 'name' => 'Stone Masonry', 'core' => true],
            ['suffix' => '1103', 'name' => 'Concrete Technology', 'core' => true],
            ['suffix' => '1104', 'name' => 'Site Organisation', 'core' => true],
            ['suffix' => '1201', 'name' => 'Entrepreneurship Skills', 'core' => false],
        ],
    ];

    /**
     * Students with their accounts, profiles and one enrolment each; the
     * admission number doubles as the login id, matching the controller.
     *
     * @var list<array{admission_no: string, first_name: string, middle_name: string|null, last_name: string, gender: string, date_of_birth: string, county: string, phone: string, national_id: string, course: string, status: string, session: string, enrolment_date: string, enrolment_status: string, remarks: string|null, sponsored_by: string|null, index_no: string|null, exam_year: string|null, previous_school: string|null}>
     */
    private const STUDENTS = [
        [
            'admission_no' => 'ADM-2026-0001',
            'first_name' => 'Brian',
            'middle_name' => 'Otieno',
            'last_name' => 'Ochieng',
            'gender' => 'male',
            'date_of_birth' => '2003-04-12',
            'county' => 'Nairobi',
            'phone' => '+254722123456',
            'national_id' => '290412011',
            'course' => 'DIT',
            'status' => 'active',
            'session' => 'Term 1, 2026/2027',
            'enrolment_date' => '2026-09-01',
            'enrolment_status' => 'ENROLLED',
            'remarks' => null,
            'sponsored_by' => null,
            'index_no' => null,
            'exam_year' => null,
            'previous_school' => null,
        ],
        [
            'admission_no' => 'ADM-2026-0002',
            'first_name' => 'Faith',
            'middle_name' => 'Njeri',
            'last_name' => 'Mwangi',
            'gender' => 'female',
            'date_of_birth' => '2004-09-03',
            'county' => 'Kiambu',
            'phone' => '+254733234567',
            'national_id' => '300903022',
            'course' => 'DFDT',
            'status' => 'active',
            'session' => 'Term 1, 2026/2027',
            'enrolment_date' => '2026-09-01',
            'enrolment_status' => 'ENROLLED',
            'remarks' => null,
            'sponsored_by' => null,
            'index_no' => null,
            'exam_year' => null,
            'previous_school' => null,
        ],
        [
            'admission_no' => 'ADM-2026-0003',
            'first_name' => 'Kevin',
            'middle_name' => 'Kiptoo',
            'last_name' => 'Rono',
            'gender' => 'male',
            'date_of_birth' => '2002-11-21',
            'county' => 'Uasin Gishu',
            'phone' => '+254711345678',
            'national_id' => '281121033',
            'course' => 'DEEE',
            'status' => 'active',
            'session' => 'Term 1, 2026/2027',
            'enrolment_date' => '2026-09-01',
            'enrolment_status' => 'ENROLLED',
            'remarks' => null,
            'sponsored_by' => null,
            'index_no' => null,
            'exam_year' => null,
            'previous_school' => null,
        ],
        [
            'admission_no' => 'ADM-2026-0004',
            'first_name' => 'Mercy',
            'middle_name' => 'Atieno',
            'last_name' => 'Owuor',
            'gender' => 'female',
            'date_of_birth' => '2003-06-17',
            'county' => 'Kisumu',
            'phone' => '+254728456789',
            'national_id' => '290617044',
            'course' => 'DSWCD',
            'status' => 'active',
            'session' => 'Term 1, 2026/2027',
            'enrolment_date' => '2026-09-01',
            'enrolment_status' => 'ENROLLED',
            'remarks' => null,
            'sponsored_by' => null,
            'index_no' => null,
            'exam_year' => null,
            'previous_school' => null,
        ],
        [
            'admission_no' => 'ADM-2026-0005',
            'first_name' => 'Samuel',
            'middle_name' => 'Kiprono',
            'last_name' => 'Kemboi',
            'gender' => 'male',
            'date_of_birth' => '2005-01-30',
            'county' => 'Nakuru',
            'phone' => '+254745567890',
            'national_id' => '310130055',
            'course' => 'CIT',
            'status' => 'active',
            'session' => 'Term 1, 2026/2027',
            'enrolment_date' => '2026-09-01',
            'enrolment_status' => 'ENROLLED',
            'remarks' => null,
            'sponsored_by' => null,
            'index_no' => null,
            'exam_year' => null,
            'previous_school' => null,
        ],
        [
            'admission_no' => 'ADM-2026-0006',
            'first_name' => 'Grace',
            'middle_name' => 'Wambui',
            'last_name' => 'Kamau',
            'gender' => 'female',
            'date_of_birth' => '2004-02-14',
            'county' => 'Machakos',
            'phone' => '+254740678901',
            'national_id' => '300214066',
            'course' => 'CBTH',
            'status' => 'active',
            'session' => 'Term 1, 2026/2027',
            'enrolment_date' => '2026-09-01',
            'enrolment_status' => 'ENROLLED',
            'remarks' => null,
            'sponsored_by' => null,
            'index_no' => null,
            'exam_year' => null,
            'previous_school' => null,
        ],
        [
            'admission_no' => 'ADM-2026-0007',
            'first_name' => 'Daniel',
            'middle_name' => 'Muthomi',
            'last_name' => 'Njiru',
            'gender' => 'male',
            'date_of_birth' => '2003-08-25',
            'county' => 'Meru',
            'phone' => '+254712789012',
            'national_id' => '290825077',
            'course' => 'CEIE',
            'status' => 'inactive',
            'session' => 'Term 1, 2026/2027',
            'enrolment_date' => '2026-09-01',
            'enrolment_status' => 'DROPPED',
            'remarks' => 'Withdrew from the programme for personal reasons.',
            'sponsored_by' => null,
            'index_no' => null,
            'exam_year' => null,
            'previous_school' => null,
        ],
        [
            'admission_no' => 'ADM-2026-0008',
            'first_name' => 'Sharon',
            'middle_name' => 'Anyango',
            'last_name' => 'Juma',
            'gender' => 'female',
            'date_of_birth' => '2005-05-08',
            'county' => 'Kakamega',
            'phone' => '+254758890123',
            'national_id' => '310508088',
            'course' => 'CFDT',
            'status' => 'active',
            'session' => 'Term 1, 2026/2027',
            'enrolment_date' => '2026-09-01',
            'enrolment_status' => 'ENROLLED',
            'remarks' => null,
            'sponsored_by' => null,
            'index_no' => null,
            'exam_year' => null,
            'previous_school' => null,
        ],
        [
            'admission_no' => 'ADM-2026-0009',
            'first_name' => 'Peter',
            'middle_name' => 'Ndung\'u',
            'last_name' => 'Waweru',
            'gender' => 'male',
            'date_of_birth' => '2001-07-19',
            'county' => 'Nairobi',
            'phone' => '+254721901234',
            'national_id' => '270719099',
            'course' => 'AEM',
            'status' => 'active',
            'session' => 'Term 3, 2025/2026',
            'enrolment_date' => '2026-04-27',
            'enrolment_status' => 'COMPLETED',
            'remarks' => 'Completed the artisan programme and sat the trade test.',
            'sponsored_by' => null,
            'index_no' => null,
            'exam_year' => null,
            'previous_school' => null,
        ],
        [
            'admission_no' => 'ADM-2026-0010',
            'first_name' => 'Hawa',
            'middle_name' => 'Abdi',
            'last_name' => 'Mohamed',
            'gender' => 'female',
            'date_of_birth' => '2004-12-02',
            'county' => 'Mombasa',
            'phone' => '+254799012345',
            'national_id' => '301202101',
            'course' => 'DIT',
            'status' => 'active',
            'session' => 'Term 1, 2026/2027',
            'enrolment_date' => '2026-09-01',
            'enrolment_status' => 'ENROLLED',
            'remarks' => null,
            'sponsored_by' => 'Higher Education Loans Board',
            'index_no' => 'HELB/2025/04821',
            'exam_year' => '2025',
            'previous_school' => 'Mago Technical and Vocational College',
        ],
    ];

    /**
     * Extra staff across the three non-student roles; the employment number
     * doubles as the login id, mirroring the staff provisioning flow.
     *
     * @var list<array{employment_no: string, first_name: string, middle_name: string|null, last_name: string, gender: string, date_of_birth: string, county: string, place_of_birth: string, role: string, position: string, department: string|null, work_email: string, work_phone: string, national_id: string, kra_pin: string, nhif_number: string, nssf_number: string, date_joined: string, confirmation_date: string, basic_salary: string, highest_qualification: string, specialization: string}>
     */
    private const STAFF = [
        [
            'employment_no' => 'EMP-2026-001',
            'first_name' => 'John',
            'middle_name' => 'Kamau',
            'last_name' => 'Githinji',
            'gender' => 'male',
            'date_of_birth' => '1986-03-14',
            'county' => 'Kiambu',
            'place_of_birth' => 'Thika',
            'role' => 'trainer',
            'position' => 'Lecturer',
            'department' => 'electrical',
            'work_email' => 'john.githinji@school.test',
            'work_phone' => '+254711223344',
            'national_id' => '28765431',
            'kra_pin' => 'A004567891B',
            'nhif_number' => '1234567',
            'nssf_number' => '5566778',
            'date_joined' => '2019-08-19',
            'confirmation_date' => '2020-02-03',
            'basic_salary' => '145000.00',
            'highest_qualification' => 'M.Sc Electrical Engineering',
            'specialization' => 'Power Systems and Machines',
        ],
        [
            'employment_no' => 'EMP-2026-002',
            'first_name' => 'Lucy',
            'middle_name' => 'Adhiambo',
            'last_name' => 'Otieno',
            'gender' => 'female',
            'date_of_birth' => '1990-07-22',
            'county' => 'Kisumu',
            'place_of_birth' => 'Kisumu',
            'role' => 'admin',
            'position' => 'Registrar',
            'department' => null,
            'work_email' => 'lucy.otieno@school.test',
            'work_phone' => '+254722556677',
            'national_id' => '30072246',
            'kra_pin' => 'B009876543C',
            'nhif_number' => '2345678',
            'nssf_number' => '6677889',
            'date_joined' => '2021-01-11',
            'confirmation_date' => '2021-07-11',
            'basic_salary' => '120000.00',
            'highest_qualification' => 'B.A Public Administration',
            'specialization' => 'Academic Records Management',
        ],
        [
            'employment_no' => 'EMP-2026-003',
            'first_name' => 'Michael',
            'middle_name' => 'Waweru',
            'last_name' => 'Njoroge',
            'gender' => 'male',
            'date_of_birth' => '1988-11-05',
            'county' => 'Nakuru',
            'place_of_birth' => 'Nakuru',
            'role' => 'finance',
            'position' => 'Finance Officer',
            'department' => null,
            'work_email' => 'michael.njoroge@school.test',
            'work_phone' => '+254733889900',
            'national_id' => '28811053',
            'kra_pin' => 'C001234567D',
            'nhif_number' => '3456789',
            'nssf_number' => '7788990',
            'date_joined' => '2022-05-02',
            'confirmation_date' => '2022-11-02',
            'basic_salary' => '110000.00',
            'highest_qualification' => 'B.Com Finance',
            'specialization' => 'Financial Reporting',
        ],
    ];

    /**
     * The admin account the demo rows are attributed to, when present.
     */
    private ?int $adminId = null;

    /**
     * Seed every module that ships empty with a coherent demo graph.
     */
    public function run(): void
    {
        $adminId = User::where('email', 'admin@school.test')->value('id');
        $this->adminId = $adminId === null ? null : (int) $adminId;

        $bodies = $this->seedExamBodies();
        $departments = $this->seedDepartments();
        $levels = $this->seedCertificationLevels($bodies);
        $sessions = $this->seedAcademicCalendar();
        $this->seedTermEvents($sessions);
        $this->seedExamBodyGrades($bodies);
        $curricula = $this->seedCurricula($bodies);
        $courses = $this->seedCourses($departments, $levels);
        $this->seedUnits($courses, $curricula);
        $this->seedStudents($courses, $curricula, $levels, $sessions);
        $this->seedStaff($departments);
    }

    /**
     * Resolve each exam body by name prefix, creating it when missing.
     *
     * @return array<string, ExamBody>
     */
    private function seedExamBodies(): array
    {
        $bodies = [];

        foreach (self::EXAM_BODIES as $definition) {
            $body = ExamBody::where('name', 'like', $definition['prefix'].'%')->first();

            if ($body === null) {
                $body = ExamBody::create([
                    'name' => $definition['name'],
                    'code' => $definition['code'],
                    'website' => $definition['website'],
                    'description' => $definition['description'],
                    'created_by' => $this->adminId,
                    'updated_by' => $this->adminId,
                ]);
            }

            $bodies[$definition['key']] = $body;
        }

        $this->command?->info('Exam bodies: '.count($bodies).' resolved.');

        return $bodies;
    }

    /**
     * Resolve each department by name prefix, creating it when missing.
     *
     * @return array<string, Department>
     */
    private function seedDepartments(): array
    {
        $departments = [];

        foreach (self::DEPARTMENTS as $definition) {
            $department = Department::where('name', 'like', $definition['prefix'].'%')->first();

            if ($department === null) {
                $department = Department::create([
                    'name' => $definition['name'],
                    'is_active' => true,
                ]);
            }

            $departments[$definition['key']] = $department;
        }

        $this->command?->info('Departments: '.count($departments).' resolved.');

        return $departments;
    }

    /**
     * Certification levels per awarding body.
     *
     * @param  array<string, ExamBody>  $bodies
     * @return array<string, CertificationLevel>
     */
    private function seedCertificationLevels(array $bodies): array
    {
        $levels = [];

        foreach (self::CERTIFICATION_LEVELS as $key => $definition) {
            $levels[$key] = CertificationLevel::firstOrCreate(
                [
                    'exam_body_id' => $bodies[$definition['body']]->id,
                    'name' => $definition['name'],
                ],
                [
                    'code' => $definition['code'],
                    'description' => $definition['description'],
                    'sequence' => $definition['sequence'],
                    'is_active' => true,
                    'created_by' => $this->adminId,
                    'updated_by' => $this->adminId,
                ],
            );
        }

        $this->command?->info('Certification levels: '.count($levels).' ensured.');

        return $levels;
    }

    /**
     * Academic years with three terms each, keyed by session name.
     *
     * @return array<string, AcademicSession>
     */
    private function seedAcademicCalendar(): array
    {
        $years = [];

        foreach (self::ACADEMIC_YEARS as $definition) {
            $years[$definition['name']] = AcademicYear::firstOrCreate(
                ['name' => $definition['name']],
                [
                    'start_date' => $definition['start_date'],
                    'end_date' => $definition['end_date'],
                    'description' => $definition['description'],
                    'is_active' => $definition['is_active'],
                    'created_by' => $this->adminId,
                    'updated_by' => $this->adminId,
                ],
            );
        }

        $sessions = [];

        foreach (self::ACADEMIC_SESSIONS as $definition) {
            $sessions[$definition['name']] = AcademicSession::firstOrCreate(
                ['name' => $definition['name']],
                [
                    'academic_year_id' => $years[$definition['year']]->id,
                    'start_date' => $definition['start_date'],
                    'end_date' => $definition['end_date'],
                    'is_active' => $definition['is_active'],
                    'created_by' => $this->adminId,
                    'updated_by' => $this->adminId,
                ],
            );
        }

        $this->command?->info('Academic years: '.count($years).', sessions: '.count($sessions).'.');

        return $sessions;
    }

    /**
     * Calendar events bound to their academic sessions, keyed by session
     * and event name so re-seeding never duplicates them.
     *
     * @param  array<string, AcademicSession>  $sessions
     */
    private function seedTermEvents(array $sessions): void
    {
        foreach (self::TERM_EVENTS as $definition) {
            TermEvent::firstOrCreate(
                [
                    'academic_session_id' => $sessions[$definition['session']]->id,
                    'name' => $definition['name'],
                ],
                [
                    'start_date' => $definition['start_date'],
                    'end_date' => $definition['end_date'],
                    'is_active' => $definition['is_active'],
                    'created_by' => $this->adminId,
                    'updated_by' => $this->adminId,
                ],
            );
        }

        $this->command?->info('Term events: '.count(self::TERM_EVENTS).' resolved.');
    }

    /**
     * The grading scales of every exam body that carries one.
     *
     * @param  array<string, ExamBody>  $bodies
     */
    private function seedExamBodyGrades(array $bodies): void
    {
        $count = 0;

        foreach (self::EXAM_BODY_GRADES as $bodyKey => $grades) {
            foreach ($grades as $definition) {
                ExamBodyGrade::firstOrCreate(
                    [
                        'exam_body_id' => $bodies[$bodyKey]->id,
                        'grade' => $definition['grade'],
                    ],
                    [
                        'grade_start' => $definition['start'],
                        'grade_end' => $definition['end'],
                        'remark' => $definition['remark'],
                        'sequence' => $definition['sequence'],
                        'is_active' => true,
                        'created_by' => $this->adminId,
                        'updated_by' => $this->adminId,
                    ],
                );

                $count++;
            }
        }

        $this->command?->info('Exam body grades: '.$count.' ensured.');
    }

    /**
     * Curricula keyed for the courses and units to reference.
     *
     * @param  array<string, ExamBody>  $bodies
     * @return array<string, Curriculum>
     */
    private function seedCurricula(array $bodies): array
    {
        $curricula = [];

        foreach (self::CURRICULA as $key => $definition) {
            $curricula[$key] = Curriculum::firstOrCreate(
                [
                    'exam_body_id' => $bodies[$definition['body']]->id,
                    'name' => $definition['name'],
                ],
                [
                    'is_active' => true,
                    'created_by' => $this->adminId,
                    'updated_by' => $this->adminId,
                ],
            );
        }

        $this->command?->info('Curricula: '.count($curricula).' ensured.');

        return $curricula;
    }

    /**
     * Programmes keyed by course code.
     *
     * @param  array<string, Department>  $departments
     * @param  array<string, CertificationLevel>  $levels
     * @return array<string, Course>
     */
    private function seedCourses(array $departments, array $levels): array
    {
        $courses = [];

        foreach (self::COURSES as $code => $definition) {
            $courses[$code] = Course::firstOrCreate(
                ['code' => $code],
                [
                    'initials' => $definition['initials'],
                    'name' => $definition['name'],
                    'duration_months' => $definition['duration_months'],
                    'description' => $definition['description'],
                    'is_active' => true,
                    'department_id' => isset($departments[$definition['department']])
                        ? $departments[$definition['department']]->id
                        : null,
                    'certification_level_id' => isset($levels[$definition['level']])
                        ? $levels[$definition['level']]->id
                        : null,
                    'created_by' => $this->adminId,
                    'updated_by' => $this->adminId,
                ],
            );
        }

        $this->command?->info('Courses: '.count($courses).' ensured.');

        return $courses;
    }

    /**
     * Five units per programme: four core and one elective.
     *
     * @param  array<string, Course>  $courses
     * @param  array<string, Curriculum>  $curricula
     */
    private function seedUnits(array $courses, array $curricula): void
    {
        $count = 0;

        foreach (self::UNITS as $courseCode => $units) {
            if (! isset($courses[$courseCode])) {
                continue;
            }

            $course = $courses[$courseCode];
            $curriculum = $curricula[self::COURSES[$courseCode]['curriculum']];

            foreach ($units as $definition) {
                $core = $definition['core'];

                Unit::firstOrCreate(
                    [
                        'curriculum_id' => $curriculum->id,
                        'course_id' => $course->id,
                        'code' => $courseCode.' '.$definition['suffix'],
                    ],
                    [
                        'name' => $definition['name'],
                        'description' => ($core ? 'Core' : 'Elective').' unit of the '.$course->initials.' programme.',
                        'modules_taught' => $core ? 3 : 2,
                        'taught_hours' => $core ? 60 : 40,
                        'credit_factor' => $core ? 2.00 : 1.00,
                        'core' => $core,
                        'is_active' => true,
                        'created_by' => $this->adminId,
                        'updated_by' => $this->adminId,
                    ],
                );

                $count++;
            }
        }

        $this->command?->info('Units: '.$count.' ensured.');
    }

    /**
     * Students with accounts, profiles and one enrolment each.
     *
     * @param  array<string, Course>  $courses
     * @param  array<string, Curriculum>  $curricula
     * @param  array<string, CertificationLevel>  $levels
     * @param  array<string, AcademicSession>  $sessions
     */
    private function seedStudents(array $courses, array $curricula, array $levels, array $sessions): void
    {
        foreach (self::STUDENTS as $definition) {
            $user = User::firstOrCreate(
                ['login_id' => $definition['admission_no']],
                [
                    'name' => User::composeName($definition['first_name'], $definition['middle_name'], $definition['last_name']),
                    'email' => Str::lower($definition['admission_no']).'@student.invalid',
                    'password' => Hash::make(self::DEMO_PASSWORD),
                    'role' => Role::Student,
                    'first_name' => $definition['first_name'],
                    'middle_name' => $definition['middle_name'],
                    'last_name' => $definition['last_name'],
                    'gender' => $definition['gender'],
                    'date_of_birth' => $definition['date_of_birth'],
                    'county' => $definition['county'],
                    'status' => 'active',
                    'must_reset_password' => false,
                    'created_by' => $this->adminId,
                    'updated_by' => $this->adminId,
                ],
            );

            $profile = StudentProfile::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'national_id' => $definition['national_id'],
                    'status' => $definition['status'],
                    'first_name' => $definition['first_name'],
                    'middle_name' => $definition['middle_name'],
                    'last_name' => $definition['last_name'],
                    'phone' => $definition['phone'],
                    'gender' => $definition['gender'],
                    'date_of_birth' => $definition['date_of_birth'],
                    'county' => $definition['county'],
                    'sponsored' => $definition['sponsored_by'] !== null,
                    'sponsored_by' => $definition['sponsored_by'],
                    'index_no' => $definition['index_no'],
                    'exam_year' => $definition['exam_year'],
                    'previous_school' => $definition['previous_school'],
                    'created_by' => $this->adminId,
                    'updated_by' => $this->adminId,
                ],
            );

            $courseDefinition = self::COURSES[$definition['course']];

            CourseEnrolment::firstOrCreate(
                [
                    'student_id' => $profile->id,
                    'course_id' => $courses[$definition['course']]->id,
                    'academic_session_id' => $sessions[$definition['session']]->id,
                ],
                [
                    'curriculum_id' => $curricula[$courseDefinition['curriculum']]->id,
                    'certification_level_id' => $levels[$courseDefinition['level']]->id,
                    'enrolment_date' => $definition['enrolment_date'],
                    'status' => $definition['enrolment_status'],
                    'remarks' => $definition['remarks'],
                    'created_by' => $this->adminId,
                    'updated_by' => $this->adminId,
                ],
            );
        }

        $this->command?->info('Students: '.count(self::STUDENTS).' with accounts and enrolments.');
    }

    /**
     * Staff accounts and employment rows across admin, trainer and finance.
     *
     * @param  array<string, Department>  $departments
     */
    private function seedStaff(array $departments): void
    {
        foreach (self::STAFF as $definition) {
            $name = User::composeName($definition['first_name'], $definition['middle_name'], $definition['last_name']);

            $user = User::firstOrCreate(
                ['login_id' => $definition['employment_no']],
                [
                    'name' => $name,
                    'email' => $definition['work_email'],
                    'password' => Hash::make(self::DEMO_PASSWORD),
                    'role' => $definition['role'],
                    'first_name' => $definition['first_name'],
                    'middle_name' => $definition['middle_name'],
                    'last_name' => $definition['last_name'],
                    'gender' => $definition['gender'],
                    'date_of_birth' => $definition['date_of_birth'],
                    'county' => $definition['county'],
                    'nationality' => 'Kenyan',
                    'place_of_birth' => $definition['place_of_birth'],
                    'status' => 'active',
                    'must_reset_password' => false,
                    'created_by' => $this->adminId,
                    'updated_by' => $this->adminId,
                ],
            );

            Staff::firstOrCreate(
                ['employment_no' => $definition['employment_no']],
                [
                    'user_id' => $user->id,
                    'name' => $name,
                    'position' => $definition['position'],
                    'department_id' => isset($departments[$definition['department']])
                        ? $departments[$definition['department']]->id
                        : null,
                    'role' => $definition['role'],
                    'work_email' => $definition['work_email'],
                    'work_phone' => $definition['work_phone'],
                    'national_id' => $definition['national_id'],
                    'kra_pin' => $definition['kra_pin'],
                    'nhif_number' => $definition['nhif_number'],
                    'nssf_number' => $definition['nssf_number'],
                    'employment_type' => 'full_time',
                    'employment_status' => 'active',
                    'date_joined' => $definition['date_joined'],
                    'confirmation_date' => $definition['confirmation_date'],
                    'basic_salary' => $definition['basic_salary'],
                    'highest_qualification' => $definition['highest_qualification'],
                    'specialization' => $definition['specialization'],
                    'created_by' => $this->adminId,
                    'updated_by' => $this->adminId,
                ],
            );
        }

        $this->command?->info('Staff: '.count(self::STAFF).' with accounts.');
    }
}
