<?php

namespace Abix\DataFiltering\Traits;

use Illuminate\Foundation\Auth\User;
use Illuminate\Database\Eloquent\Builder;
use Abix\DataFiltering\Services\Filters\FilterService;

trait DatabaseFilterable
{
    /**
     * Apply filters
     *
     * @param array $data
     * @param User|null $user
     * @param Builder $query
     * @return Builder
     */
    public function filter(
        array $data,
        ?User $user,
        $query = null
    ): Builder {
        if (!$this->defaultFilterService) {
            $filterService = FilterService::class;
        }

        return (new $filterService())
            ->setModel($this)
            ->setQuery($query)
            ->setUser($user)
            ->setData($data)
            ->filter();
    }

    /**
     * Search
     *
     * @param Builder $builder
     * @param string $search
     * @return Builder
     */
    public function scopeSearch(Builder $builder, string $search)
    {
        $columns = $this->searchableColumns ?? [];

        $terms = explode(' ', $search);

        foreach ($terms as $term) {
            foreach ($columns as $column) {
                $builder->where($column, 'LIKE', '%' . $term . '%');
            }
        }

        return $builder;
    }
}
