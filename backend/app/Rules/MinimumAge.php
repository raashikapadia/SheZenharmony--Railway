<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;

/**
 * The participant must have had their Nth birthday by today. Checks the whole
 * date, not just the year, so someone born later in the cut-off year whose
 * birthday has not yet come round is still rejected.
 */
class MinimumAge implements ValidationRule
{
    public function __construct(private readonly int $years = 18) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $dateOfBirth = Carbon::parse((string) $value)->startOfDay();
        } catch (\Throwable) {
            return; // The `date` rule reports an unparseable value.
        }

        if ($dateOfBirth->gt(now()->subYears($this->years)->startOfDay())) {
            $fail("You must be {$this->years} years or older to participate.");
        }
    }
}
