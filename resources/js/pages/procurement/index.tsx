import { Head, Link, router } from '@inertiajs/react';
import { Bell, Clock3, FileText, ShieldCheck } from 'lucide-react';
import {
    index,
    show,
} from '@/actions/App/Http/Controllers/ProcurementController';
import { RequestEditor } from '@/components/procurement/request-editor';
import {
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
    Status,
    timestamp,
} from '@/components/procurement/shared';
import { Button } from '@/components/ui/button';
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
    notifications: {
        uuid: string;
        event_type: string;
        request_uuid: string;
        created_at: string;
    }[];
};

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
}: Props) {
    const hasFilters = Object.values(filters).some(Boolean);
    return (
        <>
            <Head title="Procurement" />
            <PageContainer>
                <PageHeader
                    eyebrow={organization?.name ?? 'Quotation workspace'}
                    title={
                        isSupplier ? 'Your invitations' : 'Procurement overview'
                    }
                    description="From a clear requirement to a decision you can explain."
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
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <StatCard
                        label="Open for quotations"
                        value={counts.open ?? 0}
                        icon={Clock3}
                    />
                    <StatCard
                        label="Awaiting approval"
                        value={counts.pending_approval ?? 0}
                        icon={FileText}
                    />
                    <StatCard
                        label="Awarded requests"
                        value={counts.awarded ?? 0}
                        icon={ShieldCheck}
                    />
                </div>
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
                                    label:
                                        status[0].toUpperCase() +
                                        status.slice(1).replaceAll('_', ' '),
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
                                            <p className="text-muted-foreground text-xs">
                                                {request.request_number}
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            <Status value={request.status} />
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
                                    className="hover:bg-muted flex items-center justify-between gap-3 rounded-md px-2 py-2 text-sm"
                                >
                                    <span className="flex items-center gap-3 capitalize">
                                        <Bell className="text-muted-foreground size-4" />
                                        {notice.event_type
                                            .replaceAll('.', ' ')
                                            .replaceAll('_', ' ')}
                                    </span>
                                    <span className="text-muted-foreground text-xs whitespace-nowrap">
                                        {timestamp(notice.created_at)}
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
