<?php

namespace App\Models;

use App\Enums\LockMode;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepositoryLockPolicy extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'repository_id',
        'pattern',
        'lock_mode',
        'auto_lock',
        'lock_timeout_hours',
    ];

    protected $casts = [
        'lock_mode'          => LockMode::class,
        'auto_lock'          => 'boolean',
        'lock_timeout_hours' => 'integer',
    ];

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    public function isMandatory(): bool
    {
        return $this->lock_mode === LockMode::Mandatory;
    }

    public function matches(string $path): bool
    {
        return fnmatch($this->pattern, $path, FNM_PATHNAME | FNM_CASEFOLD);
    }
}
