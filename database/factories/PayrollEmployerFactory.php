<?php

namespace Database\Factories;

use App\Models\PayrollEmployer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PayrollEmployer> */
class PayrollEmployerFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => 'Pandan Studio Sdn. Bhd.',
            'work_location' => 'Kuching',
            'jurisdiction' => 'Sarawak',
        ];
    }
}
