import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, FileDown, LockKeyhole } from 'lucide-react';
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
    Field,
    Flash,
    Pagination,
    Section,
    SelectField,
    Status,
    TextField,
    timestamp,
} from '@/components/procurement/shared';
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
                    className="flex flex-wrap items-center justify-between gap-3 rounded-md border p-3 text-sm"
                >
                    {file.scan_status === 'clean' ? (
                        <a
                            href={download.url(file.uuid)}
                            className="flex items-center gap-2 hover:underline"
                        >
                            <FileDown className="size-4" />
                            {file.original_name}
                        </a>
                    ) : (
                        <span>{file.original_name}</span>
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
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-8">
                <Link
                    href={index()}
                    className="text-muted-foreground flex w-fit items-center gap-2 text-sm"
                >
                    <ArrowLeft className="size-4" />
                    All requests
                </Link>
                <div className="flex flex-wrap justify-between gap-4">
                    <div>
                        <div className="mb-3 flex flex-wrap items-center gap-3">
                            <span className="text-muted-foreground text-xs font-semibold tracking-wider">
                                {request.request_number}
                            </span>
                            <Status value={request.status} />
                        </div>
                        <h1 className="text-3xl font-semibold tracking-tight">
                            {request.title}
                        </h1>
                        <p className="text-muted-foreground mt-2 text-sm">
                            Deadline:{' '}
                            {timestamp(request.deadline_at, request.timezone)} ·{' '}
                            {request.timezone}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-start gap-2">
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
                                    action={publish(request.uuid)}
                                    submit="Confirm invitations"
                                    description="Publication freezes requirements, currency and criteria. Choose suppliers who should receive this request."
                                >
                                    <input
                                        type="hidden"
                                        name="lock_version"
                                        value={request.lock_version}
                                    />
                                    <div className="grid gap-3">
                                        {suppliers.map((supplier) => (
                                            <label
                                                key={supplier.uuid}
                                                className="flex items-center gap-3 rounded-lg border p-3 text-sm"
                                            >
                                                <input
                                                    type="checkbox"
                                                    name="supplier_uuids[]"
                                                    value={supplier.uuid}
                                                />
                                                {supplier.legal_name}
                                            </label>
                                        ))}
                                    </div>
                                </ActionDialog>
                            )}
                        {can.buyer &&
                            can.commercial &&
                            ['open', 'closed'].includes(request.status) && (
                                <ActionDialog
                                    title="Start evaluation"
                                    action={start(request.uuid)}
                                    submit="Open evaluation"
                                >
                                    <input
                                        type="hidden"
                                        name="lock_version"
                                        value={request.lock_version}
                                    />
                                    <p className="text-sm">
                                        Open a review of each supplierâ€™s
                                        latest submitted quotation. Withdrawn
                                        and expired offers will remain visible
                                        as excluded.
                                    </p>
                                </ActionDialog>
                            )}
                    </div>
                </div>
                <Flash />
                {request.cancellation_reason && (
                    <p className="rounded-lg border p-4 text-sm">
                        Cancellation reason: {request.cancellation_reason}
                    </p>
                )}
                <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
                    <Section
                        title="Purchase requirements"
                        description={request.description ?? undefined}
                    >
                        <div className="grid gap-4">
                            <p className="text-sm">
                                <span className="text-muted-foreground">
                                    Deliver to:
                                </span>{' '}
                                {request.delivery_destination || 'Not set'} ·{' '}
                                {request.currency_code || 'Currency not set'}
                            </p>
                            {request.items.map((item) => (
                                <div
                                    key={item.uuid}
                                    className="rounded-lg border p-4"
                                >
                                    <div className="flex justify-between gap-4">
                                        <h3 className="font-medium">
                                            {item.description}
                                        </h3>
                                        <span className="text-sm whitespace-nowrap">
                                            {item.quantity} {item.unit}
                                        </span>
                                    </div>
                                    <p className="text-muted-foreground mt-2 text-sm">
                                        {item.specification ||
                                            'Specification not set'}
                                    </p>
                                </div>
                            ))}
                            {request.items.length === 0 && (
                                <p className="text-muted-foreground text-sm">
                                    Add items to complete this draft.
                                </p>
                            )}
                        </div>
                    </Section>
                    <div className="grid content-start gap-6">
                        <Section title="Decision controls">
                            <p className="text-sm">Assigned approver</p>
                            <p className="mt-1 font-medium">
                                {request.approver_name ?? 'Not assigned'}
                            </p>
                            <p className="text-muted-foreground mt-4 text-sm">
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
                    <div className="grid gap-4 sm:grid-cols-2">
                        {request.criteria.map((criterion) => (
                            <div
                                key={criterion.uuid}
                                className="rounded-lg border p-4"
                            >
                                <h3 className="font-medium">
                                    {criterion.name}{' '}
                                    <span className="text-muted-foreground text-xs">
                                        {criterion.mandatory
                                            ? 'Mandatory'
                                            : 'Comparison'}
                                    </span>
                                </h3>
                                <p className="text-muted-foreground mt-2 text-sm">
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
                    <div className="grid gap-3">
                        {participation.map((entry) => (
                            <div
                                key={entry.uuid}
                                className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-4"
                            >
                                <span className="font-medium">
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
                            <div className="mt-5">
                                <QuotationEditor
                                    request={request}
                                    revisions={revisions}
                                />
                            </div>
                        )}
                    {can.internal &&
                        !can.commercial &&
                        request.status !== 'draft' && (
                            <p className="text-muted-foreground mt-5 flex items-center gap-2 text-sm">
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
                                        <p className="text-sm">
                                            Receipt: {revision.uuid}
                                            <br />
                                            Submitted{' '}
                                            {timestamp(
                                                revision.submitted_at,
                                                request.timezone,
                                            )}{' '}
                                            · {revision.currency_code}{' '}
                                            {revision.total_amount}
                                            <br />
                                            {
                                                revision.supplier_name_snapshot
                                            } ·{' '}
                                            {request.request_number}
                                        </p>
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
                            <div className="grid gap-5">
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
                                        <SelectField
                                            label="Visibility"
                                            name="visibility"
                                        >
                                            <option value="private">
                                                Private supplier conversation
                                            </option>
                                            <option value="all_invited">
                                                General answer to all invitees
                                            </option>
                                        </SelectField>
                                        <SelectField
                                            label="Supplier (for private conversation)"
                                            name="supplier_uuid"
                                        >
                                            <option value="">
                                                Choose supplier
                                            </option>
                                            {participation.map((entry) => (
                                                <option
                                                    key={entry.supplier_uuid}
                                                    value={entry.supplier_uuid}
                                                >
                                                    {entry.supplier_name}
                                                </option>
                                            ))}
                                        </SelectField>
                                        <SelectField
                                            label="Material to evaluation"
                                            name="material"
                                        >
                                            <option value="1">
                                                Yes â€” must resolve before
                                                recommendation
                                            </option>
                                            <option value="0">No</option>
                                        </SelectField>
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
                            <p className="text-muted-foreground text-sm">
                                No clarification conversations.
                            </p>
                        )}
                        {threads.map((thread) => (
                            <div
                                key={thread.uuid}
                                className="rounded-lg border p-4"
                            >
                                <div className="flex flex-wrap justify-between gap-3">
                                    <h3 className="font-medium">
                                        {thread.subject}
                                    </h3>
                                    <div className="flex gap-2">
                                        <Status value={thread.visibility} />
                                        <Status value={thread.status} />
                                    </div>
                                </div>
                                {thread.material && (
                                    <p className="mt-2 text-xs text-amber-700 dark:text-amber-300">
                                        Material to evaluation
                                    </p>
                                )}
                                <div className="my-4 grid gap-4">
                                    {thread.messages.map((message) => (
                                        <div
                                            key={message.uuid}
                                            className="border-l-2 pl-4"
                                        >
                                            <p className="text-muted-foreground text-xs">
                                                {message.author} ·{' '}
                                                {timestamp(
                                                    message.created_at,
                                                    request.timezone,
                                                )}
                                            </p>
                                            <p className="mt-1 text-sm whitespace-pre-wrap">
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
                                    <div className="flex gap-2">
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
                                                <p className="text-sm">
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
                    <Section title="Request actions">
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
                        <div className="grid gap-3">
                            {audit.data.map((event) => (
                                <div
                                    key={event.uuid}
                                    className="flex flex-wrap justify-between gap-2 border-b pb-3 text-sm"
                                >
                                    <div>
                                        <p>
                                            {event.action.replaceAll('.', ' ')}
                                        </p>
                                        <p className="text-muted-foreground">
                                            {event.actor_label}
                                        </p>
                                        {Object.keys(event.safe_details)
                                            .length > 0 && (
                                            <p className="text-muted-foreground text-xs">
                                                {JSON.stringify(
                                                    event.safe_details,
                                                )}
                                            </p>
                                        )}
                                    </div>
                                    <span className="text-muted-foreground">
                                        {timestamp(
                                            event.occurred_at,
                                            request.timezone,
                                        )}
                                    </span>
                                </div>
                            ))}
                        </div>
                        <div className="mt-5">
                            <Pagination page={audit} />
                        </div>
                    </Section>
                )}
            </div>
        </>
    );
}
Show.layout = {
    breadcrumbs: [
        { title: 'Procurement', href: index() },
        { title: 'Request workspace', href: '#' },
    ],
};
