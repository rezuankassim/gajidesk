<?php

use App\Actions\Payroll\PayrollCalculator;
use App\Models\PayrollEmployee;
use App\Models\PayrollEmployer;
use App\Models\PayrollRun;
use App\Models\User;
use Database\Seeders\PayrollDemoSeeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

function seedPayroll(): array
{
    test()->travelTo(now('Asia/Kuching')->setDate(2026, 11, 22)->setTime(10, 0));
    test()->seed(PayrollDemoSeeder::class);
    $admin = User::query()->where('email', PayrollDemoSeeder::EMAIL)->firstOrFail();
    $prior = PayrollRun::query()->where('period', '2026-10-01')->firstOrFail();
    $draft = PayrollRun::query()->where('period', '2026-11-01')->firstOrFail();

    return [$admin, $prior, $draft];
}

function statutoryInput(string $reason = 'Verified against fictional payroll records.'): array
{
    $amounts = ['350.00', '410.00', '15.75', '55.15', '6.30', '6.30', '0.00'];

    return array_combine(array_keys(PayrollCalculator::STATUTORY), array_map(fn (string $amount): array => [
        'basis' => 'verified', 'amount' => $amount, 'reason' => $reason,
    ], $amounts));
}

function resolvePayrollJoiner(PayrollRun $run): void
{
    $row = $run->rows()->reorder('id', 'desc')->firstOrFail();
    test()->patch(route('payroll.adjustments', [$run->id, $row->id]), [
        'revision' => $run->refresh()->revision,
        'adjustments' => [],
        'proration_amount' => '1800.00',
        'proration_reason' => 'Reviewed Sarawak demo proration.',
    ])->assertSessionHasNoErrors();
    test()->patch(route('payroll.statutory', [$run->id, $row->id]), [
        'revision' => $run->refresh()->revision,
        'statutory' => statutoryInput(),
    ])->assertSessionHasNoErrors();
}

function payrollCsvRows(string $csv): array
{
    $stream = fopen('php://memory', 'w+');
    fwrite($stream, $csv);
    rewind($stream);
    fgetcsv($stream, escape: '');
    $rows = [];

    while (($row = fgetcsv($stream, escape: '')) !== false) {
        $rows[$row[2]] = $row;
    }

    fclose($stream);

    return $rows;
}

it('opens the worksheet for the verified payroll administrator and protects every payroll surface', function () {
    [$admin, $prior] = seedPayroll();
    $row = $prior->rows()->firstOrFail();

    $this->get(route('payroll.index'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create());
    $this->get(route('payroll.directory'))->assertForbidden();
    $this->get(route('payroll.payslip', [$prior, $row]))->assertForbidden();
    $this->post(route('payroll.download', $prior))->assertForbidden();

    auth()->guard('web')->logout();
    $this->post(route('login.store'), ['email' => $admin->email, 'password' => PayrollDemoSeeder::PASSWORD])
        ->assertRedirect(route('payroll.index'));

    $this->get(route('payroll.index'))->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertInertia(fn ($page) => $page
            ->component('payroll/worksheet')->where('month', '2026-11')
            ->where('totals.headcount', 4)->where('totals.issues', 1)
            ->where('rows.0.delta', 0)->where('rows.1.delta', 20_000)
            ->where('rows.3.result.basic', null)
            ->where('rows.3.result.net', null));
});

it('recalculates adjustments in exact sen, preserves employer contribution separation and requires re-verification', function () {
    [$admin, $prior, $draft] = seedPayroll();
    $row = $draft->rows()->firstOrFail();
    $this->actingAs($admin);

    $data = [
        'revision' => $draft->revision,
        'adjustments' => [
            ['type' => 'bonus', 'amount' => '200.01'],
            ['type' => 'reimbursement', 'amount' => '10.09'],
            ['type' => 'unpaid_leave', 'amount' => '5.10', 'reason' => 'Reviewed fictional amount.'],
        ],
    ];

    $this->patch(route('payroll.adjustments', [$draft, $row]), $data)->assertSessionHasNoErrors();
    $this->patch(route('payroll.adjustments', [$draft, $row]), $data)->assertSessionHasErrors('run');
    $updatedRevision = $draft->refresh()->revision;
    $this->patch(route('payroll.adjustments', [$draft, $row]), [...$data, 'revision' => $updatedRevision])->assertSessionHasNoErrors();
    expect($draft->refresh()->revision)->toBe($updatedRevision);

    $this->get(route('payroll.index'))->assertInertia(fn ($page) => $page
        ->where('rows.0.result.earnings', 415_001)->where('rows.0.result.reimbursements', 1_009)
        ->where('rows.0.result.deductions', 37_715)->where('rows.0.result.contributions', 47_145)
        ->where('rows.0.result.net', 378_295)->where('rows.0.result.estimated', true)
        ->where('rows.0.delta', 20_500)->where('totals.net', 1_078_885));

    $this->post(route('payroll.finalise', $draft), ['revision' => $draft->revision, 'comparison_id' => $prior->id])
        ->assertSessionHasErrors('run');
    expect($draft->refresh()->finalised_at)->toBeNull();
    expect($draft->rows()->whereNotNull('result')->count())->toBe(0);
});

it('requires proration and distinguishes a missing statutory amount from verified zero', function () {
    [$admin, $prior, $draft] = seedPayroll();
    $row = $draft->rows()->reorder('id', 'desc')->firstOrFail();
    $this->actingAs($admin);

    $this->post(route('payroll.finalise', $draft), ['revision' => $draft->revision, 'comparison_id' => $prior->id])
        ->assertSessionHasErrors('run');
    expect($draft->rows()->whereNotNull('result')->count())->toBe(0);

    $this->patch(route('payroll.statutory', [$draft, $row]), [
        'revision' => $draft->revision,
        'statutory' => ['pcb_employee' => ['basis' => 'verified', 'amount' => null, 'reason' => 'Checked.']],
    ])->assertSessionHasErrors('statutory.pcb_employee.amount');
    expect($row->refresh()->inputs['statutory'])->toBe([]);

    resolvePayrollJoiner($draft);
    expect($row->refresh()->inputs['statutory']['pcb_employee']['sen'])->toBe(0);

    $request = ['revision' => $draft->refresh()->revision, 'comparison_id' => $prior->id];
    $this->post(route('payroll.finalise', $draft), $request)->assertRedirect(route('payroll.index', ['month' => '2026-11']));
    $finalised = $draft->refresh()->finalised_at;
    $this->travel(1)->minute();
    $this->post(route('payroll.finalise', $draft), $request)->assertRedirect(route('payroll.index', ['month' => '2026-11']));
    expect($draft->refresh()->finalised_at->equalTo($finalised))->toBeTrue();
    expect($row->refresh()->result['net'])->toBe(142_795);
    expect($row->result['estimated'])->toBeFalse();
});

it('creates a month once using current recurring settings and empty one-off and statutory inputs', function () {
    [$admin] = seedPayroll();
    $this->actingAs($admin);

    $this->post(route('payroll.store'), ['month' => '2026-12'])->assertRedirect();
    $this->post(route('payroll.store'), ['month' => '2026-12'])->assertRedirect();

    $runs = PayrollRun::query()->where('period', '2026-12-01')->get();
    expect($runs)->toHaveCount(1);
    $rows = $runs->sole()->rows;
    expect($rows)->toHaveCount(4);
    expect($rows[0]->inputs['recurring'][0]['label'])->toBe('Transport allowance');
    foreach ($rows as $row) {
        expect($row->inputs['adjustments'])->toBe([]);
        expect($row->inputs['statutory'])->toBe([]);
    }
});

it('exports stable finalised amounts and leading zeros without reflecting later employee edits', function () {
    [$admin, $prior, $draft] = seedPayroll();
    $this->actingAs($admin);
    resolvePayrollJoiner($draft);
    $this->post(route('payroll.finalise', $draft), ['revision' => $draft->refresh()->revision, 'comparison_id' => $prior->id])
        ->assertSessionHasNoErrors();

    $row = $draft->rows()->firstOrFail();
    $employee = PayrollEmployee::query()->findOrFail($row->payroll_employee_id);
    $employee->update(['name' => 'Changed in directory', 'salary_sen' => 6_500_000, 'beneficiary' => null]);
    PayrollEmployer::query()->findOrFail(PayrollEmployer::ID)->update(['name' => 'A new company name']);

    $this->get(route('payroll.payslip', [$draft, $row]))->assertInertia(fn ($page) => $page
        ->component('payroll/payslip')->where('employee.name', 'Nur Aina Salleh')
        ->where('employer.name', 'Pandan Studio Sdn. Bhd.')->where('result.net', 357_795));
    $batch = $this->get(route('payroll.payment', $draft))->assertOk()->inertiaProps('batch');
    expect($batch['total'])->toBe(1_201_180);
    expect($batch['recipients'][0]['account'])->toBe('•••• 0001');
    expect($batch['recipients'][0])->not->toHaveKey('beneficiary');
    expect($row->getRawOriginal('beneficiary'))->not->toContain('000012340001', 'DEMO-0001');

    $data = ['payment_date' => '2026-11-30', 'batch_version' => $batch['version']];
    $first = $this->post(route('payroll.download', $draft), $data)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    $second = $this->post(route('payroll.download', $draft), $data)->assertOk();
    expect($first->getContent())->toBe($second->getContent());
    $lines = payrollCsvRows($first->getContent());
    expect(array_column($lines, 6, 2))->toBe(['EMP-001' => '3577.95', 'EMP-002' => '4027.95', 'EMP-003' => '2977.95', 'EMP-004' => '1427.95']);
    expect($lines['EMP-001'][5])->toBe('000012340001');
    $this->assertDatabaseCount('payroll_runs', 2);
    $this->assertDatabaseCount('payroll_export_events', 2);
    $this->assertDatabaseHas('payroll_export_events', ['actor_id' => $admin->id, 'checksum' => hash('sha256', $first->getContent())]);
});

it('blocks missing beneficiary details, excludes zero pay, and audits snapshot corrections', function () {
    [$admin, $prior, $draft] = seedPayroll();
    $this->actingAs($admin);
    resolvePayrollJoiner($draft);
    $missing = $draft->rows()->firstOrFail();
    $zero = $draft->rows()->skip(1)->firstOrFail();
    $missing->update(['beneficiary' => null]);
    $this->patch(route('payroll.adjustments', [$draft, $zero]), [
        'revision' => $draft->refresh()->revision,
        'adjustments' => [['type' => 'deduction', 'label' => 'Fictional offset', 'amount' => '3827.95']],
    ])->assertSessionHasNoErrors();
    $this->patch(route('payroll.statutory', [$draft, $zero]), [
        'revision' => $draft->refresh()->revision,
        'statutory' => statutoryInput(),
    ])->assertSessionHasNoErrors();
    $this->post(route('payroll.finalise', $draft), ['revision' => $draft->refresh()->revision, 'comparison_id' => $prior->id])
        ->assertSessionHasNoErrors();

    $batch = $this->get(route('payroll.payment', $draft))->inertiaProps('batch');
    expect($batch['excluded'])->toBe(['Daniel Wong']);
    expect($batch['recipients'][0]['error'])->toContain('Missing beneficiary');
    $payment = ['payment_date' => '2026-11-30', 'batch_version' => $batch['version']];
    $this->post(route('payroll.download', $draft), $payment)->assertSessionHasErrors('batch');
    $this->assertDatabaseCount('payroll_export_events', 0);

    $beneficiary = ['name' => '=1+1', 'bank' => 'CIMB Bank', 'account' => '000012340000', 'id_type' => 'Demo', 'id_number' => '000009'];
    $this->post(route('payroll.correct', [$draft, $missing]), [
        ...$payment, 'beneficiary' => $beneficiary, 'reason' => 'Client confirmed replacement details.',
    ])->assertSessionHasNoErrors();
    expect($missing->refresh()->beneficiary)->toBeNull();
    $correction = DB::table('payroll_beneficiary_corrections')->sole();
    expect((int) $correction->actor_id)->toBe($admin->id);
    expect(Crypt::decrypt($correction->before))->toBeNull();
    expect(Crypt::decrypt($correction->after))->toEqual($beneficiary);

    $this->post(route('payroll.download', $draft), $payment)->assertSessionHasErrors('batch');
    $batch = $this->get(route('payroll.payment', $draft))->inertiaProps('batch');
    $export = $this->post(route('payroll.download', $draft), [...$payment, 'batch_version' => $batch['version']])->assertOk();
    $rows = payrollCsvRows($export->getContent());
    expect($rows)->toHaveCount(3)->not->toHaveKey('EMP-002');
    expect($rows['EMP-001'][3])->toBe("'=1+1");
    expect($rows['EMP-001'][5])->toBe('000012340000');
});

it('rejects negative pay even when all amounts are verified', function () {
    [$admin, $prior, $draft] = seedPayroll();
    $this->actingAs($admin);
    resolvePayrollJoiner($draft);
    $row = $draft->rows()->firstOrFail();
    $this->patch(route('payroll.adjustments', [$draft, $row]), [
        'revision' => $draft->refresh()->revision,
        'adjustments' => [['type' => 'deduction', 'label' => 'Fictional excess', 'amount' => '4500.00']],
    ])->assertSessionHasNoErrors();
    $this->patch(route('payroll.statutory', [$draft, $row]), [
        'revision' => $draft->refresh()->revision, 'statutory' => statutoryInput(),
    ])->assertSessionHasNoErrors();
    $this->post(route('payroll.finalise', $draft), ['revision' => $draft->refresh()->revision, 'comparison_id' => $prior->id])
        ->assertSessionHasErrors('run');
    expect($draft->refresh()->finalised_at)->toBeNull();
});

it('serializes simultaneous finalisation attempts against the same sqlite run', function () {
    $file = tempnam(sys_get_temp_dir(), 'payroll-race-');
    $release = $file.'.release';
    $env = [
        'APP_ENV' => 'testing',
        'APP_KEY' => config('app.key'),
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => $file,
        'DB_URL' => false,
    ];
    $boot = <<<'CODE'
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $payroll = $app->make(App\Actions\Payroll\ManagePayroll::class);
        CODE;
    $setup = new Process([PHP_BINARY, '-r', $boot.<<<'CODE'

        Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        App\Models\PayrollEmployer::factory()->create(['id' => 1]);
        App\Models\PayrollEmployee::factory()->create(['payroll_employer_id' => 1]);
        $run = $payroll->create('2026-11-01');
        $payroll->revise($run->id, $run->rows()->sole()->id, 0, fn ($inputs) =>
            [...$inputs, 'statutory' => Database\Seeders\PayrollDemoSeeder::fictionalStatutory(1)]
        );
        CODE], base_path(), $env, timeout: 15);

    $finalise = <<<'CODE'

        $run = $payroll->finalise(1, 1, (int) $argv[1], null);
        echo $run->finalised_by.':'.$run->finalised_at->toISOString();
        CODE;
    $first = new Process([PHP_BINARY, '-r', $boot.<<<'CODE'

        $payroll->locked(function () use ($payroll, $argv) {
            echo "locked\n";
            $deadline = microtime(true) + 8;
            while (! is_file($argv[2]) && microtime(true) < $deadline) {
                usleep(20_000);
            }
        CODE.$finalise.'
        });', '11', $release], base_path(), $env, timeout: 15);
    $second = new Process([PHP_BINARY, '-r', $boot.<<<'CODE'

        echo "started\n";
        CODE.$finalise, '12'], base_path(), $env, timeout: 15);

    try {
        $setup->mustRun();
        $first->start();
        expect($first->waitUntil(fn ($type, $output): bool => str_contains($output, 'locked')))->toBeTrue();
        $second->start();
        expect($second->waitUntil(fn ($type, $output): bool => str_contains($output, 'started')))->toBeTrue();
        touch($release);
        expect($first->wait())->toBe(0);
        expect($second->wait())->toBe(0);

        $firstResult = trim(explode("\n", $first->getOutput())[1]);
        $secondResult = trim(explode("\n", $second->getOutput())[1]);
        expect($firstResult)->toStartWith('11:')->toBe($secondResult);
    } finally {
        $first->stop();
        $second->stop();
        foreach ([$release, $file, $file.'-wal', $file.'-shm', $file.'-journal'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
});

it('maintains employee settings privately and rolls back worksheet creation for an empty month', function () {
    [$admin] = seedPayroll();
    $this->actingAs($admin);
    $this->post(route('payroll.store'), ['month' => '2020-01'])->assertSessionHasErrors('month');
    $this->assertDatabaseMissing('payroll_runs', ['period' => '2020-01-01']);

    $data = [
        'code' => 'EMP-005',
        'name' => 'Mira Liew',
        'starts_on' => '2026-12-01',
        'ends_on' => null,
        'salary' => '4200.10',
        'recurring' => [['type' => 'addition', 'label' => 'Travel allowance', 'amount' => '40.01']],
        'beneficiary' => [
            'name' => 'Mira Liew',
            'bank' => 'Public Bank',
            'account' => '001234567890',
            'id_type' => 'Demo ID',
            'id_number' => '0000123',
        ],
    ];
    $wrongAccount = [...$data['beneficiary'], 'account' => 'ABC'];
    $this->post(route('payroll.employees.store'), [...$data, 'beneficiary' => $wrongAccount])
        ->assertSessionHasErrors('beneficiary.account')->assertSessionMissing('_old_input.beneficiary');

    $this->post(route('payroll.employees.store'), $data)->assertSessionHasNoErrors();
    $employee = PayrollEmployee::query()->where('code', 'EMP-005')->sole();
    expect($employee->getRawOriginal('beneficiary'))->not->toContain('001234567890', '0000123');
    $this->get(route('payroll.directory'))->assertInertia(fn ($page) => $page
        ->component('payroll/directory')->where('employees.4.account', '•••• 7890')->missing('employees.4.beneficiary'));

    $this->put(route('payroll.employees.update', $employee), [...$data, 'salary' => '4400.12', 'beneficiary' => null])
        ->assertSessionHasNoErrors();
    expect($employee->refresh()->beneficiary['id_number'])->toBe('0000123');

    $this->post(route('payroll.store'), ['month' => '2026-12'])->assertSessionHasNoErrors();
    $run = PayrollRun::query()->where('period', '2026-12-01')->sole();
    $inputs = $run->rows()->where('payroll_employee_id', $employee->id)->sole()->inputs;
    expect($inputs['salary_sen'])->toBe(440_012);
    expect($inputs['recurring'][0]['sen'])->toBe(4_001);
});
