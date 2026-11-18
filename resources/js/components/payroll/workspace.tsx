import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    ChevronRight,
    LayoutGrid,
    LogOut,
    UsersRound,
} from 'lucide-react';
import type { PropsWithChildren, ReactNode } from 'react';
import { PayrollBrand } from '@/components/payroll/brand';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { directory, index } from '@/routes/payroll';
import { logout } from '@/routes';

export type Employer = {
    name: string;
    work_location: string;
    jurisdiction: string;
};
export type Item = {
    key: string;
    label: string;
    bucket: string;
    sen: number;
    reason: string | null;
};
export type Result = {
    basic: number | null;
    additions: number;
    earnings: number;
    reimbursements: number;
    deductions: number;
    contributions: number;
    net: number | null;
    estimated: boolean;
    items: Item[];
    issues: string[];
};

const currency = new Intl.NumberFormat('en-MY', {
    style: 'currency',
    currency: 'MYR',
});
export const money = (sen: number | null) =>
    sen === null ? '—' : currency.format(sen / 100);
export const decimal = (sen: number) => (sen / 100).toFixed(2);
export const initials = (name: string) =>
    name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0].toUpperCase())
        .join('');
export const monthName = (month: string) =>
    new Intl.DateTimeFormat('en-MY', {
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(month + '-01T00:00:00Z'));

export const rollup = [
    ['Gross earnings', 'earnings'],
    ['Reimbursements', 'reimbursements'],
    ['Employee deductions', 'deductions'],
    ['Employer contributions', 'contributions'],
    ['Net payable', 'net'],
] as const;

export function Workspace({
    employer,
    section,
    children,
}: PropsWithChildren<{ employer: Employer | null; section: string }>) {
    const { auth } = usePage<{ auth: { user: { name: string } } }>().props;

    return (
        <div className="payroll-shell min-h-screen bg-background text-foreground md:grid md:grid-cols-[218px_minmax(0,1fr)]">
            <a
                href="#payroll-main"
                className="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-10 focus:rounded-md focus:bg-card focus:p-3 focus:ring-2 focus:ring-ring print:hidden"
            >
                Skip to content
            </a>
            <aside className="flex flex-col border-b bg-card md:sticky md:top-0 md:h-dvh md:border-r md:border-b-0 print:hidden">
                <Link
                    href={index()}
                    className="flex h-17 items-center gap-3 px-5 md:border-b"
                >
                    <PayrollBrand />
                </Link>
                <nav
                    aria-label="Payroll workspace"
                    className="flex gap-2 px-3 pb-3 md:flex-col md:p-3 md:pt-6"
                >
                    <span className="hidden px-3 pb-1 text-xs font-medium text-muted-foreground md:block">
                        Workspace
                    </span>
                    {[
                        {
                            title: 'Monthly payroll',
                            url: index(),
                            icon: LayoutGrid,
                            selected: section !== 'Employees',
                        },
                        {
                            title: 'Employees',
                            url: directory(),
                            icon: UsersRound,
                            selected: section === 'Employees',
                        },
                    ].map(({ title, url, icon: Icon, selected }) => (
                        <Link
                            aria-current={selected ? 'page' : undefined}
                            key={title}
                            href={url}
                            className={cn(
                                'flex min-h-11 items-center gap-3 rounded-md px-3 text-sm font-medium transition-colors',
                                selected
                                    ? 'bg-accent text-accent-foreground hover:bg-accent'
                                    : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                            )}
                        >
                            <Icon className="size-4" /> {title}
                        </Link>
                    ))}
                </nav>
                {employer && (
                    <div className="mt-auto hidden border-t p-3 md:block">
                        <div className="flex items-center gap-3 rounded-lg bg-muted p-3">
                            <Building2 className="size-4 shrink-0 text-primary" />
                            <div className="min-w-0">
                                <p className="truncate text-xs font-semibold">
                                    {employer.name}
                                </p>
                                <p className="truncate pt-0.5 text-xs text-muted-foreground">
                                    {employer.work_location},{' '}
                                    {employer.jurisdiction}
                                </p>
                            </div>
                        </div>
                    </div>
                )}
            </aside>
            <div className="min-w-0">
                <header className="flex h-17 items-center justify-between gap-3 border-b bg-card px-4 sm:px-7 print:hidden">
                    <div className="flex items-center gap-2 text-sm">
                        <span className="hidden text-muted-foreground sm:inline">
                            Workspace
                        </span>
                        <ChevronRight className="hidden size-3.5 text-muted-foreground sm:block" />
                        <span className="font-medium">{section}</span>
                    </div>
                    <div className="flex items-center gap-2.5">
                        <Avatar className="size-8">
                            <AvatarFallback className="bg-accent text-xs font-semibold text-accent-foreground">
                                {initials(auth.user.name)}
                            </AvatarFallback>
                        </Avatar>
                        <div className="hidden text-xs leading-tight sm:block">
                            <div className="font-semibold">
                                {auth.user.name}
                            </div>
                            <div className="text-muted-foreground">
                                Payroll administrator
                            </div>
                        </div>
                        <Button
                            asChild
                            variant="ghost"
                            size="icon"
                            aria-label="Log out"
                        >
                            <Link
                                href={logout()}
                                as="button"
                                aria-label="Log out"
                            >
                                <LogOut className="size-4" />
                            </Link>
                        </Button>
                    </div>
                </header>
                <main
                    id="payroll-main"
                    className="mx-auto max-w-[1600px] px-4 py-6 sm:px-7 sm:py-8 print:p-0"
                >
                    {children}
                </main>
            </div>
        </div>
    );
}

export function Panel({
    title,
    action,
    children,
}: PropsWithChildren<{ title: string; action?: ReactNode }>) {
    return (
        <Card className="min-w-0 gap-0 py-0">
            <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-3 border-b px-5 py-4">
                <CardTitle className="text-base tracking-tight">
                    <h2>{title}</h2>
                </CardTitle>
                {action}
            </CardHeader>
            {children}
        </Card>
    );
}

export function Field({
    label,
    children,
}: PropsWithChildren<{ label: string }>) {
    return (
        <label className="grid gap-1.5 text-sm font-medium">
            {label}
            {children}
        </label>
    );
}

export function Errors({ errors }: { errors: object }) {
    const messages = Object.values(errors).filter(
        (error): error is string => typeof error === 'string',
    );
    return (
        messages.length > 0 && (
            <ul
                role="alert"
                className="grid gap-1 rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive"
            >
                {[...new Set(messages)].map((message) => (
                    <li key={message}>{message}</li>
                ))}
            </ul>
        )
    );
}

export const selectClass =
    'h-9 w-full min-w-0 rounded-md border border-input bg-background px-2 text-sm shadow-xs focus-visible:outline-2 focus-visible:outline-ring';

export function PayTotals({
    totals,
}: {
    totals: Record<(typeof rollup)[number][1], number | null>;
}) {
    return (
        <dl className="grid gap-3 text-sm">
            {rollup.map(([label, key]) => (
                <div
                    key={key}
                    className={`flex justify-between gap-3 ${key === 'net' ? 'border-t pt-3 font-semibold' : ''}`}
                >
                    <dt>{label}</dt>
                    <dd className="tabular-nums">{money(totals[key])}</dd>
                </div>
            ))}
        </dl>
    );
}
