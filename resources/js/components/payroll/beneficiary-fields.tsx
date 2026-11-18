import { Field } from '@/components/payroll/workspace';
import { Input } from '@/components/ui/input';

export type Beneficiary = {
    name: string;
    bank: string;
    account: string;
    id_type: string;
    id_number: string;
};

export const emptyBeneficiary: Beneficiary = {
    name: '',
    bank: '',
    account: '',
    id_type: '',
    id_number: '',
};

const labels: Record<keyof Beneficiary, string> = {
    name: 'Beneficiary name',
    bank: 'Malaysian bank name',
    account: 'Account number',
    id_type: 'Identity document type',
    id_number: 'Identity document number',
};

export function BeneficiaryFields({
    value,
    onChange,
}: {
    value: Beneficiary;
    onChange: (value: Beneficiary) => void;
}) {
    return (
        <div className="grid gap-3 sm:grid-cols-2">
            {(Object.entries(labels) as [keyof Beneficiary, string][]).map(
                ([key, label]) => (
                    <Field key={key} label={label}>
                        <Input
                            autoComplete="off"
                            required
                            inputMode={
                                key === 'account' ? 'numeric' : undefined
                            }
                            value={value[key]}
                            onChange={(event) =>
                                onChange({
                                    ...value,
                                    [key]: event.target.value,
                                })
                            }
                        />
                    </Field>
                ),
            )}
        </div>
    );
}
