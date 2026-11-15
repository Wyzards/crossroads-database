<?php

namespace App\Services;

use App\Models\VettingSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The one mutual-exclusion lease for vetting writes. Acquire and renew are single conditional UPDATEs
 * (so two callers can never both win); every vetting write is fenced with assertHeld.
 */
class VettingLease
{
    private static function now(): string
    {
        return now()->utc()->format('Y-m-d H:i:s');
    }

    private static function table()
    {
        return DB::table('vetting_settings')->where('id', 1);
    }

    /** @return array{token:string, expires_at:string}|null null when someone else holds it */
    public static function acquire(string $holder, int $ttl): ?array
    {
        VettingSetting::row();
        $now = self::now();
        $token = (string) Str::uuid();
        $expires = now()->utc()->addSeconds($ttl)->format('Y-m-d H:i:s');

        $won = self::table()
            ->where(fn($q) => $q->whereNull('lease_token')->orWhere('lease_expires_at', '<', $now))
            ->update(['lease_holder' => $holder, 'lease_token' => $token, 'lease_expires_at' => $expires]);

        return $won ? ['token' => $token, 'expires_at' => $expires] : null;
    }

    public static function renew(string $token, int $ttl): ?string
    {
        $expires = now()->utc()->addSeconds($ttl)->format('Y-m-d H:i:s');
        $ok = self::table()->where('lease_token', $token)->where('lease_expires_at', '>=', self::now())
            ->update(['lease_expires_at' => $expires]);

        return $ok ? $expires : null;
    }

    public static function release(string $token): void
    {
        self::table()->where('lease_token', $token)
            ->update(['lease_holder' => null, 'lease_token' => null, 'lease_expires_at' => null]);
    }

    public static function holder(): ?array
    {
        $row = VettingSetting::row();
        if (!$row->lease_token || !$row->lease_expires_at || $row->lease_expires_at->lt(now()))
            return null;
        return ['holder' => $row->lease_holder, 'expires_at' => $row->lease_expires_at->toIso8601String()];
    }

    /** Aborts with 409 unless the token is the current, unexpired lease. */
    public static function assertHeld(?string $token): void
    {
        $held = $token && self::table()->where('lease_token', $token)->where('lease_expires_at', '>=', self::now())->exists();
        if (!$held)
            abort(response()->json(['message' => 'The vetting lease is not held by this token'], 409));
    }
}
