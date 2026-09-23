import {
    save,
    submit,
} from '@/actions/App/Http/Controllers/QuotationController';
import {
    ActionDialog,
    DateField,
    Field,
    TextField,
    timestamp,
} from '@/components/procurement/shared';
import type { Procurement, Revision } from '@/types/procurement';

export function QuotationEditor({
    request,
    revisions,
}: {
    request: Procurement;
    revisions: Revision[];
}) {
    const draft = revisions.find((revision) => revision.status === 'draft');
    const source = draft ?? revisions[0];
    return (
        <div className="flex flex-wrap gap-2">
            <ActionDialog
                title={draft ? 'Edit quotation draft' : 'Prepare quotation'}
                triggerVariant={draft ? 'outline' : 'default'}
                action={save(request.uuid)}
                submit="Save draft"
                description="Amounts are entered as decimals in the request currency. Saved drafts stay private. Saving a draft does not submit it."
            >
                <input
                    type="hidden"
                    name="lock_version"
                    value={draft?.lock_version ?? 0}
                />
                <Field
                    label="Your reference"
                    name="supplier_reference"
                    defaultValue={source?.supplier_reference ?? ''}
                />
                <div className="grid gap-4 sm:grid-cols-2">
                    <DateField
                        label="Valid through (organization date)"
                        name="valid_until"
                        defaultValue={source?.valid_until}
                    />
                    <Field
                        label="Delivery days after award notification"
                        name="delivery_days"
                        type="number"
                        min="0"
                        defaultValue={source?.delivery_days ?? ''}
                    />
                    <Field
                        label={
                            'Delivery charge including tax (' +
                            request.currency_code +
                            ')'
                        }
                        name="delivery_charge"
                        inputMode="decimal"
                        defaultValue={source?.delivery_charge ?? '0'}
                    />
                    <Field
                        label="Advance payment (%) · optional"
                        name="advance_percent"
                        inputMode="decimal"
                        defaultValue={source?.advance_percent ?? ''}
                    />
                    <Field
                        label="Balance due (days) · optional"
                        name="balance_due_days"
                        type="number"
                        min="0"
                        defaultValue={source?.balance_due_days ?? ''}
                    />
                </div>
                <TextField
                    label="Payment terms"
                    name="payment_terms"
                    defaultValue={source?.payment_terms ?? ''}
                />
                {request.items.map((item, index) => {
                    const line = source?.lines.find(
                        (line) => line.item_uuid === item.uuid,
                    );
                    const prefix = 'lines[' + index + ']';
                    return (
                        <fieldset
                            key={item.uuid}
                            aria-label={item.description}
                            className="grid gap-4 rounded-lg border p-4"
                        >
                            <div className="grid gap-1">
                                <p className="font-medium">
                                    {item.description}
                                </p>
                                <p className="text-muted-foreground text-sm">
                                    {item.quantity} {item.unit}
                                    {item.specification &&
                                        ' · ' + item.specification}
                                </p>
                            </div>
                            <div className="grid gap-4">
                                <input
                                    type="hidden"
                                    name={prefix + '[item_uuid]'}
                                    value={item.uuid}
                                />
                                <TextField
                                    label="Offered product / service"
                                    name={prefix + '[offered_description]'}
                                    defaultValue={
                                        line?.offered_description ?? ''
                                    }
                                />
                                <div className="grid gap-3 sm:grid-cols-3">
                                    <Field
                                        label="Unit price"
                                        name={prefix + '[unit_price]'}
                                        inputMode="decimal"
                                        defaultValue={line?.unit_price ?? ''}
                                    />
                                    <Field
                                        label="Line discount amount"
                                        name={prefix + '[discount_amount]'}
                                        inputMode="decimal"
                                        defaultValue={
                                            line?.discount_amount ?? '0'
                                        }
                                    />
                                    <Field
                                        label="Line tax amount"
                                        name={prefix + '[tax_amount]'}
                                        inputMode="decimal"
                                        defaultValue={line?.tax_amount ?? '0'}
                                    />
                                </div>
                                <TextField
                                    label="Compliance response"
                                    name={
                                        prefix +
                                        '[supplier_compliance_response]'
                                    }
                                    defaultValue={
                                        line?.supplier_compliance_response ?? ''
                                    }
                                />
                                <Field
                                    label="Reason if unit price is zero"
                                    name={prefix + '[zero_price_reason]'}
                                    defaultValue={line?.zero_price_reason ?? ''}
                                />
                            </div>
                        </fieldset>
                    );
                })}
            </ActionDialog>
            {draft && (
                <ActionDialog
                    title="Submit saved quotation"
                    triggerVariant="default"
                    action={submit(request.uuid)}
                    submit="Submit quotation"
                    description={
                        'This will submit saved revision ' +
                        draft.revision_number +
                        '. Its contents become immutable. Deadline: ' +
                        timestamp(request.deadline_at, request.timezone) +
                        ' (' +
                        request.timezone +
                        ').'
                    }
                >
                    <input
                        type="hidden"
                        name="revision_uuid"
                        value={draft.uuid}
                    />
                    <input
                        type="hidden"
                        name="lock_version"
                        value={draft.lock_version}
                    />
                    <input
                        type="hidden"
                        name="submission_key"
                        value={draft.uuid}
                    />
                    <p className="text-muted-foreground text-sm">
                        Confirm that every line is priced and your saved
                        commercial terms and supporting documents are complete.
                        A receipt will appear after the transaction succeeds.
                    </p>
                </ActionDialog>
            )}
        </div>
    );
}
