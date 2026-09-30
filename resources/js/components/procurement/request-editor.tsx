import { useState } from 'react';
import { Plus, Trash2 } from 'lucide-react';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/ProcurementController';
import {
    ActionDialog,
    createUuid,
    DateTimeField,
    Field,
    SelectField,
    TextField,
} from '@/components/procurement/shared';
import { Button } from '@/components/ui/button';
import type {
    Choice,
    Criterion,
    Currency,
    Procurement,
} from '@/types/procurement';

export function RequestEditor({
    request,
    currencies,
    approvers,
}: {
    request?: Procurement;
    currencies: Currency[];
    approvers: Choice[];
}) {
    const [items, setItems] = useState(request?.items ?? []);
    const [criteria, setCriteria] = useState<Criterion[]>(
        request?.criteria.length
            ? request.criteria
            : ['price', 'delivery', 'payment', 'specification'].map(
                  (category) => ({
                      uuid: category,
                      name: category[0].toUpperCase() + category.slice(1),
                      category,
                      mandatory: category === 'specification',
                      assessment_rule: '',
                  }),
              ),
    );
    const localDeadline = request?.deadline_at
        ? new Date(
              new Date(request.deadline_at).getTime() -
                  new Date(request.deadline_at).getTimezoneOffset() * 60000,
          )
              .toISOString()
              .slice(0, 16)
        : '';
    return (
        <ActionDialog
            title={request ? 'Edit request' : 'Create request'}
            trigger={
                <Button>
                    {request ? (
                        'Edit draft'
                    ) : (
                        <>
                            <Plus className="size-4" /> New request
                        </>
                    )}
                </Button>
            }
            action={request ? update(request.uuid) : store()}
            submit="Save draft"
            description="Define the requirement once. Save an incomplete draft, then publish when every requirement is ready."
            transform={(data) => {
                const fields = data as Record<string, unknown>;
                return {
                    ...fields,
                    deadline_at:
                        typeof fields.deadline_at === 'string' &&
                        fields.deadline_at
                            ? new Date(fields.deadline_at).toISOString()
                            : '',
                };
            }}
        >
            <input
                type="hidden"
                name="lock_version"
                value={request?.lock_version ?? 0}
            />
            <Field
                label="Request title"
                name="title"
                defaultValue={request?.title}
                required
                maxLength={200}
            />
            <TextField
                label="Description"
                name="description"
                defaultValue={request?.description ?? ''}
            />
            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Delivery destination"
                    name="delivery_destination"
                    defaultValue={request?.delivery_destination ?? ''}
                />
                <SelectField
                    label="Currency"
                    name="currency_code"
                    defaultValue={request?.currency_code ?? currencies[0]?.code}
                    placeholder="Select currency"
                    options={currencies.map((currency) => ({
                        value: currency.code,
                        label: currency.code + ' · ' + currency.name,
                    }))}
                />
                <DateTimeField
                    label="Deadline (your browser’s local time)"
                    name="deadline_at"
                    defaultValue={localDeadline}
                />
                <SelectField
                    label="Independent approver"
                    name="approver_uuid"
                    defaultValue={request?.approver_uuid}
                    placeholder="Select approver"
                    options={approvers.map((person) => ({
                        value: person.uuid,
                        label: person.name ?? person.uuid,
                    }))}
                />
            </div>
            <div className="grid gap-4 border-t pt-4">
                <div className="flex items-center justify-between">
                    <h3 className="font-semibold">Requested items</h3>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() =>
                            setItems([
                                ...items,
                                {
                                    uuid: createUuid(),
                                    line_number: items.length + 1,
                                    description: '',
                                    specification: '',
                                    quantity: '1',
                                    unit: 'each',
                                },
                            ])
                        }
                    >
                        <Plus className="size-4" />
                        Add item
                    </Button>
                </div>
                {items.map((item, index) => (
                    <fieldset
                        key={item.uuid}
                        aria-label={'Item ' + (index + 1)}
                        className="grid gap-3 rounded-lg border p-4"
                    >
                        <div className="flex items-center justify-between">
                            <span className="text-sm font-medium">
                                Item {index + 1}
                            </span>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                aria-label={'Remove item ' + (index + 1)}
                                onClick={() =>
                                    setItems(
                                        items.filter(
                                            (entry) => entry.uuid !== item.uuid,
                                        ),
                                    )
                                }
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        </div>
                        <Field
                            label="Description"
                            name={'items[' + index + '][description]'}
                            defaultValue={item.description}
                            required
                        />
                        <TextField
                            label="Mandatory specification"
                            name={'items[' + index + '][specification]'}
                            defaultValue={item.specification}
                        />
                        <div className="grid grid-cols-2 gap-3">
                            <Field
                                label="Quantity"
                                name={'items[' + index + '][quantity]'}
                                defaultValue={item.quantity}
                                inputMode="decimal"
                                required
                            />
                            <Field
                                label="Unit"
                                name={'items[' + index + '][unit]'}
                                defaultValue={item.unit}
                                required
                            />
                        </div>
                    </fieldset>
                ))}
            </div>
            <h3 className="border-t pt-4 font-semibold">Evaluation criteria</h3>
            {criteria.map((criterion, index) => (
                <div
                    key={criterion.uuid}
                    className="grid gap-3 rounded-lg border p-4"
                >
                    <input
                        type="hidden"
                        name={'criteria[' + index + '][category]'}
                        value={criterion.category}
                    />
                    <Field
                        label={
                            criterion.category[0].toUpperCase() +
                            criterion.category.slice(1) +
                            ' criterion'
                        }
                        name={'criteria[' + index + '][name]'}
                        defaultValue={criterion.name}
                        required
                    />
                    <TextField
                        label="Assessment / pass rule"
                        name={'criteria[' + index + '][assessment_rule]'}
                        defaultValue={criterion.assessment_rule}
                    />
                    <SelectField
                        label="Mandatory condition"
                        name={'criteria[' + index + '][mandatory]'}
                        defaultValue={criterion.mandatory ? '1' : '0'}
                        options={[
                            { value: '1', label: 'Mandatory — must pass' },
                            { value: '0', label: 'Comparison finding' },
                        ]}
                    />
                </div>
            ))}
            <Button
                type="button"
                variant="outline"
                onClick={() =>
                    setCriteria([
                        ...criteria,
                        {
                            uuid: createUuid(),
                            name:
                                'Additional specification ' +
                                (criteria.length + 1),
                            category: 'specification',
                            mandatory: false,
                            assessment_rule: '',
                        },
                    ])
                }
            >
                Add specification criterion
            </Button>
        </ActionDialog>
    );
}
