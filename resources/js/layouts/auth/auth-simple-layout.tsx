import { Link } from '@inertiajs/react';
import { PayrollBrand } from '@/components/payroll/brand';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <main className="payroll-shell grid min-h-svh place-items-center bg-background px-4 py-8 sm:p-8">
            <Card className="w-full max-w-md gap-6 py-7 sm:py-8">
                <CardHeader className="gap-2 px-6 sm:px-8">
                    <Link href={home()} className="mb-4 w-fit rounded-md">
                        <PayrollBrand />
                    </Link>
                    <CardTitle>
                        <h1 className="text-2xl tracking-tight">{title}</h1>
                    </CardTitle>
                    <CardDescription>{description}</CardDescription>
                </CardHeader>
                <CardContent className="px-6 sm:px-8">{children}</CardContent>
            </Card>
        </main>
    );
}
