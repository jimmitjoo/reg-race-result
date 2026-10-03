<?php

namespace App\Results;

final class AgeGroup
{
    /**
     * Age group from the birth year, as Swedish athletics counts age (the age you turn that year):
     * M/K35, 40 … for veterans, P/F12–17 and P/F19 for youth, M/K22 for juniors; null for seniors
     * and children under 12.
     */
    public static function for(int $birthYear, string $gender, int $year): ?string
    {
        if (! in_array($gender, ['M', 'K'], true)) {
            return null;
        }

        $age = $year - $birthYear;
        $youth = $gender === 'M' ? 'P' : 'F';

        return match (true) {
            $age >= 35 => $gender.(intdiv($age, 5) * 5),
            $age >= 23 => null,
            $age >= 20 => "{$gender}22",
            $age >= 18 => "{$youth}19",
            $age >= 12 => $youth.$age,
            default => null,
        };
    }

    public static function isVeteran(?string $group): bool
    {
        return $group !== null && preg_match('/^[MK]\d{2}$/', $group) && (int) substr($group, 1) >= 35;
    }
}
