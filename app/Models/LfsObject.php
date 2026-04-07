<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LfsObject extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'repository_id',
        'oid',
        'size',
        'mime_type',
        'storage_path',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }
}
