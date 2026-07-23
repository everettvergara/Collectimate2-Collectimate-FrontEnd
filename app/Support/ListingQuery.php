<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ListingQuery
{
    /**
     * @param  list<string>  $searchColumns
     * @param  list<string>  $sortableColumns
     */
    public static function paginate(
        Builder $query,
        Request $request,
        array $searchColumns,
        array $sortableColumns,
        string $defaultSort = 'id',
        string $defaultDirection = 'desc',
        int $perPage = 15,
    ): LengthAwarePaginator {
        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function (Builder $builder) use ($searchColumns, $search): void {
                foreach ($searchColumns as $column) {
                    if (str_contains($column, '.')) {
                        [$relation, $field] = explode('.', $column, 2);
                        $builder->orWhereHas($relation, function (Builder $relationQuery) use ($field, $search): void {
                            $relationQuery->where($field, 'like', "%{$search}%");
                        });
                    } else {
                        $builder->orWhere($column, 'like', "%{$search}%");
                    }
                }
            });
        }

        $sort = $request->string('sort')->toString() ?: $defaultSort;
        $direction = strtolower($request->string('direction')->toString() ?: $defaultDirection) === 'asc' ? 'asc' : 'desc';

        if (in_array($sort, $sortableColumns, true)) {
            $query->orderBy($sort, $direction);
        } else {
            $query->orderBy($defaultSort, $defaultDirection);
        }

        return $query->paginate($perPage)->withQueryString();
    }
}
