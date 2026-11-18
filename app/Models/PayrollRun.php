<?php

namespace App\Models;

use App\Actions\Payroll\PayrollCalculator;
use Carbon\CarbonImmutable;
use Database\Factories\PayrollRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @phpstan-import-type PayResult from PayrollCalculator
 *
 * @phpstan-type EmployerDetails array{name: string, work_location: string, jurisdiction: string}
 * @phpstan-type PayrollComparison array{id: int, period: string, rows: array<int, array{name: string, result: PayResult|null}>}
 *
 * @property int $id
 * @property int $payroll_employer_id
 * @property string $period
 * @property int $revision
 * @property CarbonImmutable|null $finalised_at
 * @property int|null $finalised_by
 * @property EmployerDetails|null $employer_snapshot
 * @property PayrollComparison|null $comparison_snapshot
 * @property-read Collection<int, PayrollRow> $rows
 */
#[Fillable(['period'])]
class PayrollRun extends Model
{
    /** @use HasFactory<PayrollRunFactory> */
    use HasFactory;

    /** @return HasMany<PayrollRow, $this> */
    public function rows(): HasMany
    {
        return $this->hasMany(PayrollRow::class)->orderBy('payroll_employee_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'finalised_at' => 'immutable_datetime',
            'employer_snapshot' => 'array',
            'comparison_snapshot' => 'array',
        ];
    }
}
