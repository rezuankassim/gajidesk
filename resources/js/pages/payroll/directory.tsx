import { Head, useForm } from '@inertiajs/react';
import { Building2, Plus, X } from 'lucide-react';
import { useState } from 'react';
import {
    BeneficiaryFields,
    emptyBeneficiary,
} from '@/components/payroll/beneficiary-fields';
import {
    decimal,
    Errors,
    Field,
    money,
    Panel,
    selectClass,
    Workspace,
} from '@/components/payroll/workspace';
import type { Employer } from '@/components/payroll/workspace';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import { employer as saveEmployer } from '@/routes/payroll';
import { store, update } from '@/routes/payroll/employees';

type Employee = {
    id: number;
    code: string;
    name: string;
    starts_on: string;
    ends_on: string | null;
    salary_sen: number;
    recurring: { type: string; label: string; sen: number }[];
    account: string | null;
    bank: string | null;
};

export default function Directory({
    employer,
    employees,
    jurisdictions,
}: {
    employer: Employer | null;
    employees: Employee[];
    jurisdictions: string[];
}) {
    const [selected, setSelected] = useState<Employee | 'new' | null>(null);
    const profile = useForm(
        employer ?? { name: '', work_location: '', jurisdiction: '' },
    );

    return (
        <Workspace employer={employer} section="Employees">
            <Head title="Employees" />
            <div className="mb-6 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="text-3xl font-semibold tracking-tight">
                        Employees
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Maintain salary settings and beneficiary details for new
                        worksheets.
                    </p>
                </div>
                <Button disabled={!employer} onClick={() => setSelected('new')}>
                    <Plus data-icon="inline-start" /> Add employee
                </Button>
            </div>
            <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_310px]">
                <Panel title="Employee directory">
                    {employees.length ? (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[600px] text-left text-sm">
                                <thead className="bg-muted/65 text-xs text-muted-foreground">
                                    <tr>
                                        {[
                                            'Employee',
                                            'Monthly basic',
                                            'Employment',
                                            'Beneficiary',
                                        ].map((label) => (
                                            <th
                                                key={label}
                                                scope="col"
                                                className="px-4 py-3 font-medium"
                                            >
                                                {label}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {employees.map((employee) => (
                                        <tr key={employee.id}>
                                            <td className="px-4 py-3">
                                                <button
                                                    className="text-left font-semibold text-primary hover:underline"
                                                    onClick={() =>
                                                        setSelected(employee)
                                                    }
                                                >
                                                    {employee.name}
                                                </button>
                                                <div className="text-xs text-muted-foreground">
                                                    {employee.code}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 tabular-nums">
                                                {money(employee.salary_sen)}
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {employee.starts_on}
                                                {employee.ends_on &&
                                                    ` to ${employee.ends_on}`}
                                            </td>
                                            <td className="px-4 py-3">
                                                {employee.bank ? (
                                                    <>
                                                        {employee.bank}
                                                        <div className="text-xs text-muted-foreground">
                                                            {employee.account}
                                                        </div>
                                                    </>
                                                ) : (
                                                    'Missing details'
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <p className="p-5 text-sm text-muted-foreground">
                            {employer
                                ? 'Add your first monthly salaried employee.'
                                : 'Save the employer profile before adding employees.'}
                        </p>
                    )}
                </Panel>
                <Panel
                    title="Employer profile"
                    action={
                        <Building2 className="size-4 text-muted-foreground" />
                    }
                >
                    <form
                        className="grid gap-4 p-5"
                        onSubmit={(event) => {
                            event.preventDefault();
                            profile.put(saveEmployer.url(), {
                                preserveScroll: true,
                            });
                        }}
                    >
                        <Field label="Company name">
                            <Input
                                required
                                value={profile.data.name}
                                onChange={(event) =>
                                    profile.setData('name', event.target.value)
                                }
                            />
                        </Field>
                        <Field label="Work location">
                            <Input
                                required
                                value={profile.data.work_location}
                                onChange={(event) =>
                                    profile.setData(
                                        'work_location',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field label="Payroll jurisdiction">
                            <select
                                required
                                className={selectClass}
                                value={profile.data.jurisdiction}
                                onChange={(event) =>
                                    profile.setData(
                                        'jurisdiction',
                                        event.target.value,
                                    )
                                }
                            >
                                <option value="">Select jurisdiction</option>
                                {jurisdictions.map((value) => (
                                    <option key={value}>{value}</option>
                                ))}
                            </select>
                        </Field>
                        <Errors errors={profile.errors} />
                        <Button
                            className="justify-self-start"
                            type="submit"
                            disabled={profile.processing}
                        >
                            Save employer
                        </Button>
                        {profile.recentlySuccessful && (
                            <p
                                role="status"
                                className="text-xs text-muted-foreground"
                            >
                                Employer saved
                            </p>
                        )}
                    </form>
                </Panel>
            </div>
            <Sheet
                open={selected !== null}
                onOpenChange={(open) => {
                    if (!open) setSelected(null);
                }}
            >
                {selected && (
                    <SheetContent className="payroll-theme w-full overflow-y-auto sm:max-w-xl">
                        <div className="border-b p-5 pr-12">
                            <SheetTitle>
                                {selected === 'new'
                                    ? 'Add employee'
                                    : selected.name}
                            </SheetTitle>
                            <SheetDescription>
                                Employment, recurring pay, and beneficiary
                                details
                            </SheetDescription>
                        </div>
                        <EmployeeForm
                            employee={selected === 'new' ? null : selected}
                            close={() => setSelected(null)}
                        />
                    </SheetContent>
                )}
            </Sheet>
        </Workspace>
    );
}

function EmployeeForm({
    employee,
    close,
}: {
    employee: Employee | null;
    close: () => void;
}) {
    const [replaceBank, setReplaceBank] = useState(false);
    const form = useForm({
        name: employee?.name ?? '',
        code: employee?.code ?? '',
        starts_on: employee?.starts_on ?? '',
        ends_on: employee?.ends_on ?? '',
        salary: employee ? decimal(employee.salary_sen) : '',
        recurring:
            employee?.recurring.map((entry) => ({
                type: entry.type,
                label: entry.label,
                amount: decimal(entry.sen),
            })) ?? [],
        beneficiary: emptyBeneficiary,
    });

    return (
        <form
            className="grid gap-4 px-5 pb-5"
            onSubmit={(event) => {
                event.preventDefault();
                form.transform((data) => ({
                    ...data,
                    beneficiary: replaceBank ? data.beneficiary : null,
                }));
                if (employee)
                    form.put(update.url(employee.id), { onSuccess: close });
                else form.post(store.url(), { onSuccess: close });
            }}
        >
            <div className="grid gap-3 sm:grid-cols-2">
                <Field label="Employee name">
                    <Input
                        required
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                    />
                </Field>
                <Field label="Employee code">
                    <Input
                        required
                        value={form.data.code}
                        onChange={(event) =>
                            form.setData('code', event.target.value)
                        }
                    />
                </Field>
                <Field label="Start date">
                    <Input
                        required
                        type="date"
                        value={form.data.starts_on}
                        onChange={(event) =>
                            form.setData('starts_on', event.target.value)
                        }
                    />
                </Field>
                <Field label="Last employment date">
                    <Input
                        type="date"
                        value={form.data.ends_on}
                        onChange={(event) =>
                            form.setData('ends_on', event.target.value)
                        }
                    />
                </Field>
                <Field label="Monthly basic (RM)">
                    <Input
                        required
                        inputMode="decimal"
                        value={form.data.salary}
                        onChange={(event) =>
                            form.setData('salary', event.target.value)
                        }
                    />
                </Field>
            </div>
            <div className="grid gap-3 border-t pt-4">
                <h3 className="text-sm font-semibold">
                    Recurring allowances and deductions
                </h3>
                {form.data.recurring.map((item, i) => {
                    const change = (key: keyof typeof item, value: string) =>
                        form.setData(
                            'recurring',
                            form.data.recurring.map((other, index) =>
                                index === i
                                    ? { ...other, [key]: value }
                                    : other,
                            ),
                        );
                    return (
                        <div
                            className="grid gap-2 rounded-lg border p-3"
                            key={i}
                        >
                            <div className="grid grid-cols-[1fr_1fr_auto] items-end gap-2">
                                <Field label="Kind">
                                    <select
                                        className={selectClass}
                                        value={item.type}
                                        onChange={(event) =>
                                            change('type', event.target.value)
                                        }
                                    >
                                        <option value="addition">
                                            Allowance
                                        </option>
                                        <option value="deduction">
                                            Deduction
                                        </option>
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
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Remove recurring item ${i + 1}`}
                                    onClick={() =>
                                        form.setData(
                                            'recurring',
                                            form.data.recurring.filter(
                                                (_, index) => index !== i,
                                            ),
                                        )
                                    }
                                >
                                    <X className="size-4" />
                                </Button>
                            </div>
                            <Field label="Item name">
                                <Input
                                    required
                                    value={item.label}
                                    onChange={(event) =>
                                        change('label', event.target.value)
                                    }
                                />
                            </Field>
                        </div>
                    );
                })}
                <Button
                    className="justify-self-start"
                    variant="outline"
                    type="button"
                    onClick={() =>
                        form.setData('recurring', [
                            ...form.data.recurring,
                            { type: 'addition', label: '', amount: '' },
                        ])
                    }
                >
                    <Plus data-icon="inline-start" /> Add recurring item
                </Button>
            </div>
            <div className="grid gap-3 border-t pt-4">
                <h3 className="text-sm font-semibold">Beneficiary details</h3>
                {employee?.bank && (
                    <p className="text-sm text-muted-foreground">
                        {employee.bank} · {employee.account}
                    </p>
                )}
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={replaceBank}
                        onChange={(event) =>
                            setReplaceBank(event.target.checked)
                        }
                    />
                    {employee?.bank
                        ? 'Replace beneficiary details'
                        : 'Enter beneficiary details'}
                </label>
                {replaceBank && (
                    <BeneficiaryFields
                        value={form.data.beneficiary}
                        onChange={(value) => form.setData('beneficiary', value)}
                    />
                )}
            </div>
            <Errors errors={form.errors} />
            <Button
                className="justify-self-start"
                type="submit"
                disabled={form.processing}
            >
                Save employee
            </Button>
        </form>
    );
}
