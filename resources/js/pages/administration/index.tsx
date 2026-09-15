import { Head, router } from '@inertiajs/react';
import {
    index,
    store,
} from '@/actions/App/Http/Controllers/AdministrationController';
import {
    ActionDialog,
    Field,
    Flash,
    Pagination,
    Section,
    SelectField,
    Status,
    TextField,
} from '@/components/procurement/shared';
import { Button } from '@/components/ui/button';
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
            <SelectField
                label="Account status"
                name="status"
                defaultValue={user?.status ?? 'active'}
            >
                <option value="active">Active</option>
                <option value="disabled">Disabled</option>
            </SelectField>
            <fieldset className="grid gap-3">
                <legend className="mb-3 text-sm font-medium">
                    Internal roles
                </legend>
                {['buyer', 'approver', 'auditor', 'administrator'].map(
                    (role) => (
                        <label
                            key={role}
                            className="flex items-center gap-2 text-sm capitalize"
                        >
                            <input
                                type="checkbox"
                                name="roles[]"
                                value={role}
                                defaultChecked={user?.roles.includes(role)}
                            />
                            {role}
                        </label>
                    ),
                )}
            </fieldset>
            <SelectField
                label="Supplier membership (requires no internal roles)"
                name="supplier_uuid"
                defaultValue={user?.supplier_uuid ?? ''}
            >
                <option value="">Internal account / no supplier</option>
                {suppliers
                    .filter((supplier) => supplier.status === 'active')
                    .map((supplier) => (
                        <option key={supplier.uuid} value={supplier.uuid}>
                            {supplier.legal_name}
                        </option>
                    ))}
            </SelectField>
            {user && (
                <>
                    <SelectField
                        label="Replacement approver (when removing an assigned approver)"
                        name="replacement_uuid"
                    >
                        <option value="">Select if required</option>
                        {users
                            .filter(
                                (person) =>
                                    person.uuid !== user.uuid &&
                                    person.roles.includes('approver') &&
                                    person.status === 'active',
                            )
                            .map((person) => (
                                <option key={person.uuid} value={person.uuid}>
                                    {person.name}
                                </option>
                            ))}
                    </SelectField>
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
            >
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </SelectField>
        </ActionDialog>
    );
}

function CurrencyEditor({ currency }: { currency?: Currency }) {
    return (
        <ActionDialog
            title={currency ? 'Edit currency' : 'Add currency'}
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
            >
                <option value="1">Active</option>
                <option value="0">Inactive</option>
            </SelectField>
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
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-8">
                <div>
                    <p className="mb-2 text-xs font-semibold tracking-widest text-emerald-700 uppercase dark:text-emerald-400">
                        Workspace configuration
                    </p>
                    <h1 className="text-3xl font-semibold tracking-tight">
                        Administration
                    </h1>
                    <p className="text-muted-foreground mt-2">
                        Manage access while preserving historical decisions.
                    </p>
                </div>
                <Flash />
                {!organization && (
                    <p className="bg-muted rounded-lg border p-4 text-sm">
                        Add your supported currency, then configure the buying
                        organization and user roles.
                    </p>
                )}
                <Section
                    title="Buying organization"
                    action={
                        <ActionDialog
                            title="Configure organization"
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
                                required
                            >
                                <option value="">Choose currency</option>
                                {currencies.map((currency) => (
                                    <option
                                        key={currency.code}
                                        value={currency.code}
                                    >
                                        {currency.code}
                                    </option>
                                ))}
                            </SelectField>
                        </ActionDialog>
                    }
                >
                    <p className="font-medium">
                        {organization?.name ?? 'Not configured'}
                    </p>
                    <p className="text-muted-foreground mt-2 text-sm">
                        {organization?.timezone} ·{' '}
                        {organization?.default_currency_code}
                    </p>
                </Section>
                <Section
                    title="Accounts & permissions"
                    action={
                        <AccountEditor
                            suppliers={suppliers}
                            users={users.data}
                        />
                    }
                >
                    <form
                        className="mb-5 flex items-end gap-3"
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
                        />
                        <Button type="submit">Search</Button>
                    </form>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="text-muted-foreground border-b">
                                <tr>
                                    <th className="pb-3">Account</th>
                                    <th className="pb-3">Access</th>
                                    <th className="pb-3">Status</th>
                                    <th className="pb-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {users.data.map((user) => (
                                    <tr key={user.uuid} className="border-b">
                                        <td className="py-4 pr-4">
                                            {user.name}
                                            <p className="text-muted-foreground text-xs">
                                                {user.email}
                                            </p>
                                        </td>
                                        <td className="pr-4">
                                            {user.roles.join(', ') ||
                                                (user.supplier_uuid
                                                    ? 'Supplier'
                                                    : 'No role assigned')}
                                        </td>
                                        <td className="pr-4">
                                            <Status value={user.status} />
                                        </td>
                                        <td>
                                            <AccountEditor
                                                user={user}
                                                suppliers={suppliers}
                                                users={users.data}
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="mt-5">
                        <Pagination page={users} />
                    </div>
                </Section>
                <Section title="Supplier companies" action={<SupplierEditor />}>
                    <div className="grid gap-3">
                        {suppliers.map((supplier) => (
                            <div
                                key={supplier.uuid}
                                className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-4"
                            >
                                <div>
                                    <p className="font-medium">
                                        {supplier.legal_name}
                                    </p>
                                    <p className="text-muted-foreground text-sm">
                                        {supplier.contact_email}
                                    </p>
                                </div>
                                <Status value={supplier.status} />
                                <SupplierEditor supplier={supplier} />
                            </div>
                        ))}
                    </div>
                </Section>
                <Section
                    title="Supported currencies"
                    action={<CurrencyEditor />}
                >
                    <div className="grid gap-3">
                        {currencies.map((currency) => (
                            <div
                                key={currency.uuid}
                                className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-4"
                            >
                                <span>
                                    {currency.code} · {currency.name} ·{' '}
                                    {currency.minor_digits} decimal places
                                </span>
                                <Status
                                    value={
                                        currency.active ? 'active' : 'inactive'
                                    }
                                />
                                <CurrencyEditor currency={currency} />
                            </div>
                        ))}
                    </div>
                </Section>
                <Section title="Service readiness">
                    <div className="grid gap-2 text-sm">
                        <p>
                            Worker:{' '}
                            {workerHealthy
                                ? 'Recent heartbeat'
                                : 'No recent heartbeat'}
                        </p>
                        <p>
                            File scanner:{' '}
                            {scannerConfigured
                                ? 'Configured'
                                : 'Awaiting configuration'}
                        </p>
                        <p>{pendingScans} files awaiting security checks.</p>
                    </div>
                </Section>
                <Section title="Notification delivery">
                    <p className="text-sm">
                        {emailFailures} email notifications have failed
                        delivery. In-app notifications remain authoritative.
                        Background processing retries failed deliveries up to
                        five attempts.
                    </p>
                </Section>
            </div>
        </>
    );
}
Administration.layout = {
    breadcrumbs: [{ title: 'Administration', href: index() }],
};
