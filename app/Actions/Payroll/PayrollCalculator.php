<?php

namespace App\Actions\Payroll;

/**
 * @phpstan-type PayItem array{type: string, label: string, sen: int, reason?: string|null}
 * @phpstan-type StatutoryReview array{basis: 'verified'|'not_applicable', sen: int, reason: string, actor_id: int, reviewed_at: string, stale?: bool}
 * @phpstan-type PayInputs array{code: string, name: string, salary_sen: int, starts_on: string, ends_on: string|null, recurring: list<PayItem>, partial_month: bool, proration: array{sen: int, reason: string}|null, adjustments: list<PayItem>, statutory: array<string, StatutoryReview>}
 * @phpstan-type PayLine array{key: string, label: string, bucket: 'earnings'|'reimbursements'|'deductions'|'contributions', sen: int, reason: string|null}
 * @phpstan-type PayResult array{earnings: int, reimbursements: int, deductions: int, contributions: int, basic: int|null, additions: int, net: int|null, estimated: bool, items: list<PayLine>, issues: list<string>, method: string}
 */
class PayrollCalculator
{
    public const VERSION = 'MYR-sen-v1';

    public const STATUTORY = [
        'epf_employee' => 'EPF employee',
        'epf_employer' => 'EPF employer',
        'socso_employee' => 'SOCSO employee',
        'socso_employer' => 'SOCSO employer',
        'eis_employee' => 'EIS employee',
        'eis_employer' => 'EIS employer',
        'pcb_employee' => 'PCB / MTD',
    ];

    public const ADJUSTMENTS = [
        'bonus' => 'Bonus',
        'overtime' => 'Overtime',
        'unpaid_leave' => 'Unpaid leave',
        'reimbursement' => 'Reimbursement',
        'addition' => 'Other addition',
        'deduction' => 'Other deduction',
    ];

    public const MONEY = 'regex:/\A[0-9]{1,7}(?:\.[0-9]{1,2})?\z/';

    public static function sen(string $amount): int
    {
        [$ringgit, $fraction] = array_pad(explode('.', $amount), 2, '');

        return ((int) $ringgit * 100) + (int) str_pad($fraction, 2, '0');
    }

    public static function decimal(int $sen): string
    {
        return ($sen < 0 ? '-' : '').intdiv(abs($sen), 100).'.'.str_pad((string) (abs($sen) % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * @param  PayInputs  $inputs
     * @return PayResult
     */
    public function calculate(array $inputs): array
    {
        $issues = [];
        $items = [];
        $basic = $inputs['salary_sen'];

        if ($inputs['partial_month']) {
            $basic = $inputs['proration']['sen'] ?? null;

            if ($basic === null) {
                $issues[] = 'Review prorated basic pay';
            }
        }

        if ($basic !== null) {
            $items[] = $this->item('basic', $inputs['partial_month'] ? 'Prorated basic pay' : 'Basic pay', 'earnings', $basic, $inputs['proration']['reason'] ?? null);
        }

        foreach (['recurring', 'adjustments'] as $source) {
            foreach ($inputs[$source] as $adjustment) {
                $type = $adjustment['type'];
                $bucket = match ($type) {
                    'unpaid_leave', 'deduction' => 'deductions',
                    'reimbursement' => 'reimbursements',
                    default => 'earnings',
                };
                $label = $adjustment['label'] ?: self::ADJUSTMENTS[$type];
                $items[] = $this->item($source.':'.$type.':'.$label, $label, $bucket, $adjustment['sen'], $adjustment['reason'] ?? null);
            }
        }

        $missingStatutory = [];
        $staleStatutory = [];

        foreach (self::STATUTORY as $key => $label) {
            $review = $inputs['statutory'][$key] ?? null;
            if ($review === null) {
                $missingStatutory[] = $label;

                continue;
            }

            if (! empty($review['stale'])) {
                $staleStatutory[] = $label;
            }

            $basis = $review['basis'] === 'not_applicable' ? 'Not applicable' : 'Manually verified';
            $items[] = $this->item($key, $label, str_ends_with($key, '_employer') ? 'contributions' : 'deductions', $review['sen'], $basis.' · '.$review['reason']);
        }

        if ($missingStatutory !== []) {
            $issues[] = 'Verify '.implode(', ', $missingStatutory);
        }

        $totals = ['earnings' => 0, 'reimbursements' => 0, 'deductions' => 0, 'contributions' => 0];

        foreach ($items as $item) {
            $totals[$item['bucket']] += $item['sen'];
        }

        $net = $issues === [] ? $totals['earnings'] + $totals['reimbursements'] - $totals['deductions'] : null;

        if ($staleStatutory !== []) {
            $issues[] = 'Reverify '.implode(', ', $staleStatutory).' after the pay change';
        }

        if ($net !== null && $net < 0) {
            $issues[] = 'Net pay is negative';
        }

        return [
            ...$totals,
            'basic' => $basic,
            'additions' => $totals['earnings'] - ($basic ?? 0),
            'net' => $net,
            'estimated' => $net !== null && $staleStatutory !== [],
            'items' => $items,
            'issues' => $issues,
            'method' => self::VERSION,
        ];
    }

    /**
     * @param  PayResult  $current
     * @param  PayResult|null  $previous
     * @return list<string>
     */
    public function changes(array $current, ?array $previous): array
    {
        if ($previous === null) {
            return [];
        }

        $groups = [];

        foreach ([$current['items'], $previous['items']] as $index => $items) {
            foreach ($items as $item) {
                $key = $item['key'];
                $groups[$key] ??= ['label' => $item['label'], 'bucket' => $item['bucket'], 'current' => 0, 'previous' => 0];
                $groups[$key][$index === 0 ? 'current' : 'previous'] += $item['sen'];
            }
        }

        $changes = [];

        foreach ($groups as $key => $group) {
            $difference = $group['current'] - $group['previous'];

            if ($difference === 0) {
                continue;
            }

            $label = str_starts_with($key, 'adjustments:') ? 'one-off '.$group['label'] : $group['label'];
            $verb = match (true) {
                $group['previous'] === 0 => 'added',
                $group['current'] === 0 => 'removed',
                $difference > 0 => 'increased',
                default => 'decreased',
            };

            $changes[] = 'RM'.self::decimal(abs($difference)).' '.$label.' '.$verb.' this month'
                .($group['bucket'] === 'contributions' ? ' (employer contribution)' : '').'.';
        }

        return $changes;
    }

    /**
     * @param  'earnings'|'reimbursements'|'deductions'|'contributions'  $bucket
     * @return PayLine
     */
    private function item(string $key, string $label, string $bucket, int $sen, ?string $reason): array
    {
        return ['key' => $key, 'label' => $label, 'bucket' => $bucket, 'sen' => $sen, 'reason' => $reason];
    }
}
