<?php

namespace App\Actions\Payroll;

use App\Models\PayrollEmployer;
use App\Models\PayrollRun;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-import-type PayInputs from PayrollCalculator
 * @phpstan-import-type PayrollComparison from PayrollRun
 */
class ManagePayroll
{
    public function __construct(private PayrollCalculator $calculator) {}

    /**
     * @template T
     *
     * @param  Closure(PayrollEmployer): T  $operation
     * @return T
     */
    public function locked(Closure $operation): mixed
    {
        return DB::transaction(function () use ($operation) {
            $employer = PayrollEmployer::query()->lockForUpdate()->findOrFail(PayrollEmployer::ID);

            return $operation($employer);
        }, 3);
    }

    public function create(string $period): PayrollRun
    {
        return $this->locked(function (PayrollEmployer $employer) use ($period): PayrollRun {
            $run = $employer->runs()->firstOrCreate(['period' => $period]);

            if (! $run->wasRecentlyCreated) {
                return $run;
            }

            $end = Carbon::parse($period)->endOfMonth()->toDateString();
            $employees = $employer->employees()->where('starts_on', '<=', $end)
                ->where(fn ($query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', $period))
                ->orderBy('id')->get();

            if ($employees->isEmpty()) {
                throw ValidationException::withMessages(['month' => 'No employees were active in this month. Check the employment dates in the directory.']);
            }

            foreach ($employees as $employee) {
                $run->rows()->create([
                    'payroll_employee_id' => $employee->id,
                    'beneficiary' => $employee->beneficiary,
                    'inputs' => [
                        ...$employee->only(['code', 'name', 'salary_sen', 'starts_on', 'ends_on', 'recurring']),
                        'partial_month' => $employee->starts_on > $period || ($employee->ends_on !== null && $employee->ends_on < $end),
                        'proration' => null,
                        'adjustments' => [],
                        'statutory' => [],
                    ],
                ]);
            }

            return $run;
        });
    }

    /** @param Closure(PayInputs): PayInputs $change */
    public function revise(int $runId, int $rowId, int $revision, Closure $change): void
    {
        $this->locked(function (PayrollEmployer $employer) use ($runId, $rowId, $revision, $change): void {
            $run = $employer->runs()->findOrFail($runId);
            $this->checkRevision($run, $revision);
            $row = $run->rows()->findOrFail($rowId);
            $inputs = $change($row->inputs);

            if ($inputs !== $row->inputs) {
                $row->update(['inputs' => $inputs]);
                $run->increment('revision');
            }
        });
    }

    public function finalise(int $runId, int $revision, int $actorId, ?int $comparisonId): PayrollRun
    {
        return $this->locked(function (PayrollEmployer $employer) use ($runId, $revision, $actorId, $comparisonId): PayrollRun {
            $run = $employer->runs()->with('rows')->findOrFail($runId);

            if ($run->finalised_at !== null) {
                return $run;
            }

            $this->checkRevision($run, $revision);
            $comparison = $this->comparison($run);

            if (($comparison['id'] ?? null) !== $comparisonId) {
                throw ValidationException::withMessages(['run' => 'The comparison month changed. Review the updated worksheet before finalising.']);
            }

            if ($run->rows->isEmpty()) {
                throw ValidationException::withMessages(['run' => 'The worksheet has no employees.']);
            }

            foreach ($run->rows as $row) {
                $result = $this->calculator->calculate($row->inputs);

                if ($result['issues'] !== []) {
                    throw ValidationException::withMessages(['run' => $row->inputs['name'].' · '.implode('. ', $result['issues']).'.']);
                }

                $row->forceFill(['result' => $result])->save();
            }

            $run->forceFill([
                'finalised_at' => now(),
                'finalised_by' => $actorId,
                'employer_snapshot' => $employer->only(['name', 'work_location', 'jurisdiction']),
                'comparison_snapshot' => $comparison,
            ])->save();

            return $run;
        });
    }

    /** @return PayrollComparison|null */
    public function comparison(PayrollRun $run): ?array
    {
        if ($run->finalised_at !== null) {
            return $run->comparison_snapshot;
        }

        $prior = PayrollRun::query()->where('payroll_employer_id', $run->payroll_employer_id)
            ->where('period', '<', $run->period)->whereNotNull('finalised_at')
            ->latest('period')->with('rows')->first();

        if ($prior === null) {
            return null;
        }

        $rows = [];

        foreach ($prior->rows as $row) {
            $rows[$row->payroll_employee_id] = ['name' => $row->inputs['name'], 'result' => $row->result];
        }

        return ['id' => $prior->id, 'period' => $prior->period, 'rows' => $rows];
    }

    private function checkRevision(PayrollRun $run, int $revision): void
    {
        if ($run->finalised_at !== null || $run->revision !== $revision) {
            throw ValidationException::withMessages(['run' => 'The worksheet changed. Review the latest values and try again.']);
        }
    }
}
