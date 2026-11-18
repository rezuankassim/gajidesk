<?php

namespace App\Models;

use App\Actions\Payroll\PayrollCalculator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * @phpstan-import-type Beneficiary from PayrollEmployee
 * @phpstan-import-type PayInputs from PayrollCalculator
 * @phpstan-import-type PayResult from PayrollCalculator
 *
 * @property int $id
 * @property int $payroll_run_id
 * @property int $payroll_employee_id
 * @property PayInputs $inputs
 * @property Beneficiary|null $beneficiary
 * @property PayResult|null $result
 */
#[Fillable(['payroll_employee_id', 'inputs', 'beneficiary'])]
#[Hidden(['beneficiary'])]
class PayrollRow extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['inputs' => 'array', 'result' => 'array', 'beneficiary' => 'encrypted:array'];
    }
}
