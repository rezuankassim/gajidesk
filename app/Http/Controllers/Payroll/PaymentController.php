<?php

namespace App\Http\Controllers\Payroll;

use App\Actions\Payroll\ManagePayroll;
use App\Actions\Payroll\MaybankExporter;
use App\Http\Controllers\Controller;
use App\Models\PayrollEmployer;
use App\Models\PayrollRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PaymentController extends Controller
{
    public function show(Request $request, int $run, MaybankExporter $exporter): InertiaResponse
    {
        $request->validate(['payment_date' => ['sometimes', 'date_format:Y-m-d']]);
        $run = $this->finalised(PayrollEmployer::query()->findOrFail(PayrollEmployer::ID), $run);
        $date = $request->query('payment_date', Carbon::parse($run->period)->endOfMonth()->toDateString());
        $batch = $exporter->batch($run, $date);

        return Inertia::render('payroll/payment', [
            'run' => $run->id,
            'month' => substr($run->period, 0, 7),
            'employer' => $run->employer_snapshot,
            'paymentDate' => $date,
            'batch' => [
                ...$batch,
                'recipients' => array_map(fn (array $recipient): array => [
                    ...Arr::except($recipient, ['beneficiary']),
                    'bank' => $recipient['beneficiary']['bank'] ?? null,
                    'account' => DirectoryController::maskAccount($recipient['beneficiary']['account'] ?? null),
                ], $batch['recipients']),
            ],
            'csrf' => csrf_token(),
        ]);
    }

    public function download(Request $request, int $run, ManagePayroll $payroll, MaybankExporter $exporter): Response
    {
        $data = $request->validate([
            'payment_date' => ['required', 'date_format:Y-m-d'],
            'batch_version' => ['required', 'string', 'size:64'],
        ]);

        return $payroll->locked(function (PayrollEmployer $employer) use ($request, $run, $exporter, $data): Response {
            $run = $this->finalised($employer, $run);
            $batch = $exporter->batch($run, $data['payment_date']);

            if (! hash_equals($batch['version'], $data['batch_version'])) {
                throw ValidationException::withMessages(['batch' => 'The payment data changed. Review the latest batch before downloading.']);
            }
            if ($batch['recipients'] === [] || array_filter(array_column($batch['recipients'], 'error'))) {
                throw ValidationException::withMessages(['batch' => 'Resolve the beneficiary errors and ensure there is at least one positive payment.']);
            }

            $csv = $exporter->previewCsv($batch['recipients'], $data['payment_date']);
            DB::table('payroll_export_events')->insert([
                'payroll_run_id' => $run->id,
                'actor_id' => $request->user()->id,
                'payment_date' => $data['payment_date'],
                'format' => MaybankExporter::PREVIEW_FORMAT,
                'checksum' => hash('sha256', $csv),
                'beneficiary_revisions' => json_encode(array_column($batch['recipients'], 'revision', 'row_id'), JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);

            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'private, no-store',
                'Content-Disposition' => 'attachment; filename="payment-preview-'.substr($run->period, 0, 7).'.csv"',
            ]);
        });
    }

    public function correct(Request $request, int $run, int $row, ManagePayroll $payroll, MaybankExporter $exporter): RedirectResponse
    {
        $rules = DirectoryController::beneficiaryRules();
        $rules['beneficiary'][0] = 'required';
        $data = $request->validate([
            ...$rules,
            'reason' => ['required', 'string', 'max:500'],
            'batch_version' => ['required', 'string', 'size:64'],
            'payment_date' => ['required', 'date_format:Y-m-d'],
        ]);

        $payroll->locked(function (PayrollEmployer $employer) use ($request, $run, $row, $exporter, $data): void {
            $run = $this->finalised($employer, $run);
            $row = $run->rows->findOrFail($row);
            abort_unless($row->result['net'] > 0, 404);

            $batch = $exporter->batch($run, $data['payment_date']);
            if (! hash_equals($batch['version'], $data['batch_version'])) {
                throw ValidationException::withMessages(['batch' => 'The payment data changed. Review the latest batch before correcting it.']);
            }
            $before = collect($batch['recipients'])->firstWhere('row_id', $row->id)['beneficiary'];
            if ($before === $data['beneficiary']) {
                return;
            }

            DB::table('payroll_beneficiary_corrections')->insert([
                'payroll_row_id' => $row->id,
                'actor_id' => $request->user()->id,
                'reason' => $data['reason'],
                'before' => Crypt::encrypt($before),
                'after' => Crypt::encrypt($data['beneficiary']),
                'created_at' => now(),
            ]);
        });

        return back();
    }

    private function finalised(PayrollEmployer $employer, int $run): PayrollRun
    {
        return $employer->runs()->with('rows')->whereNotNull('finalised_at')->findOrFail($run);
    }
}
