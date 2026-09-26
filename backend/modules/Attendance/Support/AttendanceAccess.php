<?php

namespace Modules\Attendance\Support;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Modules\Attendance\Models\TeachingSession;
use Modules\Class\Models\AcademicClass;
use Modules\Enrollment\Enums\EnrollmentItemStatus;
use Modules\Enrollment\Enums\EnrollmentStatus;
use Modules\Enrollment\Models\StudentEnrollment;
use Modules\Enrollment\Models\StudentEnrollmentItem;
use Modules\Identity\Models\User;
use Modules\Student\Models\Student;

/**
 * Otorisasi presensi.
 *
 * Permission saja tidak cukup sebagai penjaga: role `dosen` memegang
 * `attendance.manage`/`attendance.record` secara global, sehingga middleware
 * `permission:` hanya membuktikan "ini staf/pengajar", bukan "ini kelasnya".
 * Semua keputusan kepemilikan diambil di sini dan gagal-tertutup (default deny).
 */
class AttendanceAccess
{
    /**
     * Penanda staf. `attendance.manage` tidak bisa dipakai sebagai penanda karena
     * role dosen juga memegangnya; `enrollments.lock` hanya dipegang staf akademik.
     */
    public const STAFF_PERMISSION = 'enrollments.lock';

    public static function isStaff(?User $user): bool
    {
        return $user !== null && $user->hasPermissionTo(self::STAFF_PERMISSION);
    }

    public static function mayRecord(?User $user): bool
    {
        return $user !== null
            && ($user->hasPermissionTo('attendance.record') || $user->hasPermissionTo('attendance.manage'));
    }

    /**
     * Profil mahasiswa milik akun login. Tidak ada pencocokan email dan tidak ada
     * fallback "mahasiswa pertama": identitas harus eksplisit lewat user_id.
     */
    public static function currentStudent(?User $user): ?Student
    {
        if (!$user) {
            return null;
        }

        return $user->student ?? Student::where('user_id', $user->id)->first();
    }

    public static function ownLecturerId(?User $user): ?int
    {
        return $user?->lecturer?->id;
    }

    /**
     * Pengampu kelas = dosen yang tercatat pada sesi ATAU pada pivot class_lecturers.
     */
    public static function teachesClass(?int $lecturerId, AcademicClass|int $class): bool
    {
        if (!$lecturerId) {
            return false;
        }

        $classId = $class instanceof AcademicClass ? $class->id : $class;

        return AcademicClass::whereKey($classId)
            ->whereHas('lecturers', fn ($q) => $q->where('lecturers.id', $lecturerId))
            ->exists();
    }

    public static function mayManageSession(?User $user, TeachingSession $session): bool
    {
        if (!$user || !self::mayRecord($user)) {
            return false;
        }

        if (self::isStaff($user)) {
            return true;
        }

        $lecturerId = self::ownLecturerId($user);

        if (!$lecturerId) {
            return false;
        }

        return $session->lecturer_id === $lecturerId
            || self::teachesClass($lecturerId, $session->academic_class_id);
    }

    /**
     * Staf boleh menghapus/membuat sesi; dosen pengampu boleh mengelola sesi miliknya
     * tetapi tidak boleh menghapus master sesi hasil generasi.
     */
    public static function mayManageSessionsGlobally(?User $user): bool
    {
        return self::isStaff($user) && self::mayRecord($user);
    }

    public static function maySeeSession(?User $user, TeachingSession $session): bool
    {
        if (self::mayManageSession($user, $session)) {
            return true;
        }

        $student = self::currentStudent($user);

        return $student !== null && self::isOnRoster($session->academic_class_id, $student->id);
    }

    public static function maySeeClass(?User $user, AcademicClass $class): bool
    {
        if (self::isStaff($user)) {
            return true;
        }

        if (self::mayRecord($user) && self::teachesClass(self::ownLecturerId($user), $class)) {
            return true;
        }

        $student = self::currentStudent($user);

        return $student !== null && self::isOnRoster($class->id, $student->id);
    }

    public static function maySeeStudent(?User $user, Student $student): bool
    {
        if (self::isStaff($user)) {
            return true;
        }

        $viewer = self::currentStudent($user);
        if ($viewer && $viewer->id === $student->id) {
            return true;
        }

        if (!self::mayRecord($user)) {
            return false;
        }

        $lecturerId = self::ownLecturerId($user);
        if (!$lecturerId) {
            return false;
        }

        if ((int) $student->academicAdvisor?->lecturer_id === (int) $lecturerId) {
            return true;
        }

        $classIds = self::classIdsOfStudent($student->id);
        if (!$classIds) {
            return false;
        }

        return AcademicClass::whereIn('id', $classIds)
            ->whereHas('lecturers', fn ($q) => $q->where('lecturers.id', $lecturerId))
            ->exists();
    }

    /**
     * Persempit query listing sesi sesuai peran pemanggil.
     */
    public static function scopeSessionsFor(?User $user, Builder $query): Builder
    {
        if (self::isStaff($user)) {
            return $query;
        }

        $lecturerId = self::ownLecturerId($user);
        if ($lecturerId && self::mayRecord($user)) {
            return $query->where(function ($q) use ($lecturerId) {
                $q->where('lecturer_id', $lecturerId)
                    ->orWhereHas('academicClass.lecturers', fn ($lq) => $lq->where('lecturers.id', $lecturerId));
            });
        }

        $student = self::currentStudent($user);
        if ($student) {
            $classIds = self::classIdsOfStudent($student->id);
            $query->whereIn('academic_class_id', $classIds ?: [0]);

            return $query;
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * Item KRS aktif: status `enrolled` pada KRS yang sudah approved/locked.
     * Item dropped/cancelled bukan roster — mahasiswa yang membatalkan kelas tidak
     * boleh lagi muncul (atau menulis presensi) di kelas itu.
     */
    protected static function activeItemsQuery(int $classId, ?int $studentId = null): Builder
    {
        return StudentEnrollmentItem::query()
            ->where('class_id', $classId)
            ->where('status', EnrollmentItemStatus::ENROLLED->value)
            ->whereHas('enrollment', function ($q) use ($studentId) {
                $q->whereIn('status', [EnrollmentStatus::APPROVED->value, EnrollmentStatus::LOCKED->value]);
                if ($studentId !== null) {
                    $q->where('student_id', $studentId);
                }
            });
    }

    public static function isOnRoster(int $classId, int $studentId): bool
    {
        return self::activeItemsQuery($classId, $studentId)->exists();
    }

    /**
     * @return array<int, int>
     */
    public static function rosterStudentIds(int $classId): array
    {
        $enrollmentIds = self::activeItemsQuery($classId)->pluck('enrollment_id')->all();

        if (!$enrollmentIds) {
            return [];
        }

        return StudentEnrollment::whereIn('id', $enrollmentIds)
            ->pluck('student_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    public static function classIdsOfStudent(int $studentId): array
    {
        return StudentEnrollmentItem::query()
            ->where('status', EnrollmentItemStatus::ENROLLED->value)
            ->whereHas('enrollment', function ($q) use ($studentId) {
                $q->where('student_id', $studentId)
                    ->whereIn('status', [EnrollmentStatus::APPROVED->value, EnrollmentStatus::LOCKED->value]);
            })
            ->pluck('class_id')
            ->unique()
            ->values()
            ->all();
    }
}
