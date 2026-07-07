<?php

namespace App\Models\Scopes;

use App\Support\HospitalContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/** @implements Scope<Model> */
class HospitalScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $hospitalId = HospitalContext::id();

        if ($hospitalId !== null) {
            $builder->where($model->getTable().'.hospital_id', $hospitalId);
        }
    }
}
