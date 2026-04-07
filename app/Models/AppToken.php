<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AppToken extends Model
{
    use HasUuids;

    protected $table = 'app_tokens';

    protected $fillable = ['name', 'token', 'abilities'];

    protected $casts = [
        'abilities' => 'array',
        'last_used_at' => 'datetime',
    ];

    /**
     * @param  array<int, string>  $abilities
     * @return array{plaintext: string, token: AppToken}
     */
    public static function generate(string $name, array $abilities = ['*']): array
    {
        $plaintext = 'cru_app_' . Str::random(48);

        $token = static::create([
            'name' => $name,
            'token' => hash('sha256', $plaintext),
            'abilities' => $abilities,
        ]);

        return ['plaintext' => $plaintext, 'token' => $token];
    }

    public static function findByRawToken(string $rawToken): ?static
    {
        return static::query()
            ->where('token', hash('sha256', $rawToken))
            ->first();
    }

    public function can(string $ability): bool
    {
        return in_array('*', $this->abilities ?? [], true)
            || in_array($ability, $this->abilities ?? [], true);
    }

    public function touchLastUsed(): void
    {
        $this->timestamps = false;
        $this->last_used_at = now();
        $this->save();
        $this->timestamps = true;
    }
}
