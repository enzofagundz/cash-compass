<?php

namespace App\Mcp\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;

trait PaginatesResults
{
    /**
     * @param  Builder<*>  $query
     * @return LengthAwarePaginator<int, mixed>
     */
    protected function paginate(Builder $query, Request $request, int $default = 25, int $max = 100): LengthAwarePaginator
    {
        $perPage = min(max(1, (int) $request->get('per_page', $default)), $max);
        $page = max(1, (int) $request->get('page', 1));

        return $query->paginate(perPage: $perPage, page: $page);
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @return array{page: int, per_page: int, total: int, has_more: bool}
     */
    protected function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'has_more' => $paginator->hasMorePages(),
        ];
    }
}
