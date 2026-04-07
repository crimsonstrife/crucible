<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepositoryLfsPolicy extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'repository_id',
        'pattern',
        'min_size_bytes',
        'description',
    ];

    protected $casts = [
        'min_size_bytes' => 'integer',
    ];

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    public function matches(string $path): bool
    {
        return fnmatch($this->pattern, $path, FNM_PATHNAME | FNM_CASEFOLD);
    }
}
