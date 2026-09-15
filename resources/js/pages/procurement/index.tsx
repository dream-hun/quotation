import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowUpRight,
    Clock3,
    FileText,
    MoreHorizontal,
    ShieldCheck,
} from 'lucide-react';
import {
    index,
    show,
} from '@/actions/App/Http/Controllers/ProcurementController';
import { RequestEditor } from '@/components/procurement/request-editor';
import {
    Field,
    Flash,
    Pagination,
    Section,
    SelectField,
    Status,
    timestamp,
} from '@/components/procurement/shared';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
    return (
        <>
            <Head title="Procurement" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-8">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="mb-2 text-xs font-semibold tracking-widest text-emerald-700 uppercase dark:text-emerald-400">
                            {organization?.name ?? 'Quotation workspace'}
                        </p>
                        <h1 className="text-3xl font-semibold tracking-tight">
                            {isSupplier
                                ? 'Your invitations'
                                : 'Procurement overview'}
                        </h1>
                        <p className="text-muted-foreground mt-2">
                            From a clear requirement to a decision you can
                            explain.
                        </p>
                    </div>
                    {canCreate && (
                        <RequestEditor
                            currencies={currencies}
                            approvers={approvers}
                        />
                    )}
                </div>
                <Flash />
                <div className="grid gap-4 sm:grid-cols-3">
                    {[
                        {
                            label: 'Open for quotations',
                            count: counts.open ?? 0,
                            icon: Clock3,
                        },
                        {
                            label: 'Awaiting approval',
                            count: counts.pending_approval ?? 0,
                            icon: FileText,
                        },
                        {
                            label: 'Awarded requests',
                            count: counts.awarded ?? 0,
                            icon: ShieldCheck,
                        },
                    ].map(({ label, count, icon: Icon }) => (
                        <div
                            key={label}
                            className="bg-card flex items-center justify-between rounded-xl border p-5"
                        >
                            <div>
                                <p className="text-muted-foreground text-sm">
                                    {label}
                                </p>
                                <p className="mt-2 text-3xl font-semibold">
                                    {count}
                                </p>
                            </div>
                            <Icon className="size-6 text-emerald-600" />
                        </div>
                    ))}
                </div>
                <Section
                    title={
                        isSupplier ? 'Invited requests' : 'Requests & history'
                    }
                    description="Search and follow each procurement through its lifecycle."
                >
                    <form
                        className="mb-6 grid items-end gap-3 sm:grid-cols-3 lg:grid-cols-6"
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
                            defaultValue={filters.status ?? ''}
                        >
                            <option value="">All statuses</option>
                            {[
                                'draft',
                                'open',
                                'closed',
                                'evaluating',
                                'pending_approval',
                                'awarded',
                                'rejected',
                                'cancelled',
                            ].map((status) => (
                                <option key={status} value={status}>
                                    {status.replaceAll('_', ' ')}
                                </option>
                            ))}
                        </SelectField>
                        {!isSupplier && (
                            <Field
                                label="Supplier"
                                name="supplier"
                                defaultValue={filters.supplier}
                            />
                        )}
                        <Field
                            label="Created from"
                            name="from"
                            type="date"
                            defaultValue={filters.from}
                        />
                        <Field
                            label="Created to"
                            name="to"
                            type="date"
                            defaultValue={filters.to}
                        />
                        <Button type="submit">Apply filters</Button>
                    </form>
                    {requests.data.length === 0 ? (
                        <div className="rounded-lg border border-dashed py-14 text-center">
                            <FileText className="text-muted-foreground mx-auto mb-3 size-8" />
                            <h3 className="font-medium">No requests yet</h3>
                            <p className="text-muted-foreground mt-2 text-sm">
                                {canCreate
                                    ? 'Create your first request to invite suppliers.'
                                    : 'Your administrator or buyer will assign access and invitations here.'}
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="text-muted-foreground border-b text-xs">
                                    <tr>
                                        <th className="pb-3">Request</th>
                                        <th className="pb-3">Status</th>
                                        <th className="pb-3">Deadline</th>
                                        <th className="pb-3">
                                            <span className="sr-only">
                                                Actions
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {requests.data.map((request) => (
                                        <tr
                                            key={request.uuid}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-4 pr-4">
                                                <Link
                                                    href={show(request.uuid)}
                                                    className="font-medium hover:underline"
                                                >
                                                    {request.title}
                                                </Link>
                                                <p className="text-muted-foreground mt-1 text-xs">
                                                    {request.request_number}
                                                </p>
                                            </td>
                                            <td className="pr-4">
                                                <Status
                                                    value={request.status}
                                                />
                                            </td>
                                            <td className="pr-4 whitespace-nowrap">
                                                {timestamp(
                                                    request.deadline_at,
                                                    request.timezone,
                                                )}
                                                <p className="text-muted-foreground text-xs">
                                                    {request.timezone}
                                                </p>
                                            </td>
                                            <td>
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        asChild
                                                    >
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            aria-label={
                                                                'Actions for ' +
                                                                request.title
                                                            }
                                                        >
                                                            <MoreHorizontal className="size-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuItem
                                                            asChild
                                                        >
                                                            <Link
                                                                href={show(
                                                                    request.uuid,
                                                                )}
                                                            >
                                                                Open workspace
                                                                <ArrowUpRight className="size-4" />
                                                            </Link>
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    <div className="mt-5">
                        <Pagination page={requests} />
                    </div>
                </Section>
                {notifications.length > 0 && (
                    <Section title="Recent updates">
                        <div className="grid gap-3">
                            {notifications.map((notice) => (
                                <Link
                                    key={notice.uuid}
                                    href={show(notice.request_uuid)}
                                    className="hover:bg-muted flex items-center justify-between gap-3 rounded-md p-2 text-sm"
                                >
                                    <span className="capitalize">
                                        {notice.event_type
                                            .replaceAll('.', ' ')
                                            .replaceAll('_', ' ')}
                                    </span>
                                    <span className="text-muted-foreground text-xs">
                                        {timestamp(notice.created_at)}
                                    </span>
                                </Link>
                            ))}
                        </div>
                    </Section>
                )}
            </div>
        </>
    );
}
Index.layout = { breadcrumbs: [{ title: 'Procurement', href: index() }] };
