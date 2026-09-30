import { Head, router } from '@inertiajs/react';
import { Pencil, Plus, UserCheck, UserX } from 'lucide-react';
import { useState } from 'react';
import { store } from '@/actions/App/Http/Controllers/AdministrationController';
import { index } from '@/actions/App/Http/Controllers/UserAccountController';
import {
    ActionDialog,
    CheckboxField,
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
    TextField,
} from '@/components/procurement/shared';
import { Badge } from '@/components/ui/badge';
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

type Account = {
    uuid: string;
    name: string;
    email: string;
    status: string;
    lock_version: number;
    roles: string[];
    supplier_uuid: string | null;
    supplier_name: string | null;
};

type Choice = { uuid: string; name?: string; legal_name?: string };

type Props = {
    users: PageData<Account>;
    filters: { q?: string; role?: string; status?: string; supplier?: string };
    suppliers: Choice[];
    approvers: Choice[];
};

const internalRoles = ['buyer', 'approver', 'auditor', 'administrator'];

function capitalize(value: string): string {
    return value[0].toUpperCase() + value.slice(1);
}

function AccountFields({ user }: { user: Account }) {
    return (
        <>
            <input type="hidden" name="type" value="user" />
            <input type="hidden" name="uuid" value={user.uuid} />
            <input
                type="hidden"
                name="lock_version"
                value={user.lock_version}
            />
            <input type="hidden" name="name" value={user.name} />
            <input type="hidden" name="email" value={user.email} />
            <input
                type="hidden"
                name="supplier_uuid"
                value={user.supplier_uuid ?? ''}
            />
            {user.roles.map((role) => (
                <input key={role} type="hidden" name="roles[]" value={role} />
            ))}
        </>
    );
}

function AccountEditor({
    user,
    suppliers,
    approvers,
    open,
    onOpenChange,
}: {
    user?: Account;
    suppliers: Choice[];
    approvers: Choice[];
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
}) {
    return (
        <ActionDialog
            title={user ? 'Edit user' : 'Add user'}
            trigger={
                user ? null : (
                    <Button>
                        <Plus />
                        Add user
                    </Button>
                )
            }
            open={open}
            onOpenChange={onOpenChange}
            action={store()}
            submit={user ? 'Save changes' : 'Add user'}
            description="Assign internal roles or a supplier membership. New users receive an email to set their password."
        >
            <input type="hidden" name="type" value="user" />
            <input type="hidden" name="uuid" value={user?.uuid ?? ''} />
            <input
                type="hidden"
                name="lock_version"
                value={user?.lock_version ?? 0}
            />
            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Full name"
                    name="name"
                    defaultValue={user?.name}
                    autoComplete="off"
                    required
                />
                <Field
                    label="Email"
                    name="email"
                    type="email"
                    defaultValue={user?.email}
                    autoComplete="off"
                    required
                />
            </div>
            <SelectField
                label="Account status"
                name="status"
                defaultValue={user?.status ?? 'active'}
                options={[
                    { value: 'active', label: 'Active' },
                    { value: 'disabled', label: 'Disabled' },
                ]}
            />
            <fieldset className="grid gap-3">
                <legend className="mb-2 text-sm font-medium">
                    Internal roles
                </legend>
                <div className="grid gap-2 sm:grid-cols-2">
                    {internalRoles.map((role) => (
                        <CheckboxField
                            key={role}
                            bordered
                            name="roles[]"
                            value={role}
                            defaultChecked={user?.roles.includes(role)}
                            label={capitalize(role)}
                        />
                    ))}
                </div>
            </fieldset>
            <SelectField
                label="Supplier membership (requires no internal roles)"
                name="supplier_uuid"
                defaultValue={user?.supplier_uuid ?? ''}
                options={[
                    { value: '', label: 'Internal account / no supplier' },
                    ...suppliers.map((supplier) => ({
                        value: supplier.uuid,
                        label: supplier.legal_name ?? supplier.uuid,
                    })),
                ]}
            />
            {user && (
                <>
                    <SelectField
                        label="Replacement approver (when removing an assigned approver)"
                        name="replacement_uuid"
                        options={[
                            { value: '', label: 'Not required' },
                            ...approvers
                                .filter((person) => person.uuid !== user.uuid)
                                .map((person) => ({
                                    value: person.uuid,
                                    label: person.name ?? person.uuid,
                                })),
                        ]}
                    />
                    <TextField
                        label="Reason for approver reassignment"
                        name="reason"
                    />
                </>
            )}
        </ActionDialog>
    );
}

function AccountStatusDialog({
    user,
    open,
    onOpenChange,
}: {
    user: Account;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const disabling = user.status === 'active';
    return (
        <ActionDialog
            title={disabling ? `Disable ${user.name}?` : `Enable ${user.name}?`}
            trigger={null}
            open={open}
            onOpenChange={onOpenChange}
            action={store()}
            submit={disabling ? 'Disable user' : 'Enable user'}
            submitVariant={disabling ? 'destructive' : 'default'}
            description={
                disabling
                    ? 'They will be signed out and lose access immediately. Their history is kept. If they are the assigned approver on an open request, use Edit user to choose a replacement first.'
                    : 'They will regain the access their roles grant.'
            }
        >
            <AccountFields user={user} />
            <input
                type="hidden"
                name="status"
                value={disabling ? 'disabled' : 'active'}
            />
        </ActionDialog>
    );
}

function UserRowActions({
    user,
    suppliers,
    approvers,
}: {
    user: Account;
    suppliers: Choice[];
    approvers: Choice[];
}) {
    const [dialog, setDialog] = useState<'edit' | 'status' | null>(null);
    const close = (open: boolean) => !open && setDialog(null);
    return (
        <>
            <RowActions label={`Actions for ${user.name}`}>
                <DropdownMenuLabel className="truncate">
                    {user.name}
                </DropdownMenuLabel>
                <DropdownMenuItem onSelect={() => setDialog('edit')}>
                    <Pencil />
                    Edit user
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    variant={
                        user.status === 'active' ? 'destructive' : 'default'
                    }
                    onSelect={() => setDialog('status')}
                >
                    {user.status === 'active' ? <UserX /> : <UserCheck />}
                    {user.status === 'active' ? 'Disable user' : 'Enable user'}
                </DropdownMenuItem>
            </RowActions>
            <AccountEditor
                user={user}
                suppliers={suppliers}
                approvers={approvers}
                open={dialog === 'edit'}
                onOpenChange={close}
            />
            <AccountStatusDialog
                user={user}
                open={dialog === 'status'}
                onOpenChange={close}
            />
        </>
    );
}

function Access({ user }: { user: Account }) {
    if (user.supplier_name) {
        return (
            <span className="text-sm">
                <span className="text-muted-foreground">Supplier · </span>
                {user.supplier_name}
            </span>
        );
    }

    return user.roles.length > 0 ? (
        <div className="flex flex-wrap gap-1">
            {user.roles.map((role) => (
                <Badge key={role} variant="secondary" className="capitalize">
                    {role}
                </Badge>
            ))}
        </div>
    ) : (
        <span className="text-muted-foreground text-sm">No access</span>
    );
}

export default function Users({ users, filters, suppliers, approvers }: Props) {
    return (
        <>
            <Head title="Users" />
            <PageContainer>
                <PageHeader
                    title="Users"
                    description="Internal roles and supplier representatives. Changes are recorded in the audit trail."
                    actions={
                        <AccountEditor
                            suppliers={suppliers}
                            approvers={approvers}
                        />
                    }
                />
                <Flash />
                <TableToolbar
                    search={filters.q}
                    searchLabel="Search name or email"
                    filters={[
                        {
                            name: 'role',
                            label: 'Access',
                            value: filters.role,
                            options: [
                                ...internalRoles.map((role) => ({
                                    value: role,
                                    label: capitalize(role),
                                })),
                                { value: 'supplier', label: 'Supplier' },
                                { value: 'none', label: 'No access' },
                            ],
                        },
                        {
                            name: 'status',
                            label: 'Statuses',
                            value: filters.status,
                            options: [
                                { value: 'active', label: 'Active' },
                                { value: 'disabled', label: 'Disabled' },
                            ],
                        },
                        {
                            name: 'supplier',
                            label: 'Suppliers',
                            value: filters.supplier,
                            options: suppliers.map((supplier) => ({
                                value: supplier.uuid,
                                label: supplier.legal_name ?? supplier.uuid,
                            })),
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
                                <TableHead>Name</TableHead>
                                <TableHead>Access</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="w-12">
                                    <span className="sr-only">Actions</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {users.data.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={4}
                                        className="text-muted-foreground h-24 text-center"
                                    >
                                        No users match these filters.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                users.data.map((user) => (
                                    <TableRow key={user.uuid}>
                                        <TableCell>
                                            <p className="font-medium">
                                                {user.name}
                                            </p>
                                            <p className="text-muted-foreground text-xs">
                                                {user.email}
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            <Access user={user} />
                                        </TableCell>
                                        <TableCell>
                                            <Status value={user.status} />
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <UserRowActions
                                                user={user}
                                                suppliers={suppliers}
                                                approvers={approvers}
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </DataTableFrame>
                <Pagination page={users} />
            </PageContainer>
        </>
    );
}

Users.layout = {
    breadcrumbs: [{ title: 'Users', href: index() }],
};
