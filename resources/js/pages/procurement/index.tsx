import { Head, Link, router } from '@inertiajs/react';
import {
    Bell,
    ChevronRight,
    FileText,
    Send,
    Stamp,
    Trophy,
} from 'lucide-react';
import {
    index,
    show,
} from '@/actions/App/Http/Controllers/ProcurementController';
import { RequestEditor } from '@/components/procurement/request-editor';
import {
    DataTableFrame,
    DateField,
    EmptyState,
    Field,
    Flash,
    PageContainer,
    PageHeader,
    Pagination,
    Section,
    SelectField,
    StatCard,
    StatGrid,
    Status,
    statusLabel,
    statusTone,
    timestamp,
} from '@/components/procurement/shared';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type {
    Choice,
    Currency,
    PageData,
    Procurement,
} from '@/types/procurement';

type Props = {
    requests: PageData<Procurement>;
    counts: Record<string, number>;
    filters: Record<string, string>;
    canCreate: boolean;
    isSupplier: boolean;
    organization: { name: string; timezone: string } | null;
    currencies: Currency[];
    approvers: Choice[];
    approachingDeadlines: WorkList;
    assignedTasks: WorkList;
    trends: {
        weeks: string[];
        published: number[];
        approvals: number[];
        awards: number[];
    };
    notifications: {
        uuid: string;
        event_type: string;
        request_uuid: string;
        created_at: string;
    }[];
};

type WorkList = {
    total: number;
    data: (Pick<
        Procurement,
        | 'uuid'
        | 'request_number'
        | 'title'
        | 'status'
        | 'deadline_at'
        | 'timezone'
    > & { action?: string })[];
};

function WorkItems({ items, empty }: { items: WorkList; empty: string }) {
    return items.total === 0 ? (
        <p className="text-muted-foreground py-2 text-sm">{empty}</p>
    ) : (
        <div className="grid gap-3">
            <ul className="-mx-4 divide-y border-y md:-mx-5">
                {items.data.map((request) => (
                    <li key={request.uuid}>
                        <Link
                            href={show(request.uuid)}
                            className="group hover:bg-muted/60 focus-visible:ring-ring flex items-center gap-4 px-4 py-3 transition-colors focus-visible:ring-2 focus-visible:outline-none focus-visible:ring-inset md:px-5"
                        >
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-medium">
                                    {request.title}
                                </p>
                                <p className="text-muted-foreground mt-0.5 truncate text-xs">
                                    <span className="font-mono">
                                        {request.request_number}
                                    </span>
                                    {request.deadline_at &&
                                        ` · closes ${timestamp(request.deadline_at, request.timezone)}`}
                                </p>
                            </div>
                            {request.action ? (
                                <span className="hidden shrink-0 text-xs font-medium sm:inline">
                                    {request.action}
                                </span>
                            ) : (
                                <Status value={request.status} />
                            )}
                            <ChevronRight
                                aria-hidden="true"
                                className="text-muted-foreground group-hover:text-foreground size-4 shrink-0 transition-colors"
                            />
                        </Link>
                    </li>
                ))}
            </ul>
            {items.total > items.data.length && (
                <p className="text-muted-foreground text-xs">
                    Showing {items.data.length} of {items.total}
                </p>
            )}
        </div>
    );
}

const pipelineColors = {
    success: 'bg-emerald-600 dark:bg-emerald-400',
    danger: 'bg-red-500 dark:bg-red-400',
    warning: 'bg-amber-500 dark:bg-amber-400',
    info: 'bg-brand',
    neutral: 'bg-slate-400 dark:bg-slate-500',
};

function Pipeline({ counts }: { counts: Record<string, number> }) {
    const segments = statuses
        .map((status) => ({ status, count: counts[status] ?? 0 }))
        .filter((segment) => segment.count > 0);
    const total = segments.reduce((sum, segment) => sum + segment.count, 0);
    if (total === 0) {
        return null;
    }

    return (
        <Section
            title="Pipeline"
            description={`${total} ${total === 1 ? 'request' : 'requests'} by current status.`}
        >
            <div
                className="flex h-2 w-full gap-0.5 overflow-hidden rounded-sm"
                role="img"
                aria-label={segments
                    .map(
                        ({ status, count }) =>
                            `${statusLabel(status)}: ${count}`,
                    )
                    .join(', ')}
            >
                {segments.map(({ status, count }) => (
                    <div
                        key={status}
                        className={pipelineColors[statusTone(status)]}
                        style={{ width: `${(count / total) * 100}%` }}
                    />
                ))}
            </div>
            <ul className="mt-4 flex flex-wrap gap-x-6 gap-y-2">
                {segments.map(({ status, count }) => (
                    <li key={status}>
                        <Link
                            href={index({ query: { status } })}
                            className="text-muted-foreground hover:text-foreground flex items-center gap-2 text-sm transition-colors"
                        >
                            <span
                                aria-hidden="true"
                                className={cn(
                                    'size-2 shrink-0 rounded-sm',
                                    pipelineColors[statusTone(status)],
                                )}
                            />
                            {statusLabel(status)}
                            <span className="text-foreground font-medium tabular-nums">
                                {count}
                            </span>
                        </Link>
                    </li>
                ))}
            </ul>
        </Section>
    );
}

const statuses = [
    'draft',
    'open',
    'closed',
    'evaluating',
    'pending_approval',
    'awarded',
    'rejected',
    'cancelled',
];

export default function Index({
    requests,
    counts,
    filters,
    canCreate,
    isSupplier,
    organization,
    currencies,
    approvers,
    notifications,
    approachingDeadlines,
    assignedTasks,
    trends,
}: Props) {
    const hasFilters = Object.values(filters).some(Boolean);
    return (
        <>
            <Head title="Procurement" />
            <PageContainer>
                <PageHeader
                    eyebrow={organization?.name ?? 'Quotation workspace'}
                    title={isSupplier ? 'Your invitations' : 'Overview'}
                    description={
                        isSupplier
                            ? 'Your invitations, deadlines and submitted quotations.'
                            : 'From a clear requirement to a decision you can explain.'
                    }
                    actions={
                        canCreate && (
                            <RequestEditor
                                currencies={currencies}
                                approvers={approvers}
                            />
                        )
                    }
                />
                <Flash />
                <StatGrid>
                    <StatCard
                        label="Open for quotations"
                        value={counts.open ?? 0}
                        hint={`${approachingDeadlines.total} closing within 7 days`}
                        href={index({ query: { status: 'open' } })}
                        icon={Send}
                        tone="info"
                        trend={{
                            values: trends.published,
                            weeks: trends.weeks,
                            noun: 'published',
                        }}
                    />
                    <StatCard
                        label="Awaiting approval"
                        value={counts.pending_approval ?? 0}
                        hint="With an independent approver"
                        href={index({ query: { status: 'pending_approval' } })}
                        icon={Stamp}
                        tone="warning"
                        trend={{
                            values: trends.approvals,
                            weeks: trends.weeks,
                            noun: 'sent for approval',
                        }}
                    />
                    <StatCard
                        label="Awarded"
                        value={counts.awarded ?? 0}
                        hint="Decisions on record"
                        href={index({ query: { status: 'awarded' } })}
                        icon={Trophy}
                        tone="success"
                        trend={{
                            values: trends.awards,
                            weeks: trends.weeks,
                            noun: 'awarded',
                        }}
                    />
                </StatGrid>
                <div className="grid items-start gap-4 lg:grid-cols-2">
                    <Section
                        title={`Your next actions${assignedTasks.total > 0 ? ` (${assignedTasks.total})` : ''}`}
                        description="Requests waiting on you, oldest first."
                    >
                        <WorkItems
                            items={assignedTasks}
                            empty="Nothing is waiting on you."
                        />
                    </Section>
                    <Section
                        title={`Closing this week${approachingDeadlines.total > 0 ? ` (${approachingDeadlines.total})` : ''}`}
                        description="Open requests with a deadline in the next 7 days."
                    >
                        <WorkItems
                            items={approachingDeadlines}
                            empty="No deadlines in the next 7 days."
                        />
                    </Section>
                </div>
                <Pipeline counts={counts} />
                <Section
                    title={
                        isSupplier ? 'Invited requests' : 'Requests & history'
                    }
                    description="Search and follow each procurement through its lifecycle."
                >
                    <form
                        className="mb-4 grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr_1fr_1fr_auto]"
                        onSubmit={(event) => {
                            event.preventDefault();
                            const data = Object.fromEntries(
                                new FormData(event.currentTarget),
                            ) as Record<string, string>;
                            router.get(index.url(), data, {
                                preserveState: true,
                            });
                        }}
                    >
                        <Field
                            label="Search"
                            name="q"
                            defaultValue={filters.q}
                            placeholder="Number or title"
                        />
                        <SelectField
                            label="Status"
                            name="status"
                            defaultValue={filters.status}
                            options={[
                                { value: '', label: 'All statuses' },
                                ...statuses.map((status) => ({
                                    value: status,
                                    label: statusLabel(status),
                                })),
                            ]}
                        />
                        {!isSupplier && (
                            <Field
                                label="Supplier"
                                name="supplier"
                                defaultValue={filters.supplier}
                                placeholder="Supplier name"
                            />
                        )}
                        <DateField
                            label="Created from"
                            name="from"
                            placeholder="Any date"
                            defaultValue={filters.from}
                        />
                        <DateField
                            label="Created to"
                            name="to"
                            placeholder="Any date"
                            defaultValue={filters.to}
                        />
                        <div className="flex gap-2">
                            <Button type="submit">Apply</Button>
                            {hasFilters && (
                                <Button variant="ghost" asChild>
                                    <Link href={index()}>Clear</Link>
                                </Button>
                            )}
                        </div>
                    </form>
                    {requests.data.length === 0 ? (
                        <EmptyState
                            icon={FileText}
                            title={
                                hasFilters
                                    ? 'No requests match these filters'
                                    : 'No requests yet'
                            }
                            description={
                                hasFilters
                                    ? 'Try a different search or clear the filters.'
                                    : canCreate
                                      ? 'Create your first request to invite suppliers.'
                                      : 'Your administrator or buyer will assign access and invitations here.'
                            }
                        />
                    ) : (
                        <DataTableFrame>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Request</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Deadline</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {requests.data.map((request) => (
                                        <TableRow
                                            key={request.uuid}
                                            className="cursor-pointer"
                                            onClick={() =>
                                                router.visit(show(request.uuid))
                                            }
                                        >
                                            <TableCell className="py-3">
                                                <Link
                                                    href={show(request.uuid)}
                                                    className="font-medium hover:underline"
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                >
                                                    {request.title}
                                                </Link>
                                                <p className="text-muted-foreground font-mono text-xs">
                                                    {request.request_number}
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                <Status
                                                    value={request.status}
                                                />
                                            </TableCell>
                                            <TableCell>
                                                {timestamp(
                                                    request.deadline_at,
                                                    request.timezone,
                                                )}
                                                <p className="text-muted-foreground text-xs">
                                                    {request.timezone}
                                                </p>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </DataTableFrame>
                    )}
                    <div className="mt-4 border-t pt-4">
                        <Pagination page={requests} />
                    </div>
                </Section>
                {notifications.length > 0 && (
                    <Section title="Recent updates">
                        <div className="-mx-2 grid gap-1">
                            {notifications.map((notice) => (
                                <Link
                                    key={notice.uuid}
                                    href={show(notice.request_uuid)}
                                    className="hover:bg-muted focus-visible:ring-ring flex items-center justify-between gap-3 rounded-md px-2 py-2 text-sm transition-colors focus-visible:ring-2 focus-visible:outline-none"
                                >
                                    <span className="flex items-center gap-3 capitalize">
                                        <Bell
                                            aria-hidden="true"
                                            className="text-muted-foreground size-4"
                                        />
                                        {notice.event_type
                                            .replaceAll('.', ' ')
                                            .replaceAll('_', ' ')}
                                    </span>
                                    <span className="text-muted-foreground text-xs whitespace-nowrap">
                                        {timestamp(
                                            notice.created_at,
                                            organization?.timezone,
                                        )}
                                    </span>
                                </Link>
                            ))}
                        </div>
                    </Section>
                )}
            </PageContainer>
        </>
    );
}
Index.layout = { breadcrumbs: [{ title: 'Procurement', href: index() }] };
