<?php

namespace App\Models;

use App\Actions\Payroll\PayrollCalculator;
use Database\Factories\PayrollEmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @phpstan-import-type PayItem from PayrollCalculator
 *
 * @phpstan-type Beneficiary array{name: string, bank: string, account: string, id_type: string, id_number: string}
 *
 * @property int $id
 * @property int $payroll_employer_id
 * @property string $code
 * @property string $name
 * @property string $starts_on
 * @property string|null $ends_on
 * @property int $salary_sen
 * @property list<PayItem> $recurring
 * @property Beneficiary|null $beneficiary
 */
#[Fillable(['code', 'name', 'starts_on', 'ends_on', 'salary_sen', 'recurring', 'beneficiary'])]
#[Hidden(['beneficiary'])]
class PayrollEmployee extends Model
{
    /** @use HasFactory<PayrollEmployeeFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['salary_sen' => 'integer', 'recurring' => 'array', 'beneficiary' => 'encrypted:array'];
    }
}
