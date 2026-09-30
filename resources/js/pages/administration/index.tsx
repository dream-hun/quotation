import { Head } from '@inertiajs/react';
import {
    CircleAlert,
    CircleCheck,
    CircleOff,
    Pencil,
    Plus,
} from 'lucide-react';
import { useState } from 'react';
import {
    index,
    store,
} from '@/actions/App/Http/Controllers/AdministrationController';
import {
    ActionDialog,
    DataTableFrame,
    Field,
    Flash,
    PageContainer,
    PageHeader,
    RowActions,
    Section,
    SelectField,
    Status,
} from '@/components/procurement/shared';
import { Alert, AlertDescription } from '@/components/ui/alert';
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

type Currency = {
    uuid: string;
    code: string;
    name: string;
    minor_digits: number;
    active: boolean;
    lock_version: number;
};
type Props = {
    currencies: Currency[];
    emailFailures: number;
    workerHealthy: boolean;
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

function CurrencyEditor({
    currency,
    open,
    onOpenChange,
}: {
    currency?: Currency;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
}) {
    return (
        <ActionDialog
            title={currency ? 'Edit currency' : 'Add currency'}
            trigger={
                currency ? null : (
                    <Button>
                        <Plus />
                        Add currency
                    </Button>
                )
            }
            open={open}
            onOpenChange={onOpenChange}
            submit={currency ? 'Save changes' : 'Add currency'}
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

function CurrencyStatusDialog({
    currency,
    open,
    onOpenChange,
}: {
    currency: Currency;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <ActionDialog
            title={
                currency.active
                    ? `Deactivate ${currency.code}?`
                    : `Activate ${currency.code}?`
            }
            trigger={null}
            open={open}
            onOpenChange={onOpenChange}
            action={store()}
            submit={currency.active ? 'Deactivate' : 'Activate'}
            submitVariant={currency.active ? 'destructive' : 'default'}
            description={
                currency.active
                    ? 'New requests can no longer use this currency. Existing requests keep it.'
                    : 'New requests can use this currency again.'
            }
        >
            <input type="hidden" name="type" value="currency" />
            <input
                type="hidden"
                name="lock_version"
                value={currency.lock_version}
            />
            <input type="hidden" name="code" value={currency.code} />
            <input type="hidden" name="name" value={currency.name} />
            <input
                type="hidden"
                name="minor_digits"
                value={currency.minor_digits}
            />
            <input
                type="hidden"
                name="active"
                value={currency.active ? '0' : '1'}
            />
        </ActionDialog>
    );
}

function CurrencyRowActions({ currency }: { currency: Currency }) {
    const [dialog, setDialog] = useState<'edit' | 'status' | null>(null);
    const close = (open: boolean) => !open && setDialog(null);
    return (
        <>
            <RowActions label={`Actions for ${currency.code}`}>
                <DropdownMenuLabel>{currency.code}</DropdownMenuLabel>
                <DropdownMenuItem onSelect={() => setDialog('edit')}>
                    <Pencil />
                    Edit currency
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    variant={currency.active ? 'destructive' : 'default'}
                    onSelect={() => setDialog('status')}
                >
                    {currency.active ? <CircleOff /> : <CircleCheck />}
                    {currency.active ? 'Deactivate' : 'Activate'}
                </DropdownMenuItem>
            </RowActions>
            <CurrencyEditor
                currency={currency}
                open={dialog === 'edit'}
                onOpenChange={close}
            />
            <CurrencyStatusDialog
                currency={currency}
                open={dialog === 'status'}
                onOpenChange={close}
            />
        </>
    );
}

export default function Administration({
    currencies,
    organization,
    emailFailures,
    workerHealthy,
}: Props) {
    return (
        <>
            <Head title="Configuration" />
            <PageContainer>
                <PageHeader
                    title="Configuration"
                    description="The buying organization, supported currencies and service health."
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
                <div className="grid gap-4">
                    <div className="flex flex-wrap items-end justify-between gap-2">
                        <div>
                            <h2 className="font-semibold">
                                Supported currencies
                            </h2>
                            <p className="text-muted-foreground text-sm">
                                Currencies buyers can choose for new requests.
                            </p>
                        </div>
                        <CurrencyEditor />
                    </div>
                    <DataTableFrame>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Code</TableHead>
                                    <TableHead>Name</TableHead>
                                    <TableHead className="text-right">
                                        Decimal places
                                    </TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="w-12">
                                        <span className="sr-only">Actions</span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {currencies.length === 0 ? (
                                    <TableRow>
                                        <TableCell
                                            colSpan={5}
                                            className="text-muted-foreground h-24 text-center"
                                        >
                                            No currencies yet.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    currencies.map((currency) => (
                                        <TableRow key={currency.uuid}>
                                            <TableCell className="font-mono font-medium">
                                                {currency.code}
                                            </TableCell>
                                            <TableCell>
                                                {currency.name}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {currency.minor_digits}
                                            </TableCell>
                                            <TableCell>
                                                <Status
                                                    value={
                                                        currency.active
                                                            ? 'active'
                                                            : 'inactive'
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <CurrencyRowActions
                                                    currency={currency}
                                                />
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </DataTableFrame>
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
    breadcrumbs: [{ title: 'Configuration', href: index() }],
};
