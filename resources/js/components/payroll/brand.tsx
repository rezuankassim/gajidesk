import { WalletCards } from 'lucide-react';

export const payrollName = 'GajiDesk';

export function PayrollBrand() {
    return (
        <span className="inline-flex items-center gap-2.5">
            <span className="grid size-8 place-items-center rounded-lg bg-primary text-primary-foreground">
                <WalletCards aria-hidden="true" className="size-4.5" />
            </span>
            <span className="text-lg font-semibold tracking-tight">
                {payrollName}
            </span>
        </span>
    );
}
