<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_payroll_admin')->default(false);
        });

        Schema::create('payroll_employers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('work_location');
            $table->string('jurisdiction');
            $table->timestamps();
        });

        Schema::create('payroll_employees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_employer_id')->constrained();
            $table->string('code');
            $table->string('name');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->unsignedBigInteger('salary_sen');
            $table->json('recurring');
            $table->text('beneficiary')->nullable();
            $table->timestamps();
            $table->unique(['payroll_employer_id', 'code']);
        });

        Schema::create('payroll_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_employer_id')->constrained();
            $table->date('period');
            $table->unsignedInteger('revision')->default(0);
            $table->timestamp('finalised_at')->nullable();
            $table->unsignedBigInteger('finalised_by')->nullable();
            $table->json('employer_snapshot')->nullable();
            $table->json('comparison_snapshot')->nullable();
            $table->timestamps();
            $table->unique(['payroll_employer_id', 'period']);
        });

        Schema::create('payroll_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained();
            $table->foreignId('payroll_employee_id')->constrained();
            $table->json('inputs');
            $table->text('beneficiary')->nullable();
            $table->json('result')->nullable();
            $table->timestamps();
            $table->unique(['payroll_run_id', 'payroll_employee_id']);
        });

        Schema::create('payroll_beneficiary_corrections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_row_id')->constrained();
            $table->unsignedBigInteger('actor_id');
            $table->string('reason', 500);
            $table->text('before');
            $table->text('after');
            $table->timestamp('created_at');
        });

        Schema::create('payroll_export_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained();
            $table->unsignedBigInteger('actor_id');
            $table->date('payment_date');
            $table->string('format');
            $table->char('checksum', 64);
            $table->json('beneficiary_revisions');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_export_events');
        Schema::dropIfExists('payroll_beneficiary_corrections');
        Schema::dropIfExists('payroll_rows');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('payroll_employees');
        Schema::dropIfExists('payroll_employers');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_payroll_admin');
        });
    }
};
