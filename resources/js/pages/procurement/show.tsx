import { Head, Link } from '@inertiajs/react';
import { Ban, FileDown, FileText, LockKeyhole } from 'lucide-react';
import { store as clarify } from '@/actions/App/Http/Controllers/ClarificationController';
import { start } from '@/actions/App/Http/Controllers/EvaluationController';
import {
    change,
    index,
    publish,
} from '@/actions/App/Http/Controllers/ProcurementController';
import {
    download,
    store as upload,
} from '@/actions/App/Http/Controllers/ProcurementFileController';
import { participation as respond } from '@/actions/App/Http/Controllers/QuotationController';
import { EvaluationPanel } from '@/components/procurement/evaluation-panel';
import { QuotationEditor } from '@/components/procurement/quotation-editor';
import { RequestEditor } from '@/components/procurement/request-editor';
import {
    ActionDialog,
    CheckboxField,
    EmptyState,
    Field,
    Flash,
    PageContainer,
    PageHeader,
    Pagination,
    Section,
    SelectField,
    Status,
    TextField,
    timestamp,
} from '@/components/procurement/shared';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import type {
    Choice,
    Currency,
    FileRecord,
    PageData,
    Participation,
    Procurement,
    Revision,
    Round,
    Thread,
} from '@/types/procurement';

function Files({ files }: { files: FileRecord[] }) {
    return (
        <div className="grid gap-2">
            {files.length === 0 && (
                <p className="text-muted-foreground text-sm">
                    No documents attached.
                </p>
            )}
            {files.map((file) => (
                <div
                    key={file.uuid}
                    className="flex flex-wrap items-center justify-between gap-3 rounded-md border px-3 py-2 text-sm"
                >
                    {file.scan_status === 'clean' ? (
                        <a
                            href={download.url(file.uuid)}
                            className="flex min-w-0 items-center gap-2 font-medium hover:underline"
                        >
                            <FileDown className="text-muted-foreground size-4 shrink-0" />
                            <span className="truncate">
                                {file.original_name}
                            </span>
                        </a>
                    ) : (
                        <span className="text-muted-foreground flex min-w-0 items-center gap-2">
                            <FileText className="size-4 shrink-0" />
                            <span className="truncate">
                                {file.original_name}
                            </span>
                        </span>
                    )}
                    <Status value={file.scan_status} />
                </div>
            ))}
        </div>
    );
}

function Upload({
    request,
    type,
    uuid,
}: {
    request: Procurement;
    type: string;
    uuid?: string;
}) {
    return (
        <ActionDialog
            title="Attach document"
            action={upload(request.uuid)}
            submit="Upload for checking"
            description="PDF, DOCX, XLSX, PNG or JPEG. Up to 10 MB per file and ten files per record. Files remain unavailable until security checks pass."
        >
            <input type="hidden" name="parent_type" value={type} />
            <input type="hidden" name="parent_uuid" value={uuid ?? ''} />
            <Field
                label="Document"
                name="file"
                type="file"
                accept=".pdf,.docx,.xlsx,.png,.jpg,.jpeg"
                required
            />
        </ActionDialog>
    );
}

type Props = {
    request: Procurement;
    participation: Participation[];
    revisions: Revision[];
    rounds: Round[];
    threads: Thread[];
    attachments: FileRecord[];
    can: {
        buyer: boolean;
        supplier: boolean;
        commercial: boolean;
        internal: boolean;
    };
    currencies: Currency[];
    approvers: Choice[];
    suppliers: Choice[];
    audit: PageData<{
        uuid: string;
        action: string;
        actor_label: string;
        occurred_at: string;
        safe_details: Record<string, unknown>;
    }> | null;
};

export default function Show({
    request,
    participation,
    revisions,
    rounds,
    threads,
    attachments,
    can,
    currencies,
    approvers,
    suppliers,
    audit,
}: Props) {
    const open =
        request.status === 'open' &&
        !!request.deadline_at &&
        new Date(request.deadline_at).getTime() > Date.now();
    const canClarify =
        (can.buyer || can.supplier) &&
        ['open', 'closed', 'evaluating'].includes(request.status);
    const draft = revisions.find((revision) => revision.status === 'draft');
    return (
        <>
            <Head title={request.title} />
            <PageContainer>
                <PageHeader
                    eyebrow={
                        <>
                            <span>{request.request_number}</span>
                            <Status value={request.status} />
                        </>
                    }
                    title={request.title}
                    description={
                        <>
                            Deadline:{' '}
                            {timestamp(request.deadline_at, request.timezone)} ·{' '}
                            {request.timezone}
                        </>
                    }
                    actions={
                        <>
                            {can.buyer && request.status === 'draft' && (
                                <RequestEditor
                                    key={request.lock_version}
                                    request={request}
                                    currencies={currencies}
                                    approvers={approvers}
                                />
                            )}
                            {can.buyer &&
                                ['draft', 'open'].includes(request.status) && (
                                    <ActionDialog
                                        title={
                                            request.status === 'draft'
                                                ? 'Publish & invite'
                                                : 'Invite suppliers'
                                        }
                                        triggerVariant={
                                            request.status === 'draft'
                                                ? 'default'
                                                : 'outline'
                                        }
                                        action={publish(request.uuid)}
                                        submit="Confirm invitations"
                                        description="Publication freezes requirements, currency and criteria. Choose suppliers who should receive this request."
                                    >
                                        <input
                                            type="hidden"
                                            name="lock_version"
                                            value={request.lock_version}
                                        />
                                        <div className="grid gap-2 sm:grid-cols-2">
                                            {suppliers.map((supplier) => (
                                                <CheckboxField
                                                    key={supplier.uuid}
                                                    bordered
                                                    name="supplier_uuids[]"
                                                    value={supplier.uuid}
                                                    label={supplier.legal_name}
                                                />
                                            ))}
                                        </div>
                                        {suppliers.length === 0 && (
                                            <p className="text-muted-foreground text-sm">
                                                No active suppliers. Add
                                                supplier companies in
                                                Administration first.
                                            </p>
                                        )}
                                    </ActionDialog>
                                )}
                            {can.buyer &&
                                can.commercial &&
                                ['open', 'closed'].includes(request.status) && (
                                    <ActionDialog
                                        title="Start evaluation"
                                        triggerVariant="default"
                                        action={start(request.uuid)}
                                        submit="Open evaluation"
                                    >
                                        <input
                                            type="hidden"
                                            name="lock_version"
                                            value={request.lock_version}
                                        />
                                        <p className="text-muted-foreground text-sm">
                                            Open a review of each supplier’s
                                            latest submitted quotation.
                                            Withdrawn and expired offers will
                                            remain visible as excluded.
                                        </p>
                                    </ActionDialog>
                                )}
                        </>
                    }
                />
                <Flash />
                {request.cancellation_reason && (
                    <Alert variant="destructive">
                        <Ban />
                        <AlertDescription>
                            Cancellation reason: {request.cancellation_reason}
                        </AlertDescription>
                    </Alert>
                )}
                <div className="grid gap-4 lg:grid-cols-[2fr_1fr]">
                    <Section
                        title="Purchase requirements"
                        description={request.description ?? undefined}
                    >
                        <div className="grid gap-3">
                            <dl className="grid gap-3 text-sm sm:grid-cols-2">
                                <div className="grid gap-1">
                                    <dt className="text-muted-foreground">
                                        Deliver to
                                    </dt>
                                    <dd className="font-medium">
                                        {request.delivery_destination ||
                                            'Not set'}
                                    </dd>
                                </div>
                                <div className="grid gap-1">
                                    <dt className="text-muted-foreground">
                                        Currency
                                    </dt>
                                    <dd className="font-medium">
                                        {request.currency_code || 'Not set'}
                                    </dd>
                                </div>
                            </dl>
                            {request.items.map((item) => (
                                <div
                                    key={item.uuid}
                                    className="rounded-lg border p-4"
                                >
                                    <div className="flex justify-between gap-4">
                                        <h3 className="font-medium">
                                            {item.description}
                                        </h3>
                                        <Badge
                                            variant="secondary"
                                            className="tabular-nums"
                                        >
                                            {item.quantity} {item.unit}
                                        </Badge>
                                    </div>
                                    <p className="text-muted-foreground mt-1 text-sm">
                                        {item.specification ||
                                            'Specification not set'}
                                    </p>
                                </div>
                            ))}
                            {request.items.length === 0 && (
                                <EmptyState
                                    icon={FileText}
                                    title="No items yet"
                                    description="Add items to complete this draft."
                                />
                            )}
                        </div>
                    </Section>
                    <div className="grid content-start gap-4">
                        <Section title="Decision controls">
                            <p className="text-muted-foreground text-sm">
                                Assigned approver
                            </p>
                            <p className="mt-1 font-medium">
                                {request.approver_name ?? 'Not assigned'}
                            </p>
                            <p className="text-muted-foreground mt-3 border-t pt-3 text-sm">
                                A recommendation requires complete findings. The
                                creator and evaluator cannot approve their own
                                procurement.
                            </p>
                        </Section>
                        <Section
                            title="Request documents"
                            action={
                                can.buyer && request.status === 'draft' ? (
                                    <Upload request={request} type="request" />
                                ) : undefined
                            }
                        >
                            <Files files={attachments} />
                        </Section>
                    </div>
                </div>
                <Section title="Evaluation criteria">
                    <div className="grid gap-3 sm:grid-cols-2">
                        {request.criteria.map((criterion) => (
                            <div
                                key={criterion.uuid}
                                className="rounded-lg border p-4"
                            >
                                <div className="flex items-center justify-between gap-3">
                                    <h3 className="font-medium">
                                        {criterion.name}
                                    </h3>
                                    <Badge
                                        variant={
                                            criterion.mandatory
                                                ? 'default'
                                                : 'secondary'
                                        }
                                    >
                                        {criterion.mandatory
                                            ? 'Mandatory'
                                            : 'Comparison'}
                                    </Badge>
                                </div>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    {criterion.assessment_rule ||
                                        'Assessment rule not set'}
                                </p>
                            </div>
                        ))}
                    </div>
                </Section>
                <Section
                    title={
                        can.supplier
                            ? 'Your participation'
                            : 'Supplier participation'
                    }
                >
                    <div className="grid gap-2">
                        {participation.length === 0 && (
                            <p className="text-muted-foreground text-sm">
                                No suppliers invited yet.
                            </p>
                        )}
                        {participation.map((entry) => (
                            <div
                                key={entry.uuid}
                                className="flex flex-wrap items-center gap-3 rounded-lg border p-3"
                            >
                                <span className="flex-1 font-medium">
                                    {entry.supplier_name}
                                </span>
                                <Status value={entry.status} />
                                {can.supplier && open && (
                                    <ActionDialog
                                        title={
                                            entry.status === 'declined'
                                                ? 'Resume participation'
                                                : [
                                                        'responded',
                                                        'withdrawn',
                                                    ].includes(entry.status)
                                                  ? 'Withdraw quotation'
                                                  : 'Decline invitation'
                                        }
                                        triggerVariant={
                                            entry.status === 'declined'
                                                ? 'outline'
                                                : 'ghost'
                                        }
                                        action={respond(request.uuid)}
                                        submit="Confirm"
                                    >
                                        <input
                                            type="hidden"
                                            name="operation"
                                            value={
                                                entry.status === 'declined'
                                                    ? 'participate'
                                                    : [
                                                            'responded',
                                                            'withdrawn',
                                                        ].includes(entry.status)
                                                      ? 'withdraw'
                                                      : 'decline'
                                            }
                                        />
                                        <input
                                            type="hidden"
                                            name="lock_version"
                                            value={
                                                [
                                                    'responded',
                                                    'withdrawn',
                                                ].includes(entry.status)
                                                    ? entry.quotation_version
                                                    : entry.lock_version
                                            }
                                        />
                                        <TextField
                                            label="Reason"
                                            name="reason"
                                        />
                                    </ActionDialog>
                                )}
                            </div>
                        ))}
                    </div>
                    {can.supplier &&
                        open &&
                        participation[0]?.status !== 'declined' && (
                            <div className="mt-4 border-t pt-4">
                                <QuotationEditor
                                    request={request}
                                    revisions={revisions}
                                />
                            </div>
                        )}
                    {can.internal &&
                        !can.commercial &&
                        request.status !== 'draft' && (
                            <p className="text-muted-foreground mt-4 flex items-center gap-2 border-t pt-4 text-sm">
                                <LockKeyhole className="size-4" />
                                Commercial contents are sealed until the
                                deadline. Supplier drafts remain private.
                            </p>
                        )}
                </Section>
                {can.supplier && (
                    <Section
                        title="Your revisions & receipts"
                        action={
                            draft && open ? (
                                <Upload
                                    request={request}
                                    type="revision"
                                    uuid={draft.uuid}
                                />
                            ) : undefined
                        }
                    >
                        <div className="grid gap-4">
                            {revisions.map((revision) => (
                                <div
                                    key={revision.uuid}
                                    className="grid gap-3 rounded-lg border p-4"
                                >
                                    <div className="flex flex-wrap justify-between gap-3">
                                        <h3 className="font-medium">
                                            Revision {revision.revision_number}
                                        </h3>
                                        <Status value={revision.status} />
                                    </div>
                                    {revision.status === 'submitted' ? (
                                        <dl className="grid gap-3 text-sm sm:grid-cols-3">
                                            <div className="grid gap-1">
                                                <dt className="text-muted-foreground">
                                                    Submitted
                                                </dt>
                                                <dd>
                                                    {timestamp(
                                                        revision.submitted_at,
                                                        request.timezone,
                                                    )}
                                                </dd>
                                            </div>
                                            <div className="grid gap-1">
                                                <dt className="text-muted-foreground">
                                                    Total
                                                </dt>
                                                <dd className="font-medium tabular-nums">
                                                    {revision.currency_code}{' '}
                                                    {revision.total_amount}
                                                </dd>
                                            </div>
                                            <div className="grid gap-1">
                                                <dt className="text-muted-foreground">
                                                    Receipt
                                                </dt>
                                                <dd className="font-mono text-xs break-all">
                                                    {revision.uuid}
                                                </dd>
                                            </div>
                                        </dl>
                                    ) : (
                                        <p className="text-muted-foreground text-sm">
                                            Saved draft. Your previous submitted
                                            revision stays current until this
                                            revision is submitted.
                                        </p>
                                    )}
                                    <Files files={revision.attachments} />
                                </div>
                            ))}
                            {revisions.length === 0 && (
                                <p className="text-muted-foreground text-sm">
                                    Prepare a quotation to begin. No quotation
                                    has been submitted.
                                </p>
                            )}
                        </div>
                    </Section>
                )}
                {can.commercial && (
                    <>
                        <EvaluationPanel
                            request={request}
                            revisions={revisions}
                            rounds={rounds}
                            buyer={can.buyer}
                        />
                        <Section title="Submitted supporting documents">
                            <div className="grid gap-4">
                                {revisions.length === 0 && (
                                    <p className="text-muted-foreground text-sm">
                                        No submitted documents.
                                    </p>
                                )}
                                {revisions.map((revision) => (
                                    <div key={revision.uuid}>
                                        <h3 className="mb-2 text-sm font-medium">
                                            {revision.supplier_name_snapshot} ·
                                            Revision {revision.revision_number}
                                        </h3>
                                        <Files files={revision.attachments} />
                                    </div>
                                ))}
                            </div>
                        </Section>
                    </>
                )}
                <Section
                    title="Clarifications"
                    description="Private conversations stay with the invited supplier. General answers are published separately."
                    action={
                        canClarify ? (
                            <ActionDialog
                                title="New clarification"
                                action={clarify(request.uuid)}
                                submit="Post question or answer"
                            >
                                <input
                                    type="hidden"
                                    name="lock_version"
                                    value="0"
                                />
                                <Field
                                    label="Subject"
                                    name="subject"
                                    required
                                />
                                {can.buyer && (
                                    <>
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <SelectField
                                                label="Visibility"
                                                name="visibility"
                                                defaultValue="private"
                                                options={[
                                                    {
                                                        value: 'private',
                                                        label: 'Private supplier conversation',
                                                    },
                                                    {
                                                        value: 'all_invited',
                                                        label: 'General answer to all invitees',
                                                    },
                                                ]}
                                            />
                                            <SelectField
                                                label="Supplier (for private conversation)"
                                                name="supplier_uuid"
                                                placeholder="Choose supplier"
                                                options={[
                                                    {
                                                        value: '',
                                                        label: 'No supplier',
                                                    },
                                                    ...participation.map(
                                                        (entry) => ({
                                                            value: entry.supplier_uuid,
                                                            label: entry.supplier_name,
                                                        }),
                                                    ),
                                                ]}
                                            />
                                        </div>
                                        <SelectField
                                            label="Material to evaluation"
                                            name="material"
                                            defaultValue="1"
                                            options={[
                                                {
                                                    value: '1',
                                                    label: 'Yes — must resolve before recommendation',
                                                },
                                                { value: '0', label: 'No' },
                                            ]}
                                        />
                                    </>
                                )}
                                <TextField
                                    label="Message"
                                    name="body"
                                    required
                                />
                            </ActionDialog>
                        ) : undefined
                    }
                >
                    <div className="grid gap-4">
                        {threads.length === 0 && (
                            <EmptyState title="No clarification conversations" />
                        )}
                        {threads.map((thread) => (
                            <div
                                key={thread.uuid}
                                className="rounded-lg border p-4"
                            >
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <h3 className="font-medium">
                                        {thread.subject}
                                    </h3>
                                    <div className="flex flex-wrap gap-2">
                                        {thread.material && (
                                            <Badge
                                                variant="outline"
                                                className="rounded-full border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300"
                                            >
                                                Material
                                            </Badge>
                                        )}
                                        <Status value={thread.visibility} />
                                        <Status value={thread.status} />
                                    </div>
                                </div>
                                {thread.material && (
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        Material to evaluation — must be
                                        resolved before recommendation.
                                    </p>
                                )}
                                <div className="my-4 grid gap-3">
                                    {thread.messages.map((message) => (
                                        <div
                                            key={message.uuid}
                                            className="bg-muted/40 grid gap-2 rounded-md border-l-2 p-3"
                                        >
                                            <p className="text-muted-foreground text-xs">
                                                {message.author} ·{' '}
                                                {timestamp(
                                                    message.created_at,
                                                    request.timezone,
                                                )}
                                            </p>
                                            <p className="text-sm whitespace-pre-wrap">
                                                {message.body}
                                            </p>
                                            {canClarify &&
                                                message.canAttach && (
                                                    <Upload
                                                        request={request}
                                                        type="message"
                                                        uuid={message.uuid}
                                                    />
                                                )}
                                            {message.attachments.length > 0 && (
                                                <Files
                                                    files={message.attachments}
                                                />
                                            )}
                                        </div>
                                    ))}
                                </div>
                                {canClarify && thread.status === 'open' && (
                                    <div className="flex flex-wrap gap-2 border-t pt-3">
                                        {(can.buyer ||
                                            thread.visibility ===
                                                'private') && (
                                            <ActionDialog
                                                title="Reply"
                                                action={clarify(request.uuid)}
                                                submit="Post reply"
                                            >
                                                <input
                                                    type="hidden"
                                                    name="thread_uuid"
                                                    value={thread.uuid}
                                                />
                                                <input
                                                    type="hidden"
                                                    name="lock_version"
                                                    value={thread.lock_version}
                                                />
                                                <input
                                                    type="hidden"
                                                    name="operation"
                                                    value="reply"
                                                />
                                                <TextField
                                                    label="Reply"
                                                    name="body"
                                                    required
                                                />
                                            </ActionDialog>
                                        )}
                                        {can.buyer && (
                                            <ActionDialog
                                                title="Mark resolved"
                                                triggerVariant="ghost"
                                                action={clarify(request.uuid)}
                                                submit="Resolve"
                                            >
                                                <input
                                                    type="hidden"
                                                    name="thread_uuid"
                                                    value={thread.uuid}
                                                />
                                                <input
                                                    type="hidden"
                                                    name="lock_version"
                                                    value={thread.lock_version}
                                                />
                                                <input
                                                    type="hidden"
                                                    name="operation"
                                                    value="resolve"
                                                />
                                                <p className="text-muted-foreground text-sm">
                                                    Confirm this question is
                                                    resolved and its answer is
                                                    recorded.
                                                </p>
                                            </ActionDialog>
                                        )}
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                </Section>
                {can.buyer && (
                    <Section
                        title="Request actions"
                        description="Every change is recorded with a reason in the audit history."
                    >
                        <div className="flex flex-wrap gap-2">
                            {open && (
                                <ActionDialog
                                    title="Extend deadline"
                                    action={change(request.uuid)}
                                    transform={(data) => ({
                                        ...data,
                                        deadline_at: new Date(
                                            String(
                                                (
                                                    data as Record<
                                                        string,
                                                        unknown
                                                    >
                                                ).deadline_at,
                                            ),
                                        ).toISOString(),
                                    })}
                                >
                                    <input
                                        type="hidden"
                                        name="operation"
                                        value="extend"
                                    />
                                    <input
                                        type="hidden"
                                        name="lock_version"
                                        value={request.lock_version}
                                    />
                                    <Field
                                        label="New deadline (browser local time)"
                                        type="datetime-local"
                                        name="deadline_at"
                                        required
                                    />
                                    <TextField
                                        label="Reason for extension"
                                        name="reason"
                                        required
                                    />
                                </ActionDialog>
                            )}
                            {['draft', 'open', 'closed', 'evaluating'].includes(
                                request.status,
                            ) && (
                                <ActionDialog
                                    title="Cancel request"
                                    triggerVariant="destructive"
                                    submitVariant="destructive"
                                    action={change(request.uuid)}
                                    submit="Confirm cancellation"
                                >
                                    <input
                                        type="hidden"
                                        name="operation"
                                        value="cancel"
                                    />
                                    <input
                                        type="hidden"
                                        name="lock_version"
                                        value={request.lock_version}
                                    />
                                    <TextField
                                        label="Cancellation reason"
                                        name="reason"
                                        required
                                    />
                                </ActionDialog>
                            )}
                            <ActionDialog
                                title="Clone as new draft"
                                action={change(request.uuid)}
                            >
                                <input
                                    type="hidden"
                                    name="operation"
                                    value="clone"
                                />
                                <input
                                    type="hidden"
                                    name="lock_version"
                                    value={request.lock_version}
                                />
                                <TextField
                                    label="Reason for new procurement"
                                    name="reason"
                                    required
                                />
                            </ActionDialog>
                        </div>
                    </Section>
                )}
                {audit && (
                    <Section
                        title="Audit history"
                        description="Recorded actions are retained with actor and timestamp."
                    >
                        <div className="grid">
                            {audit.data.map((event) => (
                                <div
                                    key={event.uuid}
                                    className="flex flex-wrap justify-between gap-2 border-b py-3 text-sm first:pt-0 last:border-0"
                                >
                                    <div className="grid gap-1">
                                        <p className="font-medium capitalize">
                                            {event.action
                                                .replaceAll('.', ' ')
                                                .replaceAll('_', ' ')}
                                        </p>
                                        <p className="text-muted-foreground">
                                            {event.actor_label}
                                        </p>
                                        {Object.keys(event.safe_details)
                                            .length > 0 && (
                                            <dl className="text-muted-foreground flex flex-wrap gap-x-4 gap-y-1 text-xs">
                                                {Object.entries(
                                                    event.safe_details,
                                                ).map(([key, value]) => (
                                                    <div
                                                        key={key}
                                                        className="flex gap-1"
                                                    >
                                                        <dt className="capitalize">
                                                            {key.replaceAll(
                                                                '_',
                                                                ' ',
                                                            )}
                                                            :
                                                        </dt>
                                                        <dd className="text-foreground">
                                                            {typeof value ===
                                                            'object'
                                                                ? JSON.stringify(
                                                                      value,
                                                                  )
                                                                : String(value)}
                                                        </dd>
                                                    </div>
                                                ))}
                                            </dl>
                                        )}
                                    </div>
                                    <span className="text-muted-foreground text-xs whitespace-nowrap">
                                        {timestamp(
                                            event.occurred_at,
                                            request.timezone,
                                        )}
                                    </span>
                                </div>
                            ))}
                        </div>
                        <div className="mt-4 border-t pt-4">
                            <Pagination page={audit} />
                        </div>
                    </Section>
                )}
            </PageContainer>
        </>
    );
}
Show.layout = {
    breadcrumbs: [
        { title: 'Procurement', href: index() },
        { title: 'Request workspace', href: '#' },
    ],
};
