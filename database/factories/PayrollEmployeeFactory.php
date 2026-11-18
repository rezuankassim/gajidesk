<?php

namespace Database\Factories;

use App\Models\PayrollEmployee;
use App\Models\PayrollEmployer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PayrollEmployee> */
class PayrollEmployeeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'payroll_employer_id' => PayrollEmployer::factory(),
            'code' => fake()->unique()->numerify('EMP-###'),
            'name' => fake()->name(),
            'starts_on' => '2020-01-01',
            'salary_sen' => 320_000,
            'recurring' => [],
            'beneficiary' => [
                'name' => 'Fictional Recipient',
                'bank' => 'Maybank',
                'account' => '000012345678',
                'id_type' => 'Fictional ID',
                'id_number' => 'DEMO-0123',
            ],
        ];
    }
}
