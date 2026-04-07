<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait HasExternalId
{
    public static function bootHasExternalId(): void
    {
        static::creating(function ($model) {
            if (empty($model->external_id)) {
                $model->external_id = (string) Str::orderedUuid();
            }
        });
    }
}
