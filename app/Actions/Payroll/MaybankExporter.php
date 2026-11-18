<?php

namespace App\Actions\Payroll;

use App\Models\PayrollEmployee;
use App\Models\PayrollRun;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * @phpstan-import-type Beneficiary from PayrollEmployee
 *
 * @phpstan-type Recipient array{row_id: int, code: string, name: string, beneficiary: Beneficiary|null, revision: int|null, net: int, error: string|null}
 * @phpstan-type PaymentBatch array{recipients: list<Recipient>, excluded: list<string>, total: int, version: string, limitExceeded: bool}
 */
class MaybankExporter
{
    public const PREVIEW_FORMAT = 'payment-preview-v1';

    public const MAX_RECIPIENTS = 100;

    public const MAX_TOTAL_SEN = 25_000_000;

    /** @return array<int, array{revision: int, beneficiary: Beneficiary}> */
    private function effectiveBeneficiaries(PayrollRun $run): array
    {
        $latest = DB::table('payroll_beneficiary_corrections')
            ->whereIn('payroll_row_id', $run->rows->modelKeys())
            ->selectRaw('MAX(id) AS id')
            ->groupBy('payroll_row_id');

        return DB::table('payroll_beneficiary_corrections')->whereIn('id', $latest)->get()
            ->mapWithKeys(fn ($change) => [$change->payroll_row_id => [
                'revision' => $change->id,
                'beneficiary' => Crypt::decrypt($change->after),
            ]])->all();
    }

    /** @return PaymentBatch */
    public function batch(PayrollRun $run, string $paymentDate): array
    {
        $corrections = $this->effectiveBeneficiaries($run);
        $recipients = [];
        $excluded = [];
        $total = 0;

        foreach ($run->rows as $row) {
            $net = $row->result['net'] ?? null;
            if ($net === null || $net < 0) {
                throw new LogicException('A finalised payment row must have nonnegative net pay.');
            }
            if ($net === 0) {
                $excluded[] = $row->inputs['name'];

                continue;
            }

            $beneficiary = $corrections[$row->id]['beneficiary'] ?? $row->beneficiary;
            $missing = array_filter(['name', 'bank', 'account', 'id_type', 'id_number'], fn (string $field): bool => blank($beneficiary[$field] ?? null));
            $recipients[] = [
                'row_id' => $row->id,
                'code' => $row->inputs['code'],
                'name' => $row->inputs['name'],
                'beneficiary' => $beneficiary,
                'revision' => $corrections[$row->id]['revision'] ?? null,
                'net' => $net,
                'error' => $missing === [] ? null : 'Missing beneficiary '.implode(', ', $missing).'.',
            ];
            $total += $net;
        }

        $version = hash_hmac('sha256', json_encode([
            self::PREVIEW_FORMAT, $run->id, $paymentDate, array_column($recipients, 'revision', 'row_id'),
        ], JSON_THROW_ON_ERROR), config('app.key'));

        return [
            'recipients' => $recipients,
            'excluded' => $excluded,
            'total' => $total,
            'version' => $version,
            'limitExceeded' => count($recipients) > self::MAX_RECIPIENTS || $total > self::MAX_TOTAL_SEN,
        ];
    }

    /** @param list<Recipient> $recipients */
    public function previewCsv(array $recipients, string $paymentDate): string
    {
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new LogicException('Could not open the payment preview stream.');
        }
        fputcsv($stream, ['Payment preview only', 'Payment date', 'Employee code', 'Beneficiary name', 'Bank', 'Account number', 'Amount (MYR)'], ',', '"', '', "\r\n");

        foreach ($recipients as $recipient) {
            $beneficiary = $recipient['beneficiary'];
            if ($beneficiary === null || $recipient['error'] !== null) {
                throw new LogicException('A preview recipient must have a complete beneficiary.');
            }
            fputcsv($stream, [
                'PREVIEW', $paymentDate, $this->safeText($recipient['code']), $this->safeText($beneficiary['name']),
                $this->safeText($beneficiary['bank']), $beneficiary['account'], PayrollCalculator::decimal($recipient['net']),
            ], ',', '"', '', "\r\n");
        }

        rewind($stream);
        $bytes = stream_get_contents($stream);
        fclose($stream);

        if ($bytes === false) {
            throw new LogicException('Could not read the payment preview stream.');
        }

        return $bytes;
    }

    private function safeText(string $text): string
    {
        return preg_match('/\A[\s\p{Cf}]*[=+\-@]|[\x00-\x1f]/u', $text) ? "'".$text : $text;
    }
}
