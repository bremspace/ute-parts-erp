<?php

namespace App\Scopes;

use App\Services\BranchContextManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class CabangScope implements Scope
{
    /**
     * Terapkan filter cabang_id pada Eloquent query builder
     * bila BranchContextManager memiliki active cabang dan tidak dibypass.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(BranchContextManager::class);

        if ($context->isBypassed()) {
            return;
        }

        $cabangId = $context->getActiveCabangId();

        if ($cabangId !== null) {
            $builder->where($model->qualifyColumn('cabang_id'), $cabangId);
        }
    }
}
