<?php

namespace Database\Factories;

use App\Models\PayrollEmployer;
use App\Models\PayrollRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PayrollRun> */
class PayrollRunFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['payroll_employer_id' => PayrollEmployer::factory(), 'period' => '2026-11-01'];
    }
}
