import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, CircleAlert, Download, Landmark } from 'lucide-react';
import { useState } from 'react';
import {
    BeneficiaryFields,
    emptyBeneficiary,
} from '@/components/payroll/beneficiary-fields';
import {
    Errors,
    Field,
    money,
    monthName,
    Panel,
    Workspace,
} from '@/components/payroll/workspace';
import type { Employer } from '@/components/payroll/workspace';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import { correct, download, index, payment } from '@/routes/payroll';

type Recipient = {
    row_id: number;
    name: string;
    code: string;
    net: number;
    error: string | null;
    bank: string | null;
    account: string | null;
    revision: number | null;
};
type Batch = {
    recipients: Recipient[];
    excluded: string[];
    total: number;
    version: string;
    limitExceeded: boolean;
};

export default function Payment({
    employer,
    run,
    month,
    paymentDate,
    batch,
    csrf,
}: {
    employer: Employer;
    run: number;
    month: string;
    paymentDate: string;
    batch: Batch;
    csrf: string;
}) {
    const [date, setDate] = useState(paymentDate);
    const [recipient, setRecipient] = useState<Recipient | null>(null);
    const { errors } = usePage().props;
    const invalid = batch.recipients.some((row) => row.error);

    return (
        <Workspace employer={employer} section="Payment review">
            <Head title="Payment review" />
            <Link
                className="mb-3 inline-flex items-center gap-2 text-sm font-medium text-primary hover:underline"
                href={index({ query: { month } })}
            >
                <ArrowLeft className="size-4" /> Back to worksheet
            </Link>
            <div className="mb-6">
                <h1 className="text-3xl font-semibold tracking-tight">
                    Payment review
                </h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    {monthName(month)} · Finalised payroll
                </p>
            </div>
            <div className="grid gap-5">
                <Alert>
                    <Landmark />
                    <AlertTitle>Maybank upload file unavailable</AlertTitle>
                    <AlertDescription>
                        The preview is for reconciliation. To enable bank
                        upload, provide the blank template from Maybank2u Biz
                        Maker access under Bulk Payment → Bulk Payment by
                        Entries &amp; File Upload → File Upload/Download.
                    </AlertDescription>
                </Alert>
                {batch.limitExceeded && (
                    <Alert variant="destructive">
                        <CircleAlert />
                        <AlertTitle>
                            Over the published Maybank file-upload limits
                        </AlertTitle>
                        <AlertDescription>
                            The bank lists up to 100 recipients and RM250,000
                            per file. The preview still shows all payable
                            employees.
                        </AlertDescription>
                    </Alert>
                )}
                <Errors errors={errors} />
                <Panel
                    title="Salary payment batch"
                    action={
                        <form
                            className="flex items-end gap-2"
                            onSubmit={(event) => {
                                event.preventDefault();
                                router.get(
                                    payment.url(run),
                                    { payment_date: date },
                                    { preserveScroll: true },
                                );
                            }}
                        >
                            <Field label="Payment date">
                                <Input
                                    required
                                    type="date"
                                    value={date}
                                    onChange={(event) =>
                                        setDate(event.target.value)
                                    }
                                />
                            </Field>
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={date === paymentDate}
                            >
                                Review date
                            </Button>
                        </form>
                    }
                >
                    <dl className="grid gap-4 border-b p-5 sm:grid-cols-3">
                        <div>
                            <dt className="text-sm text-muted-foreground">
                                Transfer total
                            </dt>
                            <dd className="text-2xl font-semibold tabular-nums">
                                {money(batch.total)}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-sm text-muted-foreground">
                                Payable recipients
                            </dt>
                            <dd className="text-2xl font-semibold">
                                {batch.recipients.length}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-sm text-muted-foreground">
                                Excluded for zero pay
                            </dt>
                            <dd className="text-2xl font-semibold">
                                {batch.excluded.length}
                            </dd>
                        </div>
                    </dl>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[680px] text-left text-sm">
                            <thead className="bg-muted/65 text-xs text-muted-foreground">
                                <tr>
                                    {[
                                        'Employee',
                                        'Beneficiary bank',
                                        'Account',
                                        'Amount',
                                        'Review',
                                    ].map((label) => (
                                        <th
                                            key={label}
                                            scope="col"
                                            className="px-5 py-3 font-medium"
                                        >
                                            {label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {batch.recipients.map((row) => (
                                    <tr key={row.row_id}>
                                        <td className="px-5 py-3">
                                            <strong className="font-medium">
                                                {row.name}
                                            </strong>
                                            <div className="text-xs text-muted-foreground">
                                                {row.code}
                                            </div>
                                        </td>
                                        <td className="px-5 py-3">
                                            {row.bank ?? '—'}
                                        </td>
                                        <td className="px-5 py-3 tabular-nums">
                                            {row.account ?? '—'}
                                        </td>
                                        <td className="px-5 py-3 font-semibold whitespace-nowrap tabular-nums">
                                            {money(row.net)}
                                        </td>
                                        <td className="px-5 py-3">
                                            {row.error && (
                                                <p className="text-sm text-destructive">
                                                    {row.error}
                                                </p>
                                            )}
                                            {row.revision && (
                                                <p className="text-xs text-muted-foreground">
                                                    Correction recorded
                                                </p>
                                            )}
                                            <button
                                                className="text-left text-sm font-medium text-primary hover:underline"
                                                onClick={() =>
                                                    setRecipient(row)
                                                }
                                            >
                                                Correct beneficiary
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {batch.excluded.length > 0 && (
                        <p className="border-t px-5 py-3 text-sm text-muted-foreground">
                            Zero pay · {batch.excluded.join(', ')}
                        </p>
                    )}
                    <div className="grid gap-2 border-t p-5">
                        {invalid && (
                            <p className="text-sm text-destructive">
                                Resolve the beneficiary errors before
                                downloading.
                            </p>
                        )}
                        {batch.recipients.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                There are no positive payments to export.
                            </p>
                        )}
                        <form
                            {...download.form(run)}
                            className="flex flex-wrap gap-2"
                        >
                            <input type="hidden" name="_token" value={csrf} />
                            <input
                                type="hidden"
                                name="payment_date"
                                value={paymentDate}
                            />
                            <input
                                type="hidden"
                                name="batch_version"
                                value={batch.version}
                            />
                            <Button
                                disabled={
                                    invalid ||
                                    batch.recipients.length === 0 ||
                                    date !== paymentDate
                                }
                                type="submit"
                            >
                                <Download data-icon="inline-start" /> Download
                                Payment preview CSV
                            </Button>
                            <Button disabled type="button" variant="outline">
                                Maybank upload file
                            </Button>
                        </form>
                    </div>
                </Panel>
            </div>
            <Sheet
                open={recipient !== null}
                onOpenChange={(open) => {
                    if (!open) setRecipient(null);
                }}
            >
                {recipient && (
                    <SheetContent className="payroll-theme w-full overflow-y-auto sm:max-w-xl">
                        <div className="border-b p-5 pr-12">
                            <SheetTitle>Correct beneficiary</SheetTitle>
                            <SheetDescription>
                                {recipient.name} ·{' '}
                                {recipient.account ?? 'No account recorded'}
                            </SheetDescription>
                        </div>
                        <CorrectionForm
                            run={run}
                            recipient={recipient}
                            date={paymentDate}
                            version={batch.version}
                            close={() => setRecipient(null)}
                        />
                    </SheetContent>
                )}
            </Sheet>
        </Workspace>
    );
}

function CorrectionForm({
    run,
    recipient,
    date,
    version,
    close,
}: {
    run: number;
    recipient: Recipient;
    date: string;
    version: string;
    close: () => void;
}) {
    const form = useForm({
        beneficiary: emptyBeneficiary,
        reason: '',
        payment_date: date,
        batch_version: version,
    });
    return (
        <form
            className="grid gap-4 px-5 pb-5"
            onSubmit={(event) => {
                event.preventDefault();
                form.transform((data) => ({
                    ...data,
                    payment_date: date,
                    batch_version: version,
                }));
                form.post(correct.url([run, recipient.row_id]), {
                    preserveScroll: true,
                    onSuccess: close,
                });
            }}
        >
            <p className="text-sm text-muted-foreground">
                Enter the full replacement details. An audit entry links the
                correction to this run. Previously downloaded files retain their
                original details.
            </p>
            <BeneficiaryFields
                value={form.data.beneficiary}
                onChange={(value) => form.setData('beneficiary', value)}
            />
            <Field label="Correction reason">
                <Input
                    required
                    value={form.data.reason}
                    onChange={(event) =>
                        form.setData('reason', event.target.value)
                    }
                />
            </Field>
            <Errors errors={form.errors} />
            <Button
                className="justify-self-start"
                type="submit"
                disabled={form.processing}
            >
                Record correction
            </Button>
        </form>
    );
}
