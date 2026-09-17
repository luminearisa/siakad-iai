<?php

namespace App\Support\Traits;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait ScopesToOwnLecturer
{
    /**
     * Decide whether the caller must be restricted to their own lecturer data.
     *
     * A user holding the `dosen` role without the module's management permission
     * is a plain lecturer: they must only ever see their own teaching data
     * (their classes, their schedule, the students they advise, ...). Staff roles
     * (super admin, admin akademik, kaprodi) keep full visibility.
     *
     * Use this when the relation shape is custom (e.g. a nested belongsTo) and the
     * `scopeToOwnLecturer()` helper below does not fit; apply the returned id yourself.
     *
     * @return array{0: bool, 1: int|null} Tuple of [isLecturerOnly, ownLecturerId].
     *                                     When isLecturerOnly is true and ownLecturerId
     *                                     is null, the caller owns no data at all.
     */
    protected function resolveOwnLecturerScope(Request $request, string $managePermission): array
    {
        $user = $request->user();

        $isLecturerOnly = $user
            && $user->hasRole('dosen')
            && !$user->hasPermissionTo($managePermission);

        return [$isLecturerOnly, $isLecturerOnly ? $user->lecturer?->id : null];
    }

    /**
     * Restrict a listing query to the data owned by the authenticated lecturer.
     *
     * Two shapes are supported:
     *  - a many-to-many / nested path, e.g. 'lecturers' or 'academicClass.lecturers'
     *    (matched through the pivot via whereHas on `lecturers.id`);
     *  - a direct foreign key on the queried model, e.g. `$column = 'lecturer_id'`
     *    (matched with a plain where, which is what belongsTo relations need).
     *
     * @param  Builder  $query  Query whose model reaches lecturers through $relation (or owns $column).
     * @param  string  $managePermission  Permission that marks the user as staff for this module.
     * @param  string|null  $relation  Relation path to the lecturers relation, e.g. 'lecturers' or 'academicClass.lecturers'.
     * @param  string|null  $column  Direct foreign key column on the queried model, e.g. 'lecturer_id'. Takes precedence over $relation.
     * @return bool  True when the query was scoped to the caller's own lecturer id.
     */
    protected function scopeToOwnLecturer(
        Request $request,
        Builder $query,
        string $managePermission,
        ?string $relation = 'lecturers',
        ?string $column = null
    ): bool {
        [$isLecturerOnly, $ownLecturerId] = $this->resolveOwnLecturerScope($request, $managePermission);

        if (!$isLecturerOnly) {
            return false;
        }

        if (!$ownLecturerId) {
            // A lecturer account without a lecturer profile owns no data at all.
            $query->whereRaw('1 = 0');

            return true;
        }

        if ($column) {
            $query->where($column, $ownLecturerId);
        } else {
            $query->whereHas($relation, function ($q) use ($ownLecturerId) {
                $q->where('lecturers.id', $ownLecturerId);
            });
        }

        return true;
    }

    /**
     * Check whether the caller may read/modify a single record owned by a lecturer.
     *
     * Mirrors `resolveOwnLecturerScope()` for detail endpoints: a plain lecturer may
     * only touch records whose owner lecturer id is their own. Staff keep full access.
     */
    protected function lecturerMayAccessOwnedRecord(
        Request $request,
        ?int $ownerLecturerId,
        string $managePermission
    ): bool {
        [$isLecturerOnly, $ownLecturerId] = $this->resolveOwnLecturerScope($request, $managePermission);

        if (!$isLecturerOnly) {
            return true;
        }

        return $ownLecturerId !== null && $ownerLecturerId === $ownLecturerId;
    }
}
