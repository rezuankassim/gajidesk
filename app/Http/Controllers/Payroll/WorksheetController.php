<?php

namespace App\Http\Controllers\Payroll;

use App\Actions\Payroll\ManagePayroll;
use App\Actions\Payroll\PayrollCalculator;
use App\Http\Controllers\Controller;
use App\Models\PayrollEmployer;
use App\Models\PayrollRow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WorksheetController extends Controller
{
    public function index(Request $request, ManagePayroll $payroll, PayrollCalculator $calculator): Response
    {
        $request->validate(['month' => ['sometimes', 'date_format:Y-m']]);
        $month = $request->query('month', Date::now('Asia/Kuching')->format('Y-m'));
        $employer = PayrollEmployer::query()->find(PayrollEmployer::ID);
        $run = $employer?->runs()->where('period', $month.'-01')->with('rows')->first();
        $comparison = $run ? $payroll->comparison($run) : null;
        $previousRows = $comparison['rows'] ?? [];
        $rows = $run?->rows->map(function (PayrollRow $row) use ($calculator, $previousRows, $comparison): array {
            $result = $row->result ?? $calculator->calculate($row->inputs);
            $previous = $previousRows[$row->payroll_employee_id]['result'] ?? null;
            $canCompare = $result['net'] !== null && ($previous['net'] ?? null) !== null;
            $changes = $canCompare ? $calculator->changes($result, $previous) : [];

            return [
                'id' => $row->id,
                'inputs' => $row->inputs,
                'result' => $result,
                'delta' => $canCompare ? $result['net'] - $previous['net'] : null,
                'new_employee' => $comparison !== null && $previous === null,
                'changes' => $changes,
            ];
        }) ?? collect();

        $resolved = $rows->filter(fn (array $row): bool => $row['result']['net'] !== null);
        $departures = array_diff_key($previousRows, array_fill_keys($run?->rows->pluck('payroll_employee_id')->all() ?? [], true));

        return Inertia::render('payroll/worksheet', [
            'employer' => $run->employer_snapshot ?? $employer?->only(['name', 'work_location', 'jurisdiction']),
            'month' => $month,
            'months' => $employer?->runs()->latest('period')->get(['period', 'finalised_at'])
                ->map(fn ($run) => ['month' => substr($run->period, 0, 7), 'finalised' => $run->finalised_at !== null]),
            'run' => $run?->only(['id', 'revision', 'finalised_at']),
            'rows' => $rows,
            'previous' => $comparison === null ? null : ['id' => $comparison['id'], 'month' => substr($comparison['period'], 0, 7)],
            'departures' => array_column($departures, 'name'),
            'totals' => [
                'headcount' => $rows->count(),
                'issues' => $rows->filter(fn (array $row): bool => $row['result']['issues'] !== [])->count(),
                'unresolved' => $rows->count() - $resolved->count(),
                'estimated' => $resolved->filter(fn (array $row): bool => $row['result']['estimated'])->count(),
                'earnings' => $resolved->sum('result.earnings'),
                'reimbursements' => $resolved->sum('result.reimbursements'),
                'deductions' => $resolved->sum('result.deductions'),
                'contributions' => $resolved->sum('result.contributions'),
                'net' => $resolved->sum('result.net'),
            ],
            'statutoryLabels' => PayrollCalculator::STATUTORY,
            'adjustmentLabels' => PayrollCalculator::ADJUSTMENTS,
        ]);
    }

    public function store(Request $request, ManagePayroll $payroll): RedirectResponse
    {
        $validated = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        $run = $payroll->create($validated['month'].'-01');

        return $this->worksheet($run->period);
    }

    public function adjustments(Request $request, int $run, int $row, ManagePayroll $payroll): RedirectResponse
    {
        $validated = $request->validate([
            'revision' => ['required', 'integer', 'min:0'],
            'adjustments' => ['present', 'array', 'list', 'max:30'],
            'adjustments.*' => ['array:type,label,amount,reason'],
            'adjustments.*.type' => ['required', Rule::in(array_keys(PayrollCalculator::ADJUSTMENTS))],
            'adjustments.*.label' => ['nullable', 'string', 'max:100'],
            'adjustments.*.amount' => ['required', 'string', PayrollCalculator::MONEY],
            'adjustments.*.reason' => ['nullable', 'string', 'max:500'],
            'proration_amount' => ['nullable', 'required_with:proration_reason', 'string', PayrollCalculator::MONEY],
            'proration_reason' => ['nullable', 'required_with:proration_amount', 'string', 'max:500'],
        ]);

        $payroll->revise($run, $row, (int) $validated['revision'], function (array $inputs) use ($validated): array {
            $adjustments = [];
            foreach ($validated['adjustments'] as $index => $item) {
                if (in_array($item['type'], ['addition', 'deduction'], true) && blank($item['label'] ?? null)) {
                    throw ValidationException::withMessages(["adjustments.$index.label" => 'Enter a name for this adjustment.']);
                }
                if (in_array($item['type'], ['overtime', 'unpaid_leave'], true) && blank($item['reason'] ?? null)) {
                    throw ValidationException::withMessages(["adjustments.$index.reason" => 'Enter how you verified the amount.']);
                }
                $adjustments[] = [
                    'type' => $item['type'],
                    'label' => $item['label'] ?? '',
                    'sen' => PayrollCalculator::sen($item['amount']),
                    'reason' => $item['reason'] ?? null,
                ];
            }

            $proration = $inputs['partial_month'] && isset($validated['proration_amount'])
                ? ['sen' => PayrollCalculator::sen($validated['proration_amount']), 'reason' => $validated['proration_reason']]
                : null;

            if ($inputs['proration'] !== $proration || $inputs['adjustments'] !== $adjustments) {
                $inputs['statutory'] = array_map(fn (array $review): array => [...$review, 'stale' => true], $inputs['statutory']);
            }

            return [...$inputs, 'proration' => $proration, 'adjustments' => $adjustments];
        });

        return back();
    }

    public function statutory(Request $request, int $run, int $row, ManagePayroll $payroll): RedirectResponse
    {
        $validated = $request->validate([
            'revision' => ['required', 'integer', 'min:0'],
            'statutory' => ['required', 'array:'.implode(',', array_keys(PayrollCalculator::STATUTORY))],
            'statutory.*' => ['array:basis,amount,reason'],
            'statutory.*.basis' => ['required', Rule::in(['verified', 'not_applicable'])],
            'statutory.*.amount' => ['nullable', 'string', PayrollCalculator::MONEY],
            'statutory.*.reason' => ['required', 'string', 'max:500'],
        ]);

        $payroll->revise($run, $row, (int) $validated['revision'], function (array $inputs) use ($request, $validated): array {
            $reviews = $inputs['statutory'];
            foreach ($validated['statutory'] as $key => $entry) {
                $amount = Arr::get($entry, 'amount');
                if ($entry['basis'] === 'verified' && $amount === null) {
                    throw ValidationException::withMessages(["statutory.$key.amount" => 'Enter the verified amount, including 0.00 if applicable.']);
                }
                if ($entry['basis'] === 'not_applicable' && $amount !== null) {
                    throw ValidationException::withMessages(["statutory.$key.amount" => 'Clear the amount when confirming this is inapplicable.']);
                }
                $review = [
                    'basis' => $entry['basis'],
                    'sen' => $amount === null ? 0 : PayrollCalculator::sen($amount),
                    'reason' => $entry['reason'],
                ];
                if (empty($reviews[$key]['stale']) && $review === Arr::only($reviews[$key] ?? [], ['basis', 'sen', 'reason'])) {
                    continue;
                }
                $reviews[$key] = $review + ['actor_id' => $request->user()->id, 'reviewed_at' => now()->toISOString()];
            }

            return [...$inputs, 'statutory' => $reviews];
        });

        return back();
    }

    public function finalise(Request $request, int $run, ManagePayroll $payroll): RedirectResponse
    {
        $validated = $request->validate([
            'revision' => ['required', 'integer', 'min:0'],
            'comparison_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $finalised = $payroll->finalise($run, (int) $validated['revision'], $request->user()->id, isset($validated['comparison_id']) ? (int) $validated['comparison_id'] : null);

        return $this->worksheet($finalised->period);
    }

    public function payslip(int $run, int $row): Response
    {
        $run = PayrollEmployer::query()->findOrFail(PayrollEmployer::ID)->runs()->whereNotNull('finalised_at')->findOrFail($run);
        $row = $run->rows()->findOrFail($row);

        return Inertia::render('payroll/payslip', [
            'employer' => $run->employer_snapshot,
            'month' => substr($run->period, 0, 7),
            'employee' => Arr::only($row->inputs, ['name', 'code']),
            'result' => $row->result,
        ]);
    }

    private function worksheet(string $period): RedirectResponse
    {
        return to_route('payroll.index', ['month' => substr($period, 0, 7)]);
    }
}
