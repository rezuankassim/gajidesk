import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowDownRight,
    ArrowRight,
    ArrowUpRight,
    CalendarDays,
    CheckCircle2,
    CircleAlert,
    FileText,
    ListFilter,
    LockKeyhole,
    Plus,
    Search,
    UsersRound,
    Wallet,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    decimal,
    Errors,
    Field,
    initials,
    money,
    monthName,
    Panel,
    PayTotals,
    selectClass,
    Workspace,
} from '@/components/payroll/workspace';
import type { Employer, Result } from '@/components/payroll/workspace';
import {
    adjustments,
    directory,
    finalise,
    index,
    payment,
    payslip,
    statutory,
    store,
} from '@/routes/payroll';

type Adjustment = {
    type: string;
    label: string;
    sen: number;
    reason: string | null;
};
type Review = {
    basis: 'verified' | 'not_applicable';
    sen: number;
    reason: string;
    stale?: boolean;
};
type Row = {
    id: number;
    inputs: {
        name: string;
        code: string;
        partial_month: boolean;
        starts_on: string;
        ends_on: string | null;
        salary_sen: number;
        adjustments: Adjustment[];
        statutory: Record<string, Review>;
        proration: { sen: number; reason: string } | null;
    };
    result: Result;
    delta: number | null;
    new_employee: boolean;
    changes: string[];
};
type Run = { id: number; revision: number; finalised_at: string | null };
type Props = {
    employer: Employer | null;
    month: string;
    months: { month: string; finalised: boolean }[] | null;
    run: Run | null;
    rows: Row[];
    previous: { id: number; month: string } | null;
    departures: string[];
    totals: {
        headcount: number;
        issues: number;
        unresolved: number;
        estimated: number;
        earnings: number;
        reimbursements: number;
        deductions: number;
        contributions: number;
        net: number;
    };
    statutoryLabels: Record<string, string>;
    adjustmentLabels: Record<string, string>;
};

export default function Worksheet({
    employer,
    month,
    months,
    run,
    rows,
    previous,
    departures,
    totals,
    statutoryLabels,
    adjustmentLabels,
}: Props) {
    const [search, setSearch] = useState('');
    const [issuesOnly, setIssuesOnly] = useState(false);
    const [selected, setSelected] = useState<number | null>(null);
    const [review, setReview] = useState(false);
    const [period, setPeriod] = useState(month);
    const createForm = useForm({ month });
    const finalizeForm = useForm({
        revision: run?.revision ?? 0,
        comparison_id: previous?.id ?? null,
    });
    const row = rows.find((employee) => employee.id === selected);
    const finalised = Boolean(run?.finalised_at);
    const reviewedAmount = (employee: Row, side: 'employer' | 'employee') =>
        Object.keys(statutoryLabels)
            .filter((key) => key.endsWith('_' + side))
            .every((key) => employee.inputs.statutory[key])
            ? employee.result[
                  side === 'employee' ? 'deductions' : 'contributions'
              ]
            : null;
    const visibleRows = rows.filter(
        (employee) =>
            (!issuesOnly || employee.result.issues.length > 0) &&
            `${employee.inputs.name} ${employee.inputs.code}`
                .toLowerCase()
                .includes(search.trim().toLowerCase()),
    );
    const totalNote = [
        totals.unresolved > 0
            ? `${totals.unresolved} awaiting an amount`
            : null,
        totals.estimated > 0 ? `${totals.estimated} provisional` : null,
    ]
        .filter(Boolean)
        .join(' · ');

    function openMonth(value: string) {
        if (value) router.get(index.url(), { month: value });
    }

    return (
        <Workspace employer={employer} section="Monthly payroll">
            <Head title="Monthly payroll" />
            <div className="payroll-heading mb-6 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <div className="mb-2 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                        <CalendarDays className="size-4" /> {monthName(month)}
                        {run && (
                            <Badge
                                variant={finalised ? 'secondary' : 'outline'}
                            >
                                {finalised ? 'Finalised' : 'Draft'}
                            </Badge>
                        )}
                    </div>
                    <h1 className="text-3xl font-semibold tracking-tight">
                        Monthly payroll
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {previous
                            ? `Compared with finalised payroll for ${monthName(previous.month)}.`
                            : 'Review monthly pay for salaried employees.'}
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Dialog>
                        <DialogTrigger asChild>
                            <Button variant="outline">
                                <CalendarDays data-icon="inline-start" /> Choose
                                month
                            </Button>
                        </DialogTrigger>
                        <DialogContent className="payroll-theme">
                            <DialogHeader>
                                <DialogTitle>Open a payroll month</DialogTitle>
                                <DialogDescription>
                                    Select a month to view or start its
                                    worksheet.
                                </DialogDescription>
                            </DialogHeader>
                            <form
                                className="flex items-end gap-2"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    openMonth(period);
                                }}
                            >
                                <div className="flex-1">
                                    <Field label="Payroll month">
                                        <Input
                                            type="month"
                                            required
                                            value={period}
                                            onChange={(event) =>
                                                setPeriod(event.target.value)
                                            }
                                        />
                                    </Field>
                                </div>
                                <Button type="submit">Open month</Button>
                            </form>
                            {(months?.length ?? 0) > 0 && (
                                <ul className="max-h-64 overflow-auto text-sm">
                                    {months?.map((entry) => (
                                        <li key={entry.month}>
                                            <Link
                                                href={index({
                                                    query: {
                                                        month: entry.month,
                                                    },
                                                })}
                                                className="flex justify-between gap-2 rounded-md p-2 hover:bg-muted"
                                            >
                                                {monthName(entry.month)}{' '}
                                                <Badge variant="outline">
                                                    {entry.finalised
                                                        ? 'Finalised'
                                                        : 'Draft'}
                                                </Badge>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </DialogContent>
                    </Dialog>
                    {run &&
                        (finalised ? (
                            <Button asChild>
                                <Link href={payment(run.id)}>
                                    Review payment{' '}
                                    <ArrowRight data-icon="inline-end" />
                                </Link>
                            </Button>
                        ) : (
                            <Dialog open={review} onOpenChange={setReview}>
                                <DialogTrigger asChild>
                                    <Button>
                                        <LockKeyhole data-icon="inline-start" />{' '}
                                        Review &amp; finalise
                                    </Button>
                                </DialogTrigger>
                                <DialogContent className="payroll-theme">
                                    <DialogHeader>
                                        <DialogTitle>
                                            Review {monthName(month)}
                                        </DialogTitle>
                                        <DialogDescription>
                                            {totals.headcount} employees · MYR
                                        </DialogDescription>
                                    </DialogHeader>
                                    <PayTotals totals={totals} />
                                    {totalNote && (
                                        <p className="text-sm text-muted-foreground">
                                            {totalNote}
                                        </p>
                                    )}
                                    <Errors errors={finalizeForm.errors} />
                                    {totals.issues > 0 && (
                                        <p className="text-sm text-destructive">
                                            Resolve every pay issue before
                                            finalising.
                                        </p>
                                    )}
                                    <Button
                                        disabled={
                                            totals.issues > 0 ||
                                            totals.headcount === 0 ||
                                            finalizeForm.processing
                                        }
                                        onClick={() => {
                                            finalizeForm.transform(() => ({
                                                revision: run.revision,
                                                comparison_id:
                                                    previous?.id ?? null,
                                            }));
                                            finalizeForm.post(
                                                finalise.url(run.id),
                                                {
                                                    preserveScroll: true,
                                                    onSuccess: () =>
                                                        setReview(false),
                                                },
                                            );
                                        }}
                                    >
                                        Finalise payroll
                                    </Button>
                                </DialogContent>
                            </Dialog>
                        ))}
                </div>
            </div>

            {!run || !employer ? (
                <Panel
                    title={
                        employer ? 'Start this month' : 'Set up the employer'
                    }
                >
                    <div className="grid justify-items-start gap-3 p-5">
                        <p className="text-sm text-muted-foreground">
                            {employer
                                ? 'Create the worksheet using employee salaries and recurring items.'
                                : 'Add the company and employees before you create a worksheet.'}
                        </p>
                        {employer ? (
                            <Button
                                disabled={createForm.processing}
                                onClick={() => {
                                    createForm.transform(() => ({ month }));
                                    createForm.post(store.url());
                                }}
                            >
                                <Plus data-icon="inline-start" /> Create
                                worksheet
                            </Button>
                        ) : (
                            <Button asChild>
                                <Link href={directory()}>
                                    Open employer setup
                                </Link>
                            </Button>
                        )}
                        <Errors errors={createForm.errors} />
                    </div>
                </Panel>
            ) : (
                <>
                    <div className="mb-6 grid gap-3 sm:grid-cols-3">
                        {[
                            {
                                icon: UsersRound,
                                title: 'Employees',
                                tone: 'bg-muted text-muted-foreground',
                                value: totals.headcount,
                                note: 'Monthly salaried employees',
                            },
                            {
                                icon: Wallet,
                                title: totalNote
                                    ? 'Provisional net pay'
                                    : 'Net payable',
                                tone: 'bg-accent text-accent-foreground',
                                value: money(totals.net),
                                note:
                                    totalNote || 'MYR · Employee take-home pay',
                            },
                            {
                                icon: totals.issues
                                    ? CircleAlert
                                    : CheckCircle2,
                                title: 'Needs review',
                                tone: totals.issues
                                    ? 'payroll-warning'
                                    : 'payroll-success',
                                value: totals.issues,
                                note: totals.issues
                                    ? 'Open flagged rows to resolve pay'
                                    : 'All employees reviewed',
                            },
                        ].map(({ icon: Icon, title, value, note, tone }) => (
                            <Card className="gap-3 py-4" key={title}>
                                <CardHeader className="px-5">
                                    <CardTitle className="flex items-center gap-2.5 text-sm font-medium">
                                        <span
                                            className={cn(
                                                'grid size-8 place-items-center rounded-md',
                                                tone,
                                            )}
                                        >
                                            <Icon
                                                aria-hidden="true"
                                                className="size-4"
                                            />
                                        </span>
                                        {title}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="px-5">
                                    <p className="text-3xl leading-tight font-semibold tracking-tight tabular-nums">
                                        {value}
                                    </p>
                                    <CardDescription className="mt-1 text-xs">
                                        {note}
                                    </CardDescription>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                    <Panel
                        title="Payroll worksheet"
                        action={
                            <div className="flex flex-wrap items-center gap-2">
                                <div className="relative">
                                    <Search className="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                                    <Input
                                        className="w-44 pl-8"
                                        placeholder="Find employee"
                                        aria-label="Find employee"
                                        value={search}
                                        onChange={(event) =>
                                            setSearch(event.target.value)
                                        }
                                    />
                                </div>
                                <Button
                                    variant={
                                        issuesOnly ? 'secondary' : 'outline'
                                    }
                                    aria-pressed={issuesOnly}
                                    onClick={() => setIssuesOnly(!issuesOnly)}
                                >
                                    <ListFilter data-icon="inline-start" />{' '}
                                    Needs review
                                </Button>
                            </div>
                        }
                    >
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[950px] text-left text-sm">
                                <thead className="bg-muted/70 text-xs text-muted-foreground">
                                    <tr>
                                        {[
                                            'Employee',
                                            'Basic pay',
                                            'Additions',
                                            'Reimbursements',
                                            'Deductions',
                                            'Net pay',
                                            'Monthly change',
                                            'Review',
                                        ].map((label, i) => (
                                            <th
                                                key={label}
                                                scope="col"
                                                className={`px-3 py-3 font-medium ${i === 0 ? 'pl-5' : i < 6 ? 'text-right' : ''}`}
                                            >
                                                {label}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {visibleRows.map((employee) => (
                                        <tr
                                            key={employee.id}
                                            className="transition-colors hover:bg-muted/40"
                                        >
                                            <td className="px-3 py-3.5 pl-5">
                                                <button
                                                    className="flex items-center gap-3 text-left hover:text-primary hover:underline"
                                                    onClick={() =>
                                                        setSelected(employee.id)
                                                    }
                                                >
                                                    <Avatar className="size-8">
                                                        <AvatarFallback className="bg-muted text-xs font-semibold text-muted-foreground">
                                                            {initials(
                                                                employee.inputs
                                                                    .name,
                                                            )}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <span className="min-w-32">
                                                        <span className="block font-semibold">
                                                            {
                                                                employee.inputs
                                                                    .name
                                                            }
                                                        </span>
                                                        <span className="block text-xs text-muted-foreground">
                                                            {
                                                                employee.inputs
                                                                    .code
                                                            }
                                                        </span>
                                                    </span>
                                                </button>
                                            </td>
                                            {[
                                                employee.result.basic,
                                                employee.result.additions,
                                                employee.result.reimbursements,
                                                reviewedAmount(
                                                    employee,
                                                    'employee',
                                                ),
                                                employee.result.net,
                                            ].map((amount, i) => (
                                                <td
                                                    key={i}
                                                    className={`px-3 py-3.5 text-right whitespace-nowrap tabular-nums ${i === 4 ? 'font-semibold' : 'text-muted-foreground'}`}
                                                >
                                                    {money(amount)}
                                                    {i === 4 &&
                                                        employee.result
                                                            .estimated && (
                                                            <span className="block text-[11px] font-normal text-muted-foreground">
                                                                Provisional
                                                            </span>
                                                        )}
                                                    {i === 4 &&
                                                        amount === 0 && (
                                                            <span className="block text-[11px] font-normal text-muted-foreground">
                                                                Excluded from
                                                                payment
                                                            </span>
                                                        )}
                                                </td>
                                            ))}
                                            <td className="px-3 py-3.5 whitespace-nowrap">
                                                <Change row={employee} />
                                            </td>
                                            <td className="px-3 py-3.5 pr-5 whitespace-nowrap">
                                                {employee.result.issues
                                                    .length ? (
                                                    <button
                                                        onClick={() =>
                                                            setSelected(
                                                                employee.id,
                                                            )
                                                        }
                                                        aria-label={`Review ${employee.inputs.name}`}
                                                    >
                                                        <Badge
                                                            className="payroll-warning"
                                                            variant="outline"
                                                        >
                                                            Needs review
                                                        </Badge>
                                                    </button>
                                                ) : finalised ? (
                                                    <Link
                                                        className="inline-flex items-center gap-1.5 text-primary hover:underline"
                                                        href={payslip([
                                                            run.id,
                                                            employee.id,
                                                        ])}
                                                    >
                                                        <FileText className="size-3.5" />{' '}
                                                        Payslip
                                                    </Link>
                                                ) : (
                                                    <Badge
                                                        className="payroll-success"
                                                        variant="outline"
                                                    >
                                                        Ready
                                                    </Badge>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                    {visibleRows.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={8}
                                                className="p-5 text-center text-muted-foreground"
                                            >
                                                {rows.length === 0
                                                    ? 'No employees were active this month.'
                                                    : 'No employees match your filters.'}
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        <div className="border-t px-5 py-3 text-xs text-muted-foreground">
                            Showing {visibleRows.length} of {totals.headcount}{' '}
                            employees
                        </div>
                    </Panel>
                    <div className="mt-6 grid gap-5 lg:grid-cols-2">
                        <Panel
                            title="Payroll totals"
                            action={
                                totalNote ? (
                                    <Badge variant="outline">Provisional</Badge>
                                ) : undefined
                            }
                        >
                            <div className="p-5">
                                <PayTotals totals={totals} />
                            </div>
                        </Panel>
                        <Panel
                            title={
                                finalised ? 'Payment handoff' : 'Review queue'
                            }
                        >
                            <div className="grid gap-3 p-5 text-sm">
                                {finalised ? (
                                    <>
                                        <p className="text-muted-foreground">
                                            Review the recipient list and
                                            download the payment preview.
                                        </p>
                                        <Link
                                            href={payment(run.id)}
                                            className="inline-flex items-center gap-2 font-medium text-primary hover:underline"
                                        >
                                            Open payment review{' '}
                                            <ArrowRight className="size-4" />
                                        </Link>
                                    </>
                                ) : (
                                    rows
                                        .filter(
                                            (employee) =>
                                                employee.result.issues.length >
                                                0,
                                        )
                                        .map((employee) => (
                                            <button
                                                key={employee.id}
                                                onClick={() =>
                                                    setSelected(employee.id)
                                                }
                                                className="flex items-center gap-3 rounded-lg border p-3 text-left hover:bg-muted"
                                            >
                                                <CircleAlert className="size-4 shrink-0 text-primary" />
                                                <span className="flex-1">
                                                    <strong className="block font-medium">
                                                        {employee.inputs.name}
                                                    </strong>
                                                    <span className="text-xs text-muted-foreground">
                                                        {
                                                            employee.result
                                                                .issues[0]
                                                        }
                                                    </span>
                                                </span>
                                                <ArrowRight className="size-4 shrink-0 text-muted-foreground" />
                                            </button>
                                        ))
                                )}
                                {!finalised && totals.issues === 0 && (
                                    <p className="text-muted-foreground">
                                        All rows are ready for final review.
                                    </p>
                                )}
                                {departures.length > 0 && (
                                    <p className="text-muted-foreground">
                                        Present last run only ·{' '}
                                        {departures.join(', ')}
                                    </p>
                                )}
                            </div>
                        </Panel>
                    </div>
                </>
            )}

            <Sheet
                open={Boolean(row)}
                onOpenChange={(opened) => {
                    if (!opened) setSelected(null);
                }}
            >
                {row && run && (
                    <SheetContent className="payroll-theme w-full gap-0 overflow-y-auto sm:max-w-xl">
                        <div className="border-b p-5 pr-12">
                            <SheetTitle className="text-xl">
                                {row.inputs.name}
                            </SheetTitle>
                            <SheetDescription>
                                {row.inputs.code} · {monthName(month)}
                                {row.result.estimated
                                    ? ' · Provisional net pay'
                                    : ''}
                            </SheetDescription>
                        </div>
                        <div className="grid gap-5 p-5">
                            <PayTotals
                                totals={{
                                    ...row.result,
                                    earnings:
                                        row.result.basic === null
                                            ? null
                                            : row.result.earnings,
                                    deductions: reviewedAmount(row, 'employee'),
                                    contributions: reviewedAmount(
                                        row,
                                        'employer',
                                    ),
                                }}
                            />
                            <section className="grid gap-2">
                                <h3 className="text-sm font-semibold">
                                    Pay breakdown
                                </h3>
                                <ul className="grid gap-2 text-sm">
                                    {row.result.items.map((item, i) => (
                                        <li
                                            key={i}
                                            className="flex justify-between gap-3"
                                        >
                                            <span>
                                                {item.label}
                                                {item.reason && (
                                                    <span className="block text-xs text-muted-foreground">
                                                        {item.reason}
                                                    </span>
                                                )}
                                            </span>
                                            <span className="whitespace-nowrap tabular-nums">
                                                {money(
                                                    item.bucket === 'deductions'
                                                        ? -item.sen
                                                        : item.sen,
                                                )}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                            <section className="grid gap-2 border-t pt-4 text-sm">
                                <h3 className="font-semibold">What changed</h3>
                                {row.changes.length ? (
                                    <ul className="grid gap-1 text-muted-foreground">
                                        {row.changes.map((change, i) => (
                                            <li key={i}>{change}</li>
                                        ))}
                                    </ul>
                                ) : (
                                    <p className="text-muted-foreground">
                                        {row.new_employee
                                            ? 'New to payroll this month.'
                                            : row.delta === 0
                                              ? 'No pay changes since the previous finalised run.'
                                              : 'A comparison is unavailable until pay is complete and an earlier run exists.'}
                                    </p>
                                )}
                            </section>
                            {row.result.issues.length > 0 && (
                                <ul className="payroll-warning grid gap-1 rounded-lg border p-3 text-sm">
                                    {row.result.issues.map((issue) => (
                                        <li key={issue}>{issue}</li>
                                    ))}
                                </ul>
                            )}
                            {finalised ? (
                                <Button asChild>
                                    <Link href={payslip([run.id, row.id])}>
                                        <FileText data-icon="inline-start" />{' '}
                                        Open payslip
                                    </Link>
                                </Button>
                            ) : (
                                <>
                                    <AdjustmentEditor
                                        key={`pay-${row.id}`}
                                        row={row}
                                        run={run}
                                        labels={adjustmentLabels}
                                    />
                                    <StatutoryEditor
                                        key={`review-${row.id}`}
                                        row={row}
                                        run={run}
                                        labels={statutoryLabels}
                                    />
                                </>
                            )}
                        </div>
                    </SheetContent>
                )}
            </Sheet>
        </Workspace>
    );
}

function Change({ row }: { row: Row }) {
    if (row.new_employee)
        return <Badge variant="secondary">New to payroll</Badge>;
    if (row.delta === null)
        return <span className="text-muted-foreground">—</span>;
    if (row.delta === 0)
        return <span className="text-muted-foreground">Unchanged</span>;
    return (
        <span
            className={cn(
                'inline-flex w-fit items-center gap-0.5 rounded-md px-1.5 py-0.5 text-xs font-semibold tabular-nums',
                row.delta > 0 ? 'payroll-success' : 'text-destructive',
            )}
        >
            {row.delta > 0 ? (
                <ArrowUpRight className="size-3.5" />
            ) : (
                <ArrowDownRight className="size-3.5" />
            )}
            {row.delta > 0 && '+'}
            {money(row.delta)}
        </span>
    );
}

function AdjustmentEditor({
    row,
    run,
    labels,
}: {
    row: Row;
    run: Run;
    labels: Record<string, string>;
}) {
    const form = useForm({
        revision: run.revision,
        adjustments: row.inputs.adjustments.map((item) => ({
            type: item.type,
            label: item.label,
            amount: decimal(item.sen),
            reason: item.reason ?? '',
        })),
        proration_amount: row.inputs.proration
            ? decimal(row.inputs.proration.sen)
            : '',
        proration_reason: row.inputs.proration?.reason ?? '',
    });
    const [saved, setSaved] = useState(false);
    return (
        <form
            className="grid gap-3 border-t pt-4"
            onSubmit={(event) => {
                event.preventDefault();
                form.transform((data) => ({ ...data, revision: run.revision }));
                form.patch(adjustments.url([run.id, row.id]), {
                    preserveScroll: true,
                    onSuccess: () => setSaved(true),
                });
            }}
        >
            <h3 className="text-sm font-semibold">Monthly adjustments</h3>
            {row.inputs.partial_month && (
                <div className="grid gap-3 rounded-lg border p-3">
                    <div className="text-sm">
                        <strong>Partial month</strong>
                        <p className="text-xs text-muted-foreground">
                            Employment: {row.inputs.starts_on}
                            {row.inputs.ends_on
                                ? ` to ${row.inputs.ends_on}`
                                : ''}{' '}
                            · Monthly basic {money(row.inputs.salary_sen)}
                        </p>
                    </div>
                    <Field label="Verified prorated basic pay (RM)">
                        <Input
                            inputMode="decimal"
                            value={form.data.proration_amount}
                            onChange={(event) =>
                                form.setData(
                                    'proration_amount',
                                    event.target.value,
                                )
                            }
                        />
                    </Field>
                    <Field label="Proration explanation">
                        <Input
                            value={form.data.proration_reason}
                            onChange={(event) =>
                                form.setData(
                                    'proration_reason',
                                    event.target.value,
                                )
                            }
                        />
                    </Field>
                </div>
            )}
            {form.data.adjustments.map((item, i) => {
                const change = (key: keyof typeof item, value: string) =>
                    form.setData(
                        'adjustments',
                        form.data.adjustments.map((other, index) =>
                            index === i ? { ...other, [key]: value } : other,
                        ),
                    );
                return (
                    <div className="grid gap-2 rounded-lg border p-3" key={i}>
                        <div className="grid grid-cols-[1fr_1fr_auto] items-end gap-2">
                            <Field label="Type">
                                <select
                                    className={selectClass}
                                    value={item.type}
                                    onChange={(event) =>
                                        change('type', event.target.value)
                                    }
                                >
                                    {Object.entries(labels).map(
                                        ([key, label]) => (
                                            <option key={key} value={key}>
                                                {label}
                                            </option>
                                        ),
                                    )}
                                </select>
                            </Field>
                            <Field label="Amount (RM)">
                                <Input
                                    required
                                    inputMode="decimal"
                                    value={item.amount}
                                    onChange={(event) =>
                                        change('amount', event.target.value)
                                    }
                                />
                            </Field>
                            <Button
                                type="button"
                                aria-label={`Remove adjustment ${i + 1}`}
                                variant="ghost"
                                size="icon"
                                onClick={() =>
                                    form.setData(
                                        'adjustments',
                                        form.data.adjustments.filter(
                                            (_, index) => index !== i,
                                        ),
                                    )
                                }
                            >
                                <X className="size-4" />
                            </Button>
                        </div>
                        {(item.type === 'addition' ||
                            item.type === 'deduction' ||
                            item.label) && (
                            <Field label="Adjustment name">
                                <Input
                                    required
                                    value={item.label}
                                    onChange={(event) =>
                                        change('label', event.target.value)
                                    }
                                />
                            </Field>
                        )}
                        <Field
                            label={
                                item.type === 'overtime' ||
                                item.type === 'unpaid_leave'
                                    ? 'Verification reason'
                                    : 'Note (optional)'
                            }
                        >
                            <Input
                                required={
                                    item.type === 'overtime' ||
                                    item.type === 'unpaid_leave'
                                }
                                value={item.reason}
                                onChange={(event) =>
                                    change('reason', event.target.value)
                                }
                            />
                        </Field>
                    </div>
                );
            })}
            <Errors errors={form.errors} />
            <div className="flex flex-wrap items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    onClick={() =>
                        form.setData('adjustments', [
                            ...form.data.adjustments,
                            {
                                type: 'bonus',
                                label: '',
                                amount: '',
                                reason: '',
                            },
                        ])
                    }
                >
                    <Plus data-icon="inline-start" /> Add adjustment
                </Button>
                <Button type="submit" disabled={form.processing}>
                    Save pay changes
                </Button>
                {saved && (
                    <span
                        role="status"
                        className="text-xs text-muted-foreground"
                    >
                        Worksheet updated
                    </span>
                )}
            </div>
        </form>
    );
}

function StatutoryEditor({
    row,
    run,
    labels,
}: {
    row: Row;
    run: Run;
    labels: Record<string, string>;
}) {
    const form = useForm({
        revision: run.revision,
        statutory: Object.fromEntries(
            Object.keys(labels).map((key) => {
                const existing = row.inputs.statutory[key];
                return [
                    key,
                    {
                        basis: String(existing?.basis ?? ''),
                        amount:
                            existing?.basis === 'verified'
                                ? decimal(existing.sen)
                                : '',
                        reason: existing?.reason ?? '',
                    },
                ];
            }),
        ),
    });
    const [saved, setSaved] = useState(false);
    return (
        <form
            className="grid gap-3 border-t pt-4"
            onSubmit={(event) => {
                event.preventDefault();
                form.transform((data) => ({
                    revision: run.revision,
                    statutory: Object.fromEntries(
                        Object.entries(data.statutory).filter(
                            ([, entry]) => entry.basis,
                        ),
                    ),
                }));
                form.patch(statutory.url([run.id, row.id]), {
                    preserveScroll: true,
                    onSuccess: () => setSaved(true),
                });
            }}
        >
            <h3 className="text-sm font-semibold">Statutory verification</h3>
            <p className="text-xs text-muted-foreground">
                Enter amounts verified using the official schedules or record
                why a component is inapplicable. Employer contributions are
                separate from take-home pay.
            </p>
            {Object.entries(labels).map(([key, label]) => {
                const entry = form.data.statutory[key];
                const change = (patch: Partial<typeof entry>) =>
                    form.setData('statutory', {
                        ...form.data.statutory,
                        [key]: { ...entry, ...patch },
                    });
                return (
                    <fieldset
                        key={key}
                        className="grid gap-2 rounded-lg border px-3 pb-3"
                    >
                        <legend className="px-1 text-sm font-medium">
                            {label}
                            {row.inputs.statutory[key]?.stale
                                ? ' · Recheck'
                                : ''}
                        </legend>
                        <div className="grid grid-cols-[1.4fr_1fr] gap-2">
                            <Field label="Basis">
                                <select
                                    className={selectClass}
                                    value={entry.basis}
                                    onChange={(event) =>
                                        change({
                                            basis: event.target.value,
                                            amount:
                                                event.target.value ===
                                                'verified'
                                                    ? entry.amount
                                                    : '',
                                        })
                                    }
                                >
                                    <option value="">Needs review</option>
                                    <option value="verified">
                                        Manually verified
                                    </option>
                                    <option value="not_applicable">
                                        Not applicable
                                    </option>
                                </select>
                            </Field>
                            <Field label="Amount (RM)">
                                <Input
                                    disabled={entry.basis !== 'verified'}
                                    required={entry.basis === 'verified'}
                                    inputMode="decimal"
                                    value={entry.amount}
                                    onChange={(event) =>
                                        change({ amount: event.target.value })
                                    }
                                />
                            </Field>
                        </div>
                        <Field label="Source or reason">
                            <Input
                                disabled={!entry.basis}
                                required={Boolean(entry.basis)}
                                value={entry.reason}
                                onChange={(event) =>
                                    change({ reason: event.target.value })
                                }
                            />
                        </Field>
                    </fieldset>
                );
            })}
            <Errors errors={form.errors} />
            <div className="flex items-center gap-2">
                <Button
                    type="submit"
                    disabled={
                        form.processing ||
                        !Object.values(form.data.statutory).some(
                            (entry) => entry.basis,
                        )
                    }
                >
                    Confirm statutory review
                </Button>
                {saved && (
                    <span
                        role="status"
                        className="text-xs text-muted-foreground"
                    >
                        Review saved
                    </span>
                )}
            </div>
        </form>
    );
}
