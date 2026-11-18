import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import {
    money,
    monthName,
    PayTotals,
    Workspace,
} from '@/components/payroll/workspace';
import type { Employer, Result } from '@/components/payroll/workspace';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/payroll';

const sections = [
    ['earnings', 'Earnings'],
    ['reimbursements', 'Reimbursements'],
    ['deductions', 'Employee deductions'],
    ['contributions', 'Employer contributions'],
] as const;

export default function Payslip({
    employer,
    employee,
    month,
    result,
}: {
    employer: Employer;
    employee: { name: string; code: string };
    month: string;
    result: Result;
}) {
    return (
        <Workspace employer={employer} section="Payslip">
            <Head title={`Payslip · ${employee.name} · ${monthName(month)}`} />
            <div className="mx-auto max-w-3xl">
                <div className="mb-4 flex flex-wrap items-center justify-between gap-2 print:hidden">
                    <Link
                        className="inline-flex items-center gap-2 text-sm font-medium text-primary hover:underline"
                        href={index({ query: { month } })}
                    >
                        <ArrowLeft className="size-4" /> Back to worksheet
                    </Link>
                    <Button onClick={() => window.print()}>
                        <Printer data-icon="inline-start" /> Print payslip
                    </Button>
                </div>
                <article className="grid gap-5 rounded-xl border bg-card p-6 sm:p-8 print:rounded-none print:border-0 print:bg-white print:p-0 print:text-black">
                    <header className="flex flex-wrap justify-between gap-4 border-b pb-5">
                        <div>
                            <h1 className="text-3xl font-semibold tracking-tight">
                                Payslip
                            </h1>
                            <p className="pt-1 text-sm">
                                {monthName(month)} · MYR
                            </p>
                        </div>
                        <div className="text-sm">
                            <p className="font-semibold">{employer.name}</p>
                            {employer.work_location}, {employer.jurisdiction}
                        </div>
                    </header>
                    <div className="flex items-center justify-between gap-4 rounded-lg bg-accent p-4 print:bg-white print:text-black">
                        <div>
                            <h2 className="font-semibold">{employee.name}</h2>
                            <p className="text-sm">{employee.code}</p>
                        </div>
                        <div className="text-right">
                            <p className="text-sm">Net pay</p>
                            <p className="text-2xl font-semibold tabular-nums">
                                {money(result.net)}
                            </p>
                        </div>
                    </div>
                    {sections.map(([key, label]) => {
                        const items = result.items.filter(
                            (item) => item.bucket === key,
                        );
                        return (
                            items.length > 0 && (
                                <section
                                    key={key}
                                    className="grid gap-2 text-sm print:break-inside-avoid"
                                >
                                    <h3 className="border-b pb-1 font-semibold">
                                        {label}
                                    </h3>
                                    <dl className="grid gap-1.5">
                                        {items.map((item, i) => (
                                            <div
                                                key={i}
                                                className="flex justify-between gap-3"
                                            >
                                                <dt>{item.label}</dt>
                                                <dd className="tabular-nums">
                                                    {money(item.sen)}
                                                </dd>
                                            </div>
                                        ))}
                                    </dl>
                                </section>
                            )
                        );
                    })}
                    <div className="border-t pt-4 print:break-inside-avoid">
                        <PayTotals totals={result} />
                    </div>
                </article>
            </div>
        </Workspace>
    );
}
