<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class QueryFilter
{
    /**
     * Apply common search, filter, sort, and pagination to an Eloquent query.
     *
     * @param Builder $query
     * @param Request $request
     * @param array<string> $searchableColumns
     * @param array<string> $filterableColumns
     * @param string $defaultSort
     * @param string $defaultDirection
     * @param int $defaultPerPage
     * @param int $maxPerPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public static function apply(
        Builder $query,
        Request $request,
        array $searchableColumns = ['name'],
        array $filterableColumns = ['status'],
        string $defaultSort = 'id',
        string $defaultDirection = 'desc',
        int $defaultPerPage = 20,
        int $maxPerPage = 100
    ) {
        // 1. Search across specified columns
        if ($search = $request->query('search')) {
            $query->where(function (Builder $q) use ($search, $searchableColumns) {
                foreach ($searchableColumns as $index => $column) {
                    if (str_contains($column, '.')) {
                        [$relation, $relationColumn] = explode('.', $column, 2);
                        if ($index === 0) {
                            $q->whereHas($relation, function (Builder $rq) use ($relationColumn, $search) {
                                $rq->where($relationColumn, 'like', "%{$search}%");
                            });
                        } else {
                            $q->orWhereHas($relation, function (Builder $rq) use ($relationColumn, $search) {
                                $rq->where($relationColumn, 'like', "%{$search}%");
                            });
                        }
                    } else {
                        if ($index === 0) {
                            $q->where($column, 'like', "%{$search}%");
                        } else {
                            $q->orWhere($column, 'like', "%{$search}%");
                        }
                    }
                }
            });
        }

        // 2. Exact filters
        foreach ($filterableColumns as $filterKey => $columnName) {
            $param = is_numeric($filterKey) ? $columnName : $filterKey;
            $dbCol = is_numeric($filterKey) ? $columnName : $columnName;

            if ($request->has($param) && $request->query($param) !== null && $request->query($param) !== '') {
                $value = $request->query($param);
                if (str_contains($dbCol, '.')) {
                    [$relation, $relationColumn] = explode('.', $dbCol, 2);
                    $query->whereHas($relation, function (Builder $rq) use ($relationColumn, $value) {
                        $rq->where($relationColumn, $value);
                    });
                } else {
                    $query->where($dbCol, $value);
                }
            }
        }

        // 3. Sorting
        $sort = $request->query('sort', $defaultSort);
        $direction = strtolower($request->query('direction', $defaultDirection)) === 'asc' ? 'asc' : 'desc';

        // Check if sort column is safe (alphanumeric and underscore only)
        if (preg_match('/^[a-zA-Z0-9_]+$/', $sort)) {
            $query->orderBy($sort, $direction);
        } else {
            $query->orderBy($defaultSort, $defaultDirection);
        }

        // 4. Pagination
        $perPage = (int) $request->query('per_page', $defaultPerPage);
        if ($perPage <= 0) {
            $perPage = $defaultPerPage;
        } elseif ($perPage > $maxPerPage) {
            $perPage = $maxPerPage;
        }

        return $query->paginate($perPage);
    }
}
