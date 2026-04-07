<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use RuntimeException;

abstract class BaseModel extends Model
{
    use SoftDeletes;

    public const DELETED_AT = 'deleted_at';
    public const UPDATED_AT = 'updated_at';
    public const CREATED_AT = 'created_at';

    /**
     * Get the underlying table name statically.
     */
    public static function getTableName(): string
    {
        return with(new static())->getTable();
    }

    /**
     * True if this model's updated_at is newer than another model's.
     */
    public function updatedSince(Model $model): bool
    {
        return $this->getAttribute($this->getUpdatedAtColumn())
            > $model->getAttribute($model->getUpdatedAtColumn());
    }

    /**
     * True if this model has ever been updated after creation.
     */
    public function hasBeenUpdated(): bool
    {
        return $this->getAttribute($this->getUpdatedAtColumn())
            > $this->getAttribute($this->getCreatedAtColumn());
    }

    /**
     * Save without updating the updated_at timestamp.
     */
    public function saveWithoutTouch(): void
    {
        $this->withoutTimestampUpdate('save');
    }

    /**
     * Update attributes without touching updated_at.
     */
    public function updateWithoutTouch(array $attributes): void
    {
        $this->timestamps = false;
        $this->update($attributes);
        $this->timestamps = true;
    }

    /**
     * Soft-delete without touching updated_at.
     */
    public function deleteWithoutTouch(): void
    {
        $this->withoutTimestampUpdate('delete');
    }

    /**
     * Force-delete without touching timestamps.
     */
    public function forceDeleteWithoutTouch(): void
    {
        $this->withoutTimestampUpdate('forceDelete');
    }

    /**
     * Restore a soft-deleted model without touching updated_at.
     */
    public function restoreWithoutTouch(): void
    {
        $this->withoutTimestampUpdate('restore');
    }

    /**
     * Temporarily disable timestamps, call $method, then re-enable.
     */
    protected function withoutTimestampUpdate(string $method): void
    {
        if (! is_callable([$this, $method])) {
            throw new RuntimeException("Method '{$method}' is not callable on " . static::class . '.');
        }

        $this->timestamps = false;

        try {
            $this->{$method}();
        } finally {
            $this->timestamps = true;
        }
    }
}
