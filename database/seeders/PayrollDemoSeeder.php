<?php

namespace Database\Seeders;

use App\Actions\Payroll\ManagePayroll;
use App\Actions\Payroll\PayrollCalculator;
use App\Models\PayrollEmployee;
use App\Models\PayrollEmployer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use LogicException;

/** @phpstan-import-type StatutoryReview from PayrollCalculator */
class PayrollDemoSeeder extends Seeder
{
    public const EMAIL = 'admin@pandan.example';

    public const PASSWORD = 'demo-payroll-2026';

    public const REVIEW_REASON = 'Fictional demo payroll figures, verified for the walkthrough.';

    public const JOINER_BASIC = '1800.00';

    public function run(ManagePayroll $payroll): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new LogicException('Payroll demo seeding is available in local and testing environments.');
        }

        DB::transaction(function () use ($payroll): void {
            if (PayrollEmployer::query()->exists() || User::query()->where('email', self::EMAIL)->exists()) {
                $this->command?->warn('Payroll data already exists. Demo seeding skipped.');

                return;
            }

            $admin = User::factory()->create([
                'name' => 'Nadia Lim',
                'email' => self::EMAIL,
                'password' => self::PASSWORD,
                'is_payroll_admin' => true,
            ]);
            $employer = PayrollEmployer::factory()->create(['id' => PayrollEmployer::ID]);
            $current = Date::now('Asia/Kuching')->startOfMonth();
            $previous = $current->subMonth();
            $people = [
                ['Nur Aina Salleh', 380_000, 'Maybank', [['type' => 'addition', 'label' => 'Transport allowance', 'sen' => 15_000]]],
                ['Daniel Wong', 420_000, 'CIMB Bank', []],
                ['Farah Iskandar', 340_000, 'Maybank', [['type' => 'deduction', 'label' => 'Staff purchase', 'sen' => 5_000]]],
                ['Ethan anak Jali', 360_000, 'Public Bank', []],
            ];

            foreach ($people as $index => [$name, $salary, $bank, $recurring]) {
                $number = $index + 1;
                PayrollEmployee::factory()->create([
                    'payroll_employer_id' => $employer->id,
                    'code' => 'EMP-00'.$number,
                    'name' => $name,
                    'starts_on' => $index === 3 ? $current->setDay(16)->toDateString() : $previous->subYear()->toDateString(),
                    'salary_sen' => $salary,
                    'recurring' => $recurring,
                    'beneficiary' => [
                        'name' => $name,
                        'bank' => $bank,
                        'account' => '00001234000'.$number,
                        'id_type' => 'Fictional ID',
                        'id_number' => 'DEMO-000'.$number,
                    ],
                ]);
            }

            $prior = $payroll->create($previous->toDateString());
            $draft = $payroll->create($current->toDateString());
            foreach ([$prior, $draft] as $run) {
                foreach ($run->rows as $row) {
                    if ($row->inputs['partial_month']) {
                        continue;
                    }
                    $payroll->revise($run->id, $row->id, $run->refresh()->revision, function (array $inputs) use ($run, $draft, $admin): array {
                        if ($run->id === $draft->id && $inputs['code'] === 'EMP-002') {
                            $inputs['adjustments'] = [['type' => 'bonus', 'label' => '', 'sen' => 20_000, 'reason' => 'Demo recognition bonus']];
                        }

                        return [...$inputs, 'statutory' => self::fictionalStatutory($admin->id)];
                    });
                }
            }

            $payroll->finalise($prior->id, $prior->refresh()->revision, $admin->id, null);
        });
    }

    /** @return array<string, StatutoryReview> */
    public static function fictionalStatutory(int $actorId): array
    {
        $amounts = [35_000, 41_000, 1_575, 5_515, 630, 630, 0];
        $statutory = [];
        foreach (array_keys(PayrollCalculator::STATUTORY) as $index => $key) {
            $statutory[$key] = [
                'basis' => 'verified',
                'sen' => $amounts[$index],
                'reason' => self::REVIEW_REASON,
                'actor_id' => $actorId,
                'reviewed_at' => now()->toISOString(),
            ];
        }

        return $statutory;
    }
}
