import { Head, router } from '@inertiajs/react';
import { CircleAlert, CircleCheck, Coins, Search, Users } from 'lucide-react';
import {
    index,
    store,
} from '@/actions/App/Http/Controllers/AdministrationController';
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
} from '@/components/procurement/shared';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
};
type Supplier = {
    uuid: string;
    legal_name: string;
    contact_email: string;
    status: string;
    lock_version: number;
};
type Currency = {
    uuid: string;
    code: string;
    name: string;
    minor_digits: number;
    active: boolean;
    lock_version: number;
};
type Props = {
    users: PageData<Account>;
    suppliers: Supplier[];
    currencies: Currency[];
    emailFailures: number;
    pendingScans: number;
    scannerConfigured: boolean;
    workerHealthy: boolean;
    q: string;
    organization: {
        uuid: string;
        name: string;
        timezone: string;
        default_currency_code: string;
        lock_version: number;
    } | null;
};

function editorTrigger(label: string, isEditing: boolean) {
    return (
        <Button variant={isEditing ? 'ghost' : 'outline'} size="sm">
            {label}
        </Button>
    );
}

function AccountEditor({
    user,
    suppliers,
    users,
}: {
    user?: Account;
    suppliers: Supplier[];
    users: Account[];
}) {
    return (
        <ActionDialog
            title={user ? 'Edit account' : 'Add account'}
            trigger={editorTrigger(user ? 'Edit' : 'Add account', !!user)}
            action={store()}
            description="Assign internal roles or supplier membership. New users receive a password setup email."
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
                    required
                />
                <Field
                    label="Email"
                    name="email"
                    type="email"
                    defaultValue={user?.email}
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
                    {['buyer', 'approver', 'auditor', 'administrator'].map(
                        (role) => (
                            <CheckboxField
                                key={role}
                                bordered
                                name="roles[]"
                                value={role}
                                defaultChecked={user?.roles.includes(role)}
                                label={role[0].toUpperCase() + role.slice(1)}
                            />
                        ),
                    )}
                </div>
            </fieldset>
            <SelectField
                label="Supplier membership (requires no internal roles)"
                name="supplier_uuid"
                defaultValue={user?.supplier_uuid}
                options={[
                    { value: '', label: 'Internal account / no supplier' },
                    ...suppliers
                        .filter((supplier) => supplier.status === 'active')
                        .map((supplier) => ({
                            value: supplier.uuid,
                            label: supplier.legal_name,
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
                            ...users
                                .filter(
                                    (person) =>
                                        person.uuid !== user.uuid &&
                                        person.roles.includes('approver') &&
                                        person.status === 'active',
                                )
                                .map((person) => ({
                                    value: person.uuid,
                                    label: person.name,
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

function SupplierEditor({ supplier }: { supplier?: Supplier }) {
    return (
        <ActionDialog
            title={supplier ? 'Edit supplier' : 'Add supplier'}
            trigger={editorTrigger(
                supplier ? 'Edit' : 'Add supplier',
                !!supplier,
            )}
            action={store()}
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
                required
            />
            <Field
                label="Contact email"
                name="contact_email"
                type="email"
                defaultValue={supplier?.contact_email}
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

function CurrencyEditor({ currency }: { currency?: Currency }) {
    return (
        <ActionDialog
            title={currency ? 'Edit currency' : 'Add currency'}
            trigger={editorTrigger(
                currency ? 'Edit' : 'Add currency',
                !!currency,
            )}
            action={store()}
            description="Precision affects new publications only."
        >
            <input type="hidden" name="type" value="currency" />
            <input
                type="hidden"
                name="lock_version"
                value={currency?.lock_version ?? 0}
            />
            <Field
                label="Three-letter currency code"
                name="code"
                defaultValue={currency?.code}
                readOnly={!!currency}
                required
                maxLength={3}
            />
            <Field
                label="Currency name"
                name="name"
                defaultValue={currency?.name}
                required
            />
            <Field
                label="Decimal places (0–4)"
                name="minor_digits"
                type="number"
                min="0"
                max="4"
                defaultValue={currency?.minor_digits ?? 2}
                required
            />
            <SelectField
                label="Available for new requests"
                name="active"
                defaultValue={currency?.active === false ? '0' : '1'}
                options={[
                    { value: '1', label: 'Active' },
                    { value: '0', label: 'Inactive' },
                ]}
            />
        </ActionDialog>
    );
}

export default function Administration({
    users,
    suppliers,
    currencies,
    organization,
    emailFailures,
    pendingScans,
    scannerConfigured,
    workerHealthy,
    q,
}: Props) {
    return (
        <>
            <Head title="Administration" />
            <PageContainer>
                <PageHeader
                    eyebrow="Workspace configuration"
                    title="Administration"
                    description="Manage access while preserving historical decisions."
                />
                <Flash />
                {!organization && (
                    <Alert>
                        <CircleAlert />
                        <AlertDescription>
                            Add your supported currency, then configure the
                            buying organization and user roles.
                        </AlertDescription>
                    </Alert>
                )}
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <Section
                        title="Buying organization"
                        action={
                            <ActionDialog
                                title="Configure organization"
                                trigger={editorTrigger(
                                    organization ? 'Edit' : 'Configure',
                                    !!organization,
                                )}
                                action={store()}
                            >
                                <input
                                    type="hidden"
                                    name="type"
                                    value="organization"
                                />
                                <input
                                    type="hidden"
                                    name="lock_version"
                                    value={organization?.lock_version ?? 0}
                                />
                                <Field
                                    label="Organization name"
                                    name="name"
                                    defaultValue={organization?.name}
                                    required
                                />
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field
                                        label="IANA timezone"
                                        name="timezone"
                                        placeholder="Africa/Kigali"
                                        defaultValue={organization?.timezone}
                                        required
                                    />
                                    <SelectField
                                        label="Default currency"
                                        name="default_currency_code"
                                        defaultValue={
                                            organization?.default_currency_code
                                        }
                                        placeholder="Choose currency"
                                        required
                                        options={currencies.map((currency) => ({
                                            value: currency.code,
                                            label:
                                                currency.code +
                                                ' · ' +
                                                currency.name,
                                        }))}
                                    />
                                </div>
                            </ActionDialog>
                        }
                    >
                        <p className="font-medium">
                            {organization?.name ?? 'Not configured'}
                        </p>
                        {organization && (
                            <p className="text-muted-foreground mt-1 text-sm">
                                {organization.timezone} ·{' '}
                                {organization.default_currency_code}
                            </p>
                        )}
                    </Section>
                    <Section title="Service readiness">
                        <ul className="grid gap-2 text-sm">
                            <ReadinessItem
                                healthy={workerHealthy}
                                label={
                                    workerHealthy
                                        ? 'Worker: recent heartbeat'
                                        : 'Worker: no recent heartbeat'
                                }
                            />
                            <ReadinessItem
                                healthy={scannerConfigured}
                                label={
                                    scannerConfigured
                                        ? 'File scanner configured'
                                        : 'File scanner awaiting configuration'
                                }
                            />
                            <ReadinessItem
                                healthy={pendingScans === 0}
                                label={
                                    pendingScans +
                                    ' files awaiting security checks'
                                }
                            />
                        </ul>
                    </Section>
                    <Section title="Notification delivery">
                        <p className="text-2xl font-semibold tabular-nums">
                            {emailFailures}
                        </p>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Failed email notifications. In-app notifications
                            remain authoritative, and failed deliveries are
                            retried up to five times.
                        </p>
                    </Section>
                </div>
                <Section
                    title="Accounts & permissions"
                    description="Internal roles and supplier memberships."
                    action={
                        <AccountEditor
                            suppliers={suppliers}
                            users={users.data}
                        />
                    }
                >
                    <form
                        className="mb-4 flex max-w-md items-end gap-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            router.get(
                                index.url(),
                                {
                                    q: (
                                        event.currentTarget.elements.namedItem(
                                            'q',
                                        ) as HTMLInputElement
                                    ).value,
                                },
                                { preserveState: true },
                            );
                        }}
                    >
                        <Field
                            label="Find an account"
                            name="q"
                            defaultValue={q}
                            placeholder="Name or email"
                            className="flex-1"
                        />
                        <Button type="submit" variant="outline">
                            <Search className="size-4" />
                            Search
                        </Button>
                    </form>
                    {users.data.length === 0 ? (
                        <EmptyState
                            icon={Users}
                            title="No accounts found"
                            description={
                                q ? 'Try a different name or email.' : undefined
                            }
                        />
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Account</TableHead>
                                    <TableHead>Access</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">
                                        <span className="sr-only">Actions</span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {users.data.map((user) => (
                                    <TableRow key={user.uuid}>
                                        <TableCell className="py-3">
                                            <p className="font-medium">
                                                {user.name}
                                            </p>
                                            <p className="text-muted-foreground text-xs">
                                                {user.email}
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap gap-1">
                                                {user.roles.length > 0 ? (
                                                    user.roles.map((role) => (
                                                        <Badge
                                                            key={role}
                                                            variant="secondary"
                                                            className="capitalize"
                                                        >
                                                            {role}
                                                        </Badge>
                                                    ))
                                                ) : user.supplier_uuid ? (
                                                    <Badge variant="outline">
                                                        Supplier
                                                    </Badge>
                                                ) : (
                                                    <span className="text-muted-foreground text-sm">
                                                        No role assigned
                                                    </span>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Status value={user.status} />
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <AccountEditor
                                                user={user}
                                                suppliers={suppliers}
                                                users={users.data}
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                    <div className="mt-4 border-t pt-4">
                        <Pagination page={users} />
                    </div>
                </Section>
                <div className="grid gap-4 lg:grid-cols-2">
                    <Section
                        title="Supplier companies"
                        action={<SupplierEditor />}
                    >
                        {suppliers.length === 0 ? (
                            <EmptyState icon={Users} title="No suppliers yet" />
                        ) : (
                            <div className="-my-2 divide-y">
                                {suppliers.map((supplier) => (
                                    <div
                                        key={supplier.uuid}
                                        className="flex items-center gap-3 py-2"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate font-medium">
                                                {supplier.legal_name}
                                            </p>
                                            <p className="text-muted-foreground truncate text-sm">
                                                {supplier.contact_email}
                                            </p>
                                        </div>
                                        <Status value={supplier.status} />
                                        <SupplierEditor supplier={supplier} />
                                    </div>
                                ))}
                            </div>
                        )}
                    </Section>
                    <Section
                        title="Supported currencies"
                        action={<CurrencyEditor />}
                    >
                        {currencies.length === 0 ? (
                            <EmptyState
                                icon={Coins}
                                title="No currencies yet"
                            />
                        ) : (
                            <div className="-my-2 divide-y">
                                {currencies.map((currency) => (
                                    <div
                                        key={currency.uuid}
                                        className="flex items-center gap-3 py-2"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <p className="font-medium">
                                                {currency.code}
                                                <span className="text-muted-foreground font-normal">
                                                    {' · '}
                                                    {currency.name}
                                                </span>
                                            </p>
                                            <p className="text-muted-foreground text-sm">
                                                {currency.minor_digits} decimal
                                                places
                                            </p>
                                        </div>
                                        <Status
                                            value={
                                                currency.active
                                                    ? 'active'
                                                    : 'inactive'
                                            }
                                        />
                                        <CurrencyEditor currency={currency} />
                                    </div>
                                ))}
                            </div>
                        )}
                    </Section>
                </div>
            </PageContainer>
        </>
    );
}

function ReadinessItem({
    healthy,
    label,
}: {
    healthy: boolean;
    label: string;
}) {
    return (
        <li className="flex items-center gap-2">
            {healthy ? (
                <CircleCheck className="size-4 text-emerald-600 dark:text-emerald-400" />
            ) : (
                <CircleAlert className="size-4 text-amber-600 dark:text-amber-400" />
            )}
            {label}
        </li>
    );
}
Administration.layout = {
    breadcrumbs: [{ title: 'Administration', href: index() }],
};
