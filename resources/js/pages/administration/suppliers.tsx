import { Head, Link, router } from '@inertiajs/react';
import { Building2, CircleOff, Pencil, Plus, Users } from 'lucide-react';
import { useState } from 'react';
import { store } from '@/actions/App/Http/Controllers/AdministrationController';
import { index } from '@/actions/App/Http/Controllers/SupplierController';
import { index as users } from '@/actions/App/Http/Controllers/UserAccountController';
import {
    ActionDialog,
    DataTableFrame,
    Field,
    Flash,
    PageContainer,
    PageHeader,
    Pagination,
    RowActions,
    SelectField,
    Status,
    TableToolbar,
} from '@/components/procurement/shared';
import { Button } from '@/components/ui/button';
import {
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { PageData } from '@/types/procurement';

type Supplier = {
    uuid: string;
    legal_name: string;
    contact_email: string;
    status: string;
    lock_version: number;
    members_count: number;
    invitations_count: number;
};

type Props = {
    suppliers: PageData<Supplier>;
    filters: { q?: string; status?: string };
};

function SupplierEditor({
    supplier,
    open,
    onOpenChange,
}: {
    supplier?: Supplier;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
}) {
    return (
        <ActionDialog
            title={supplier ? 'Edit supplier' : 'Add supplier'}
            trigger={
                supplier ? null : (
                    <Button>
                        <Plus />
                        Add supplier
                    </Button>
                )
            }
            open={open}
            onOpenChange={onOpenChange}
            action={store()}
            submit={supplier ? 'Save changes' : 'Add supplier'}
            description="Supplier companies can be invited to requests. Add representatives from Users."
        >
            <input type="hidden" name="type" value="supplier" />
            <input type="hidden" name="uuid" value={supplier?.uuid ?? ''} />
            <input
                type="hidden"
                name="lock_version"
                value={supplier?.lock_version ?? 0}
            />
            <Field
                label="Legal name"
                name="legal_name"
                defaultValue={supplier?.legal_name}
                autoComplete="organization"
                required
            />
            <Field
                label="Contact email"
                name="contact_email"
                type="email"
                defaultValue={supplier?.contact_email}
                autoComplete="off"
                required
            />
            <SelectField
                label="Supplier status"
                name="status"
                defaultValue={supplier?.status ?? 'active'}
                options={[
                    { value: 'active', label: 'Active' },
                    { value: 'inactive', label: 'Inactive' },
                ]}
            />
        </ActionDialog>
    );
}

function SupplierStatusDialog({
    supplier,
    open,
    onOpenChange,
}: {
    supplier: Supplier;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const deactivating = supplier.status === 'active';
    return (
        <ActionDialog
            title={
                deactivating
                    ? `Deactivate ${supplier.legal_name}?`
                    : `Activate ${supplier.legal_name}?`
            }
            trigger={null}
            open={open}
            onOpenChange={onOpenChange}
            action={store()}
            submit={deactivating ? 'Deactivate supplier' : 'Activate supplier'}
            submitVariant={deactivating ? 'destructive' : 'default'}
            description={
                deactivating
                    ? 'The supplier can no longer be invited or given new representatives. Existing quotations and history are kept.'
                    : 'The supplier can be invited to new requests again.'
            }
        >
            <input type="hidden" name="type" value="supplier" />
            <input type="hidden" name="uuid" value={supplier.uuid} />
            <input
                type="hidden"
                name="lock_version"
                value={supplier.lock_version}
            />
            <input
                type="hidden"
                name="legal_name"
                value={supplier.legal_name}
            />
            <input
                type="hidden"
                name="contact_email"
                value={supplier.contact_email}
            />
            <input
                type="hidden"
                name="status"
                value={deactivating ? 'inactive' : 'active'}
            />
        </ActionDialog>
    );
}

function SupplierRowActions({ supplier }: { supplier: Supplier }) {
    const [dialog, setDialog] = useState<'edit' | 'status' | null>(null);
    const close = (open: boolean) => !open && setDialog(null);
    return (
        <>
            <RowActions label={`Actions for ${supplier.legal_name}`}>
                <DropdownMenuLabel className="truncate">
                    {supplier.legal_name}
                </DropdownMenuLabel>
                <DropdownMenuItem onSelect={() => setDialog('edit')}>
                    <Pencil />
                    Edit supplier
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link href={users({ query: { supplier: supplier.uuid } })}>
                        <Users />
                        View representatives
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    variant={
                        supplier.status === 'active' ? 'destructive' : 'default'
                    }
                    onSelect={() => setDialog('status')}
                >
                    {supplier.status === 'active' ? (
                        <CircleOff />
                    ) : (
                        <Building2 />
                    )}
                    {supplier.status === 'active'
                        ? 'Deactivate supplier'
                        : 'Activate supplier'}
                </DropdownMenuItem>
            </RowActions>
            <SupplierEditor
                supplier={supplier}
                open={dialog === 'edit'}
                onOpenChange={close}
            />
            <SupplierStatusDialog
                supplier={supplier}
                open={dialog === 'status'}
                onOpenChange={close}
            />
        </>
    );
}

export default function Suppliers({ suppliers, filters }: Props) {
    return (
        <>
            <Head title="Suppliers" />
            <PageContainer>
                <PageHeader
                    title="Suppliers"
                    description="Companies that can be invited to submit quotations."
                    actions={<SupplierEditor />}
                />
                <Flash />
                <TableToolbar
                    search={filters.q}
                    searchLabel="Search name or email"
                    filters={[
                        {
                            name: 'status',
                            label: 'Statuses',
                            value: filters.status,
                            options: [
                                { value: 'active', label: 'Active' },
                                { value: 'inactive', label: 'Inactive' },
                            ],
                        },
                    ]}
                    onChange={(values) =>
                        router.get(index.url(), values, {
                            preserveState: true,
                            replace: true,
                        })
                    }
                />
                <DataTableFrame>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Supplier</TableHead>
                                <TableHead>Contact</TableHead>
                                <TableHead className="text-right">
                                    Representatives
                                </TableHead>
                                <TableHead className="text-right">
                                    Invitations
                                </TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="w-12">
                                    <span className="sr-only">Actions</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {suppliers.data.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="text-muted-foreground h-24 text-center"
                                    >
                                        No suppliers match these filters.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                suppliers.data.map((supplier) => (
                                    <TableRow key={supplier.uuid}>
                                        <TableCell className="font-medium">
                                            {supplier.legal_name}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {supplier.contact_email}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {supplier.members_count}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {supplier.invitations_count}
                                        </TableCell>
                                        <TableCell>
                                            <Status value={supplier.status} />
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <SupplierRowActions
                                                supplier={supplier}
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </DataTableFrame>
                <Pagination page={suppliers} />
            </PageContainer>
        </>
    );
}

Suppliers.layout = {
    breadcrumbs: [{ title: 'Suppliers', href: index() }],
};
