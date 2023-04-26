<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Models\Traits;

/**
 * @template TModelClass of \Illuminate\Database\Eloquent\Model
 * @extends \Illuminate\Database\Eloquent\Builder<TModelClass>
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
trait Excludable
{
    /**
     * Get the array of columns
     *
     * @return mixed
     */
    private function getTableColumns()
    {
        /** @var \Illuminate\Database\Eloquent\Builder|\App\Models\BaseModel $this */
        return $this->getConnection()->getSchemaBuilder()->getColumnListing($this->getTable());
    }

    /**
     * Exclude an array of elements from the result.
     * @param Builder $query
     * @param array $columns
     *
     * @return \Illuminate\Database\Eloquent\Builder|static
     */
    public function scopeExclude($query, $columns): \Illuminate\Database\Eloquent\Builder
    {
        /** @var Builder|static $query */
        return $query->select(array_diff($this->getTableColumns(), (array) $columns));
    }
}
