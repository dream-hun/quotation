import { Scale, TriangleAlert } from 'lucide-react';
import {
    assess,
    decide,
    freeze,
} from '@/actions/App/Http/Controllers/EvaluationController';
import {
    ActionDialog,
    EmptyState,
    Section,
    SelectField,
    Status,
    TextField,
} from '@/components/procurement/shared';
import { FrozenEvidence } from '@/components/procurement/frozen-evidence';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type {
    Candidate,
    Procurement,
    Revision,
    Round,
} from '@/types/procurement';

const findingOptions = [
    { value: 'pending', label: 'Pending' },
    { value: 'pass', label: 'Pass' },
    { value: 'fail', label: 'Fail' },
];

function Assessment({
    request,
    revision,
    candidate,
}: {
    request: Procurement;
    revision: Revision;
    candidate: Candidate;
}) {
    return (
        <ActionDialog
            title="Record findings"
            action={assess(request.uuid)}
            description="Record evidence for every specification and criterion. Eligibility is calculated from your findings."
        >
            <input type="hidden" name="candidate_uuid" value={candidate.uuid} />
            <input
                type="hidden"
                name="lock_version"
                value={candidate.lock_version}
            />
            {revision.lines.map((line, index) => {
                const finding = candidate.lines.find(
                    (entry) => entry.uuid === line.uuid,
                );
                return (
                    <div
                        key={line.uuid}
                        className="grid gap-3 rounded-lg border p-4"
                    >
                        <p className="font-medium">
                            {
                                request.items.find(
                                    (item) => item.uuid === line.item_uuid,
                                )?.description
                            }
                        </p>
                        <p className="text-muted-foreground text-sm">
                            {line.offered_description} · Supplier response:{' '}
                            {line.supplier_compliance_response}
                        </p>
                        <input
                            type="hidden"
                            name={'lines[' + index + '][uuid]'}
                            value={line.uuid}
                        />
                        <SelectField
                            label="Specification finding"
                            name={'lines[' + index + '][result]'}
                            defaultValue={finding?.result ?? 'pending'}
                            options={findingOptions}
                        />
                        <TextField
                            label="Evidence"
                            name={'lines[' + index + '][finding]'}
                            defaultValue={finding?.finding ?? ''}
                            required
                        />
                    </div>
                );
            })}
            {request.criteria.map((criterion, index) => {
                const finding = candidate.criteria.find(
                    (entry) => entry.uuid === criterion.uuid,
                );
                return (
                    <div
                        key={criterion.uuid}
                        className="grid gap-3 rounded-lg border p-4"
                    >
                        <p className="font-medium">
                            {criterion.name}
                            {criterion.mandatory ? ' · Mandatory' : ''}
                        </p>
                        <p className="text-muted-foreground text-sm">
                            {criterion.assessment_rule}
                        </p>
                        <input
                            type="hidden"
                            name={'criteria[' + index + '][uuid]'}
                            value={criterion.uuid}
                        />
                        <SelectField
                            label="Criterion finding"
                            name={'criteria[' + index + '][result]'}
                            defaultValue={finding?.result ?? 'pending'}
                            options={findingOptions}
                        />
                        <TextField
                            label="Finding and rationale"
                            name={'criteria[' + index + '][finding]'}
                            defaultValue={finding?.finding ?? ''}
                            required
                        />
                    </div>
                );
            })}
            <TextField
                label="Exclusion reason (required when excluded)"
                name="reason"
                defaultValue={candidate.disposition_reason ?? ''}
            />
        </ActionDialog>
    );
}

export function EvaluationPanel({
    request,
    revisions,
    rounds,
    buyer,
}: {
    request: Procurement;
    revisions: Revision[];
    rounds: Round[];
    buyer: boolean;
}) {
    const active = rounds[0];
    return (
        <Section
            title="Quotation comparison"
            description="Compare total cost, delivery, payment terms and verified compliance. A low price does not authorize an award."
        >
            {revisions.length === 0 ? (
                <EmptyState
                    icon={Scale}
                    title="No submitted quotations"
                    description="Record the outcome and cancel or clone the request."
                />
            ) : (
                <div className="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead
                                    scope="col"
                                    className="bg-card sticky left-0 z-10 h-auto min-w-44 p-3"
                                >
                                    Comparison dimension
                                </TableHead>
                                {revisions.map((revision) => (
                                    <TableHead
                                        key={revision.uuid}
                                        scope="col"
                                        className="text-foreground h-auto min-w-64 p-3"
                                    >
                                        {revision.supplier_name_snapshot}
                                        <p className="text-muted-foreground text-xs font-normal">
                                            Revision {revision.revision_number}
                                        </p>
                                    </TableHead>
                                ))}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {[
                                {
                                    name: 'Total payable',
                                    cell: (revision: Revision) => (
                                        <strong>
                                            {request.currency_code}{' '}
                                            {revision.total_amount}
                                        </strong>
                                    ),
                                },
                                {
                                    name: 'Delivery',
                                    cell: (revision: Revision) => (
                                        <>
                                            {revision.delivery_days} days ·
                                            charge {revision.delivery_charge}
                                        </>
                                    ),
                                },
                                {
                                    name: 'Payment terms',
                                    cell: (revision: Revision) => (
                                        <>
                                            {revision.payment_terms}
                                            <p className="text-muted-foreground mt-1 text-xs">
                                                Advance:{' '}
                                                {revision.advance_percent ??
                                                    'Not specified'}{' '}
                                                · Balance days:{' '}
                                                {revision.balance_due_days ??
                                                    'Not specified'}
                                            </p>
                                        </>
                                    ),
                                },
                                {
                                    name: 'Valid through',
                                    cell: (revision: Revision) => (
                                        <>
                                            {revision.valid_until?.slice(0, 10)}
                                        </>
                                    ),
                                },
                            ].map((row) => (
                                <TableRow
                                    key={row.name}
                                    className="hover:bg-transparent"
                                >
                                    <th
                                        scope="row"
                                        className="bg-card sticky left-0 p-3 text-left align-top font-medium"
                                    >
                                        {row.name}
                                    </th>
                                    {revisions.map((revision) => (
                                        <TableCell
                                            key={revision.uuid}
                                            className="p-3 align-top whitespace-normal"
                                        >
                                            {row.cell(revision)}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))}
                            {request.items.map((item) => (
                                <TableRow
                                    key={item.uuid}
                                    className="hover:bg-transparent"
                                >
                                    <th
                                        scope="row"
                                        className="bg-card sticky left-0 p-3 text-left align-top font-medium"
                                    >
                                        {item.description}
                                        <p className="text-muted-foreground mt-1 text-xs font-normal">
                                            {item.quantity} {item.unit}
                                        </p>
                                    </th>
                                    {revisions.map((revision) => {
                                        const line = revision.lines.find(
                                            (entry) =>
                                                entry.item_uuid === item.uuid,
                                        );
                                        return (
                                            <TableCell
                                                key={revision.uuid}
                                                className="p-3 align-top whitespace-normal"
                                            >
                                                <strong>
                                                    {line?.line_total}
                                                </strong>
                                                <p className="text-muted-foreground mt-1 text-xs">
                                                    Unit {line?.unit_price} ·
                                                    Discount{' '}
                                                    {line?.discount_amount} ·
                                                    Net {line?.line_net} · Tax{' '}
                                                    {line?.tax_amount}
                                                </p>
                                                <p className="mt-2">
                                                    {
                                                        line?.supplier_compliance_response
                                                    }
                                                </p>
                                            </TableCell>
                                        );
                                    })}
                                </TableRow>
                            ))}
                            <TableRow className="hover:bg-transparent">
                                <th
                                    scope="row"
                                    className="bg-card sticky left-0 p-3 text-left align-top font-medium"
                                >
                                    Evaluator disposition
                                </th>
                                {revisions.map((revision) => {
                                    const candidate = active?.candidates.find(
                                        (entry) =>
                                            entry.revision_uuid ===
                                            revision.uuid,
                                    );
                                    return (
                                        <TableCell
                                            key={revision.uuid}
                                            className="p-3 align-top whitespace-normal"
                                        >
                                            <div className="grid gap-3">
                                                <Status
                                                    value={
                                                        candidate?.disposition ??
                                                        'pending'
                                                    }
                                                />
                                                <p>
                                                    {
                                                        candidate?.disposition_reason
                                                    }
                                                </p>
                                                {candidate &&
                                                    buyer &&
                                                    active?.status ===
                                                        'draft' && (
                                                        <Assessment
                                                            request={request}
                                                            revision={revision}
                                                            candidate={
                                                                candidate
                                                            }
                                                        />
                                                    )}
                                            </div>
                                        </TableCell>
                                    );
                                })}
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            )}
            {active?.canFreeze && (
                <div className="mt-4 grid gap-3 border-t pt-4">
                    {active.candidates.filter(
                        (candidate) => candidate.disposition === 'eligible',
                    ).length === 1 && (
                        <Alert className="border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                            <TriangleAlert />
                            <AlertDescription className="text-current">
                                Only one eligible quotation. A participation
                                justification is required.
                            </AlertDescription>
                        </Alert>
                    )}
                    <ActionDialog
                        title="Recommend for approval"
                        triggerVariant="default"
                        action={freeze(request.uuid)}
                        submit="Freeze and request approval"
                        description="This freezes the comparison, findings and recommendation. Only the assigned independent approver can decide the award."
                    >
                        <input
                            type="hidden"
                            name="lock_version"
                            value={active.lock_version}
                        />
                        <SelectField
                            label="Recommended quotation"
                            name="candidate_uuid"
                            required
                            placeholder="Select eligible quotation"
                            options={active.candidates
                                .filter(
                                    (candidate) =>
                                        candidate.disposition === 'eligible',
                                )
                                .map((candidate) => ({
                                    value: candidate.uuid,
                                    label:
                                        revisions.find(
                                            (revision) =>
                                                revision.uuid ===
                                                candidate.revision_uuid,
                                        )?.supplier_name_snapshot ??
                                        candidate.uuid,
                                }))}
                        />
                        <TextField
                            label="Selection rationale and tradeoffs"
                            name="reason"
                            required
                        />
                        <TextField
                            label="Participation justification (if only one eligible offer)"
                            name="participation_justification"
                        />
                    </ActionDialog>
                </div>
            )}
            {rounds.map((round) => (
                <div
                    key={round.uuid}
                    className="mt-4 grid gap-3 rounded-lg border p-4"
                >
                    <div className="flex items-center justify-between gap-3">
                        <h3 className="font-medium">
                            Evaluation round {round.round_number}
                        </h3>
                        <Status value={round.status} />
                    </div>
                    {round.recommendation_reason && (
                        <p className="text-sm">{round.recommendation_reason}</p>
                    )}
                    {round.participation_justification && (
                        <p className="text-muted-foreground text-sm">
                            Participation: {round.participation_justification}
                        </p>
                    )}
                    {round.approval && (
                        <div className="flex flex-wrap items-center gap-2 text-sm">
                            <span className="text-muted-foreground">
                                Decision:
                            </span>
                            <Status value={round.approval.status} />
                            {round.approval.decision_reason}
                        </div>
                    )}
                    {round.frozen_snapshot && <FrozenEvidence round={round} />}
                    {round.canDecide &&
                        round.approval?.status === 'pending' && (
                            <div className="flex flex-wrap gap-2">
                                {['approve', 'return', 'reject'].map(
                                    (decision) => (
                                        <ActionDialog
                                            key={decision}
                                            title={
                                                decision[0].toUpperCase() +
                                                decision.slice(1)
                                            }
                                            action={decide(request.uuid)}
                                            submit={'Confirm ' + decision}
                                            triggerVariant={
                                                decision === 'approve'
                                                    ? 'default'
                                                    : decision === 'reject'
                                                      ? 'destructive'
                                                      : 'outline'
                                            }
                                            submitVariant={
                                                decision === 'reject'
                                                    ? 'destructive'
                                                    : 'default'
                                            }
                                            description={
                                                decision === 'approve'
                                                    ? 'Approve the frozen recommendation and record one award. This decision is final.'
                                                    : 'Record a reason. Return creates a new evaluation round; rejection ends this procurement.'
                                            }
                                        >
                                            <input
                                                type="hidden"
                                                name="lock_version"
                                                value={
                                                    round.approval?.lock_version
                                                }
                                            />
                                            <input
                                                type="hidden"
                                                name="operation"
                                                value={decision}
                                            />
                                            <TextField
                                                label="Decision reason"
                                                name="reason"
                                                required={
                                                    decision !== 'approve'
                                                }
                                            />
                                        </ActionDialog>
                                    ),
                                )}
                            </div>
                        )}
                </div>
            ))}
        </Section>
    );
}
