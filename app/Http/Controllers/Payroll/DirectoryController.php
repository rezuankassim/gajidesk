<?php

namespace App\Http\Controllers\Payroll;

use App\Actions\Payroll\ManagePayroll;
use App\Actions\Payroll\PayrollCalculator;
use App\Http\Controllers\Controller;
use App\Models\PayrollEmployee;
use App\Models\PayrollEmployer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DirectoryController extends Controller
{
    public function index(): Response
    {
        $employer = PayrollEmployer::query()->find(PayrollEmployer::ID);

        return Inertia::render('payroll/directory', [
            'employer' => $employer?->only(['name', 'work_location', 'jurisdiction']),
            'jurisdictions' => PayrollEmployer::JURISDICTIONS,
            'employees' => $employer?->employees()->orderBy('code')->get()->map(fn (PayrollEmployee $employee): array => [
                ...$employee->only(['id', 'code', 'name', 'starts_on', 'ends_on', 'salary_sen', 'recurring']),
                'bank' => $employee->beneficiary['bank'] ?? null,
                'account' => self::maskAccount($employee->beneficiary['account'] ?? null),
            ]) ?? [],
        ]);
    }

    public function employer(Request $request, ManagePayroll $payroll): RedirectResponse
    {
        $details = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'work_location' => ['required', 'string', 'max:150'],
            'jurisdiction' => ['required', Rule::in(PayrollEmployer::JURISDICTIONS)],
        ]);

        if (! PayrollEmployer::query()->whereKey(PayrollEmployer::ID)->exists()) {
            PayrollEmployer::query()->createOrFirst(['id' => PayrollEmployer::ID], $details);

            return back();
        }

        $payroll->locked(function (PayrollEmployer $employer) use ($details): void {
            $employer->update($details);
            $employer->runs()->whereNull('finalised_at')->increment('revision');
        });

        return back();
    }

    public function store(Request $request, ManagePayroll $payroll): RedirectResponse
    {
        $payroll->locked(fn (PayrollEmployer $employer) => $employer->employees()->create($this->employeeDetails($request)));

        return back();
    }

    public function update(Request $request, int $employee, ManagePayroll $payroll): RedirectResponse
    {
        $payroll->locked(function (PayrollEmployer $employer) use ($request, $employee): void {
            $record = $employer->employees()->findOrFail($employee);
            $record->update($this->employeeDetails($request, $record));
        });

        return back();
    }

    /** @return array<string, list<string>> */
    public static function beneficiaryRules(): array
    {
        return [
            'beneficiary' => ['nullable', 'array:name,bank,account,id_type,id_number'],
            'beneficiary.name' => ['required_with:beneficiary', 'string', 'max:150'],
            'beneficiary.bank' => ['required_with:beneficiary', 'string', 'max:120'],
            'beneficiary.account' => ['required_with:beneficiary', 'string', 'regex:/\A[0-9]{4,34}\z/'],
            'beneficiary.id_type' => ['required_with:beneficiary', 'string', 'max:50'],
            'beneficiary.id_number' => ['required_with:beneficiary', 'string', 'regex:/\A[0-9A-Za-z-]{1,40}\z/'],
        ];
    }

    public static function maskAccount(?string $account): ?string
    {
        return blank($account) ? null : '•••• '.substr($account, -min(4, max(0, strlen($account) - 1)));
    }

    /** @return array<string, mixed> */
    private function employeeDetails(Request $request, ?PayrollEmployee $employee = null): array
    {
        $unique = Rule::unique('payroll_employees', 'code')->where('payroll_employer_id', PayrollEmployer::ID);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', $employee ? $unique->ignore($employee) : $unique],
            'name' => ['required', 'string', 'max:150'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'salary' => ['required', 'string', PayrollCalculator::MONEY],
            'recurring' => ['present', 'array', 'list', 'max:30'],
            'recurring.*' => ['array:type,label,amount'],
            'recurring.*.type' => ['required', Rule::in(['addition', 'deduction'])],
            'recurring.*.label' => ['required', 'string', 'max:100'],
            'recurring.*.amount' => ['required', 'string', PayrollCalculator::MONEY],
            ...self::beneficiaryRules(),
        ]);

        return [
            'code' => $data['code'],
            'name' => $data['name'],
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'] ?? null,
            'salary_sen' => PayrollCalculator::sen($data['salary']),
            'recurring' => array_map(fn (array $item): array => [
                'type' => $item['type'],
                'label' => $item['label'],
                'sen' => PayrollCalculator::sen($item['amount']),
            ], $data['recurring']),
            'beneficiary' => $data['beneficiary'] ?? $employee?->beneficiary,
        ];
    }
}
