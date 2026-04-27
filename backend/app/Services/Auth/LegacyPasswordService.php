<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class LegacyPasswordService
{
    public function validateAndMigrate(User $user, string $plainPassword): bool
    {
        $storedPassword = (string) $user->clave;

        if ($this->matchesModernHash($storedPassword, $plainPassword)) {
            if (Hash::needsRehash($storedPassword)) {
                $user->forceFill([
                    'clave' => Hash::make($plainPassword),
                ])->save();
            }

            return true;
        }

        if (! $this->matchesLegacyHash($storedPassword, $plainPassword)) {
            return false;
        }

        $user->forceFill([
            'clave' => Hash::make($plainPassword),
        ])->save();

        return true;
    }

    private function matchesModernHash(string $storedPassword, string $plainPassword): bool
    {
        $info = Hash::info($storedPassword);

        if (($info['algoName'] ?? 'unknown') === 'unknown') {
            return false;
        }

        return Hash::check($plainPassword, $storedPassword);
    }

    private function matchesLegacyHash(string $storedPassword, string $plainPassword): bool
    {
        $legacyMd5 = md5($plainPassword);

        return hash_equals(strtolower($storedPassword), strtolower($legacyMd5));
    }
}
