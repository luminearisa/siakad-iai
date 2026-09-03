<?php

namespace Modules\Identity\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;

class IdentitySeeder extends Seeder
{
    public function run(): void
    {
        // 1. Define permissions list
        $permissions = [
            // Student permissions
            ['name' => 'students.view', 'display_name' => 'View Students', 'group' => 'student', 'description' => 'View student data'],
            ['name' => 'students.create', 'display_name' => 'Create Student', 'group' => 'student', 'description' => 'Create new student record'],
            ['name' => 'students.update', 'display_name' => 'Update Student', 'group' => 'student', 'description' => 'Update student data'],
            ['name' => 'students.delete', 'display_name' => 'Delete Student', 'group' => 'student', 'description' => 'Delete student record'],
            ['name' => 'students.change_status', 'display_name' => 'Change Student Status', 'group' => 'student', 'description' => 'Change student enrollment status'],

            // Lecturer permissions
            ['name' => 'lecturers.view', 'display_name' => 'View Lecturers', 'group' => 'lecturer', 'description' => 'View lecturer data'],
            ['name' => 'lecturers.create', 'display_name' => 'Create Lecturer', 'group' => 'lecturer', 'description' => 'Create new lecturer record'],
            ['name' => 'lecturers.update', 'display_name' => 'Update Lecturer', 'group' => 'lecturer', 'description' => 'Update lecturer data'],
            ['name' => 'lecturers.delete', 'display_name' => 'Delete Lecturer', 'group' => 'lecturer', 'description' => 'Delete lecturer record'],
            ['name' => 'lecturers.change_status', 'display_name' => 'Change Lecturer Status', 'group' => 'lecturer', 'description' => 'Change lecturer employment status'],

            // Course permissions
            ['name' => 'courses.view', 'display_name' => 'View Courses', 'group' => 'course', 'description' => 'View course catalog'],
            ['name' => 'courses.create', 'display_name' => 'Create Course', 'group' => 'course', 'description' => 'Create new course'],
            ['name' => 'courses.update', 'display_name' => 'Update Course', 'group' => 'course', 'description' => 'Update course details'],
            ['name' => 'courses.delete', 'display_name' => 'Delete Course', 'group' => 'course', 'description' => 'Delete course'],

            // Curriculum permissions
            ['name' => 'curricula.view', 'display_name' => 'View Curricula', 'group' => 'curriculum', 'description' => 'View curriculum list'],
            ['name' => 'curricula.create', 'display_name' => 'Create Curriculum', 'group' => 'curriculum', 'description' => 'Create new curriculum'],
            ['name' => 'curricula.update', 'display_name' => 'Update Curriculum', 'group' => 'curriculum', 'description' => 'Update curriculum details'],
            ['name' => 'curricula.delete', 'display_name' => 'Delete Curriculum', 'group' => 'curriculum', 'description' => 'Delete curriculum'],
            ['name' => 'curricula.activate', 'display_name' => 'Activate Curriculum', 'group' => 'curriculum', 'description' => 'Activate curriculum'],
            ['name' => 'curricula.archive', 'display_name' => 'Archive Curriculum', 'group' => 'curriculum', 'description' => 'Archive curriculum'],
            ['name' => 'curricula.manage_subjects', 'display_name' => 'Manage Curriculum Subjects', 'group' => 'curriculum', 'description' => 'Add/remove subjects in curriculum'],

            // Class permissions
            ['name' => 'classes.view', 'display_name' => 'View Classes', 'group' => 'class', 'description' => 'View academic classes'],
            ['name' => 'classes.create', 'display_name' => 'Create Class', 'group' => 'class', 'description' => 'Create academic class'],
            ['name' => 'classes.update', 'display_name' => 'Update Class', 'group' => 'class', 'description' => 'Update class details'],
            ['name' => 'classes.delete', 'display_name' => 'Delete Class', 'group' => 'class', 'description' => 'Delete class'],
            ['name' => 'classes.open', 'display_name' => 'Open Class', 'group' => 'class', 'description' => 'Open class for enrollment'],
            ['name' => 'classes.close', 'display_name' => 'Close Class', 'group' => 'class', 'description' => 'Close class for enrollment'],
            ['name' => 'classes.cancel', 'display_name' => 'Cancel Class', 'group' => 'class', 'description' => 'Cancel class'],
            ['name' => 'classes.assign_lecturer', 'display_name' => 'Assign Class Lecturer', 'group' => 'class', 'description' => 'Assign lecturer to class'],

            // Room permissions
            ['name' => 'rooms.view', 'display_name' => 'View Rooms', 'group' => 'schedule', 'description' => 'View room list'],
            ['name' => 'rooms.create', 'display_name' => 'Create Room', 'group' => 'schedule', 'description' => 'Create new room'],
            ['name' => 'rooms.update', 'display_name' => 'Update Room', 'group' => 'schedule', 'description' => 'Update room details'],
            ['name' => 'rooms.delete', 'display_name' => 'Delete Room', 'group' => 'schedule', 'description' => 'Delete room'],
            ['name' => 'rooms.change_status', 'display_name' => 'Change Room Status', 'group' => 'schedule', 'description' => 'Change room operational status'],

            // Schedule permissions
            ['name' => 'schedules.view', 'display_name' => 'View Schedules', 'group' => 'schedule', 'description' => 'View class schedules'],
            ['name' => 'schedules.create', 'display_name' => 'Create Schedule', 'group' => 'schedule', 'description' => 'Create schedule for class'],
            ['name' => 'schedules.update', 'display_name' => 'Update Schedule', 'group' => 'schedule', 'description' => 'Update schedule'],
            ['name' => 'schedules.delete', 'display_name' => 'Delete Schedule', 'group' => 'schedule', 'description' => 'Delete schedule'],

            // Enrollment / KRS permissions
            ['name' => 'enrollments.view', 'display_name' => 'View Enrollments', 'group' => 'enrollment', 'description' => 'View student KRS enrollments'],
            ['name' => 'enrollments.create', 'display_name' => 'Create Enrollment', 'group' => 'enrollment', 'description' => 'Create student KRS'],
            ['name' => 'enrollments.update', 'display_name' => 'Update Enrollment', 'group' => 'enrollment', 'description' => 'Add/remove classes in KRS'],
            ['name' => 'enrollments.submit', 'display_name' => 'Submit Enrollment', 'group' => 'enrollment', 'description' => 'Submit KRS for approval'],
            ['name' => 'enrollments.approve', 'display_name' => 'Approve Enrollment', 'group' => 'enrollment', 'description' => 'Approve submitted KRS'],
            ['name' => 'enrollments.reject', 'display_name' => 'Reject Enrollment', 'group' => 'enrollment', 'description' => 'Reject submitted KRS'],
            ['name' => 'enrollments.revise', 'display_name' => 'Request Enrollment Revision', 'group' => 'enrollment', 'description' => 'Request student to revise KRS'],
            ['name' => 'enrollments.lock', 'display_name' => 'Lock Enrollment', 'group' => 'enrollment', 'description' => 'Lock finalized KRS'],

            // Academic Advising permissions
            ['name' => 'advising.view', 'display_name' => 'View Advising Data', 'group' => 'advising', 'description' => 'View academic advisor assignments and sessions'],
            ['name' => 'advising.assign', 'display_name' => 'Assign Academic Advisor', 'group' => 'advising', 'description' => 'Assign or change academic advisor'],
            ['name' => 'advising.update', 'display_name' => 'Update Advising Data', 'group' => 'advising', 'description' => 'Update advisor data'],
            ['name' => 'advising.create_session', 'display_name' => 'Create Advising Session', 'group' => 'advising', 'description' => 'Log academic consultation session'],
            ['name' => 'advising.update_session', 'display_name' => 'Update Advising Session', 'group' => 'advising', 'description' => 'Update consultation session notes'],

            // Attendance permissions
            ['name' => 'attendance.view', 'display_name' => 'View Attendance', 'group' => 'attendance', 'description' => 'View attendance records and class meetings'],
            ['name' => 'attendance.manage', 'display_name' => 'Manage Attendance', 'group' => 'attendance', 'description' => 'Create, update, and close class meetings & attendances'],
            ['name' => 'attendance.record', 'display_name' => 'Record Attendance', 'group' => 'attendance', 'description' => 'Record and update student attendance status'],
            ['name' => 'attendance.self_checkin', 'display_name' => 'Self Check-in Attendance', 'group' => 'attendance', 'description' => 'Submit self check-in token as student'],

            // Assessment & Grading permissions (Phase 13)
            ['name' => 'assessment.view', 'display_name' => 'View Assessment', 'group' => 'assessment', 'description' => 'View assessment schemes and components'],
            ['name' => 'assessment.create', 'display_name' => 'Create Assessment', 'group' => 'assessment', 'description' => 'Create assessment scheme and components'],
            ['name' => 'assessment.update', 'display_name' => 'Update Assessment', 'group' => 'assessment', 'description' => 'Update assessment scheme and components'],
            ['name' => 'assessment.delete', 'display_name' => 'Delete Assessment', 'group' => 'assessment', 'description' => 'Delete assessment scheme and components'],
            ['name' => 'assessment.scheme.manage', 'display_name' => 'Manage Assessment Scheme', 'group' => 'assessment', 'description' => 'Activate and archive assessment schemes'],

            ['name' => 'grade.view', 'display_name' => 'View Grades', 'group' => 'grade', 'description' => 'View student grades and class recap'],
            ['name' => 'grade.input', 'display_name' => 'Input Grades', 'group' => 'grade', 'description' => 'Input and draft student grades'],
            ['name' => 'grade.update', 'display_name' => 'Update Grades', 'group' => 'grade', 'description' => 'Update student draft grades'],
            ['name' => 'grade.submit', 'display_name' => 'Submit Grades', 'group' => 'grade', 'description' => 'Submit class grades for review'],
            ['name' => 'grade.finalize', 'display_name' => 'Finalize Grades', 'group' => 'grade', 'description' => 'Finalize and lock class grades'],
            ['name' => 'grade.revise', 'display_name' => 'Revise Final Grade', 'group' => 'grade', 'description' => 'Perform formal grade revision with audit'],
            ['name' => 'grade.history', 'display_name' => 'View Grade History', 'group' => 'grade', 'description' => 'View grade revision history and logs'],

            // Legacy grade permissions
            ['name' => 'grades.view', 'display_name' => 'View Grades (Legacy)', 'group' => 'grade', 'description' => 'View academic grades'],
            ['name' => 'grades.create', 'display_name' => 'Input Grade (Legacy)', 'group' => 'grade', 'description' => 'Input student grade'],
            ['name' => 'grades.update', 'display_name' => 'Update Grade (Legacy)', 'group' => 'grade', 'description' => 'Update student grade'],

            // Academic Structure permissions
            ['name' => 'institutions.view', 'display_name' => 'View Institution', 'group' => 'academic', 'description' => 'View institution info'],
            ['name' => 'institutions.manage', 'display_name' => 'Manage Institution', 'group' => 'academic', 'description' => 'Manage institution settings'],
            ['name' => 'faculties.view', 'display_name' => 'View Faculties', 'group' => 'academic', 'description' => 'View faculties'],
            ['name' => 'faculties.manage', 'display_name' => 'Manage Faculties', 'group' => 'academic', 'description' => 'Create, update, delete faculties'],
            ['name' => 'study_programs.view', 'display_name' => 'View Study Programs', 'group' => 'academic', 'description' => 'View study programs'],
            ['name' => 'study_programs.manage', 'display_name' => 'Manage Study Programs', 'group' => 'academic', 'description' => 'Create, update, delete study programs'],
            ['name' => 'academic_years.view', 'display_name' => 'View Academic Years', 'group' => 'academic', 'description' => 'View academic years'],
            ['name' => 'academic_years.manage', 'display_name' => 'Manage Academic Years', 'group' => 'academic', 'description' => 'Manage academic years'],
            ['name' => 'semesters.view', 'display_name' => 'View Semesters', 'group' => 'academic', 'description' => 'View semesters'],
            ['name' => 'semesters.manage', 'display_name' => 'Manage Semesters', 'group' => 'academic', 'description' => 'Manage semesters'],

            // Settings permissions
            ['name' => 'settings.view', 'display_name' => 'View Settings', 'group' => 'settings', 'description' => 'View application settings'],
            ['name' => 'settings.manage', 'display_name' => 'Manage Settings', 'group' => 'settings', 'description' => 'Update application settings'],

            // Audit permissions
            ['name' => 'audit.view', 'display_name' => 'View Audit Logs', 'group' => 'audit', 'description' => 'View system audit logs'],

            // Identity permissions
            ['name' => 'users.view', 'display_name' => 'View Users', 'group' => 'identity', 'description' => 'View user list'],
            ['name' => 'users.manage', 'display_name' => 'Manage Users', 'group' => 'identity', 'description' => 'Create, update, delete users'],
            ['name' => 'roles.view', 'display_name' => 'View Roles', 'group' => 'identity', 'description' => 'View roles'],
            ['name' => 'roles.manage', 'display_name' => 'Manage Roles', 'group' => 'identity', 'description' => 'Create, update, delete roles'],
            ['name' => 'permissions.view', 'display_name' => 'View Permissions', 'group' => 'identity', 'description' => 'View permissions'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm['name']], $perm);
        }

        // 2. Create Roles
        $superAdminRole = Role::firstOrCreate(
            ['name' => 'super_admin'],
            [
                'display_name' => 'Super Administrator',
                'description' => 'Full system access and administrative privileges.',
                'is_system' => true,
            ]
        );

        $adminAkademikRole = Role::firstOrCreate(
            ['name' => 'admin_akademik'],
            [
                'display_name' => 'Administrator Akademik',
                'description' => 'Academic administration and curriculum management.',
                'is_system' => true,
            ]
        );

        $dosenRole = Role::firstOrCreate(
            ['name' => 'dosen'],
            [
                'display_name' => 'Dosen Pengajar',
                'description' => 'Faculty member, course instructor, and academic advisor.',
                'is_system' => true,
            ]
        );

        $mahasiswaRole = Role::firstOrCreate(
            ['name' => 'mahasiswa'],
            [
                'display_name' => 'Mahasiswa',
                'description' => 'Enrolled student.',
                'is_system' => true,
            ]
        );

        // 3. Assign permissions to roles
        $allPermissions = Permission::all();
        $superAdminRole->permissions()->sync($allPermissions->pluck('id'));

        $adminAkademikPermissions = Permission::whereIn('group', [
            'academic',
            'student',
            'lecturer',
            'course',
            'curriculum',
            'class',
            'schedule',
            'enrollment',
            'advising',
            'attendance',
            'assessment',
            'grade',
        ])->get();
        $adminAkademikRole->permissions()->sync($adminAkademikPermissions->pluck('id'));

        $dosenPermissions = Permission::whereIn('name', [
            'courses.view',
            'curricula.view',
            'classes.view',
            'schedules.view',
            'rooms.view',
            'students.view',
            'lecturers.view',
            'faculties.view',
            'study_programs.view',
            'semesters.view',
            'academic_years.view',
            'enrollments.view',
            'enrollments.approve',
            'enrollments.reject',
            'enrollments.revise',
            'advising.view',
            'advising.create_session',
            'advising.update_session',
            'attendance.view',
            'attendance.manage',
            'attendance.record',
            'assessment.view',
            'assessment.create',
            'assessment.update',
            'assessment.scheme.manage',
            'grade.view',
            'grade.input',
            'grade.update',
            'grade.submit',
            'grade.history',
            'grades.view',
            'grades.create',
            'grades.update',
        ])->get();
        $dosenRole->permissions()->sync($dosenPermissions->pluck('id'));

        $mahasiswaPermissions = Permission::whereIn('name', [
            'courses.view',
            'curricula.view',
            'classes.view',
            'schedules.view',
            'rooms.view',
            'lecturers.view',
            'faculties.view',
            'study_programs.view',
            'semesters.view',
            'academic_years.view',
            'enrollments.view',
            'enrollments.create',
            'enrollments.update',
            'enrollments.submit',
            'advising.view',
            'attendance.view',
            'attendance.self_checkin',
            'assessment.view',
            'grade.view',
            'grades.view',
        ])->get();
        $mahasiswaRole->permissions()->sync($mahasiswaPermissions->pluck('id'));

        // 4. Create default foundation users
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@siakad.ac.id'],
            [
                'name' => 'Super Administrator',
                'password' => Hash::make('password123'),
                'status' => UserStatus::ACTIVE,
            ]
        );
        $superAdmin->assignRole('super_admin');

        $adminAkademik = User::firstOrCreate(
            ['email' => 'akademik@siakad.ac.id'],
            [
                'name' => 'Admin Akademik',
                'password' => Hash::make('password123'),
                'status' => UserStatus::ACTIVE,
            ]
        );
        $adminAkademik->assignRole('admin_akademik');

        $dosen = User::firstOrCreate(
            ['email' => 'dosen@siakad.ac.id'],
            [
                'name' => 'Dr. Ahmad Dosen, M.Kom',
                'password' => Hash::make('password123'),
                'status' => UserStatus::ACTIVE,
            ]
        );
        $dosen->assignRole('dosen');

        $mahasiswa = User::firstOrCreate(
            ['email' => 'mahasiswa@siakad.ac.id'],
            [
                'name' => 'Budi Santoso',
                'password' => Hash::make('password123'),
                'status' => UserStatus::ACTIVE,
            ]
        );
        $mahasiswa->assignRole('mahasiswa');
    }
}
