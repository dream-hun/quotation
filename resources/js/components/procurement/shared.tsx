import { Form, Link, http, usePage } from '@inertiajs/react';
import type { VariantProps } from 'class-variance-authority';
import { format, isValid, parse } from 'date-fns';
import { CalendarIcon, CircleAlert, CircleCheck } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import type { ComponentProps, ComponentType, ReactNode } from 'react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { buttonVariants } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type { PageData } from '@/types/procurement';

const statusTones = {
    success:
        'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300',
    danger: 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300',
    warning:
        'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300',
    info: 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-300',
    neutral: 'bg-muted text-muted-foreground border-transparent',
};

const statusToneMap: Record<string, keyof typeof statusTones> = {
    awarded: 'success',
    eligible: 'success',
    submitted: 'success',
    clean: 'success',
    approved: 'success',
    responded: 'success',
    active: 'success',
    resolved: 'success',
    pass: 'success',
    excluded: 'danger',
    rejected: 'danger',
    cancelled: 'danger',
    withdrawn: 'danger',
    failed: 'danger',
    declined: 'danger',
    infected: 'danger',
    fail: 'danger',
    disabled: 'danger',
    pending: 'warning',
    pending_approval: 'warning',
    evaluating: 'warning',
    returned: 'warning',
    open: 'info',
    invited: 'info',
    all_invited: 'info',
};

export function Status({ value }: { value: string }) {
    return (
        <Badge
            variant="outline"
            className={cn(
                'rounded-full capitalize',
                statusTones[statusToneMap[value] ?? 'neutral'],
            )}
        >
            {value.replaceAll('_', ' ')}
        </Badge>
    );
}

export function PageContainer({ children }: { children: ReactNode }) {
    return (
        <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
            {children}
        </div>
    );
}

export function PageHeader({
    eyebrow,
    title,
    description,
    actions,
}: {
    eyebrow?: ReactNode;
    title: string;
    description?: ReactNode;
    actions?: ReactNode;
}) {
    return (
        <div className="flex flex-wrap items-end justify-between gap-4">
            <div className="grid gap-1">
                {eyebrow && (
                    <div className="text-muted-foreground flex flex-wrap items-center gap-2 text-xs font-medium tracking-wide uppercase">
                        {eyebrow}
                    </div>
                )}
                <h1 className="text-2xl font-semibold tracking-tight">
                    {title}
                </h1>
                {description && (
                    <div className="text-muted-foreground text-sm">
                        {description}
                    </div>
                )}
            </div>
            {actions && (
                <div className="flex flex-wrap items-center gap-2">
                    {actions}
                </div>
            )}
        </div>
    );
}

export function StatCard({
    label,
    value,
    icon: Icon,
}: {
    label: string;
    value: ReactNode;
    icon: ComponentType<{ className?: string }>;
}) {
    return (
        <div className="bg-card border-sidebar-border/70 dark:border-sidebar-border flex items-center justify-between rounded-xl border p-4">
            <div className="grid gap-1">
                <p className="text-muted-foreground text-sm">{label}</p>
                <p className="text-2xl font-semibold tabular-nums">{value}</p>
            </div>
            <div className="bg-muted flex size-10 items-center justify-center rounded-lg">
                <Icon className="text-muted-foreground size-5" />
            </div>
        </div>
    );
}

export function EmptyState({
    icon: Icon,
    title,
    description,
}: {
    icon?: ComponentType<{ className?: string }>;
    title: string;
    description?: string;
}) {
    return (
        <div className="flex flex-col items-center gap-2 rounded-lg border border-dashed px-4 py-10 text-center">
            {Icon && <Icon className="text-muted-foreground size-8" />}
            <p className="text-sm font-medium">{title}</p>
            {description && (
                <p className="text-muted-foreground max-w-md text-sm">
                    {description}
                </p>
            )}
        </div>
    );
}

export function Field({
    label,
    children,
    className,
    ...props
}: ComponentProps<typeof Input> & { label: string; children?: ReactNode }) {
    const id = useId();
    return (
        <div className={cn('grid gap-2', className)}>
            <Label htmlFor={id}>{label}</Label>
            {children ?? <Input id={id} {...props} />}
        </div>
    );
}

function parseDateValue(value: string | null | undefined, pattern: string) {
    if (!value) {
        return undefined;
    }

    const date = parse(value, pattern, new Date());

    return isValid(date) ? date : undefined;
}

/**
 * Submits the value through a text input that is hidden from view but still
 * takes part in native form validation, so `required` blocks empty submits.
 */
function RequiredValueInput({
    name,
    value,
    required,
}: {
    name: string;
    value: string;
    required?: boolean;
}) {
    return (
        <input
            type="text"
            name={name}
            value={value}
            required={required}
            onChange={() => {}}
            tabIndex={-1}
            aria-hidden
            className="pointer-events-none absolute inset-x-0 bottom-0 h-px w-full opacity-0"
        />
    );
}

function DatePicker({
    id,
    date,
    onSelect,
    placeholder,
    disabled,
}: {
    id: string;
    date: Date | undefined;
    onSelect: (date: Date | undefined) => void;
    placeholder: string;
    disabled?: boolean;
}) {
    const [open, setOpen] = useState(false);
    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    id={id}
                    type="button"
                    variant="outline"
                    disabled={disabled}
                    className={cn(
                        'w-full justify-start font-normal',
                        !date && 'text-muted-foreground',
                    )}
                >
                    <CalendarIcon className="size-4" />
                    {date ? format(date, 'PPP') : placeholder}
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-auto p-0" align="start">
                <Calendar
                    mode="single"
                    selected={date}
                    defaultMonth={date}
                    onSelect={(next) => {
                        onSelect(next);
                        setOpen(false);
                    }}
                />
            </PopoverContent>
        </Popover>
    );
}

/**
 * A calendar date picker that submits a `yyyy-MM-dd` value, like a native
 * date input, through a named input so it works inside plain and Inertia forms.
 */
export function DateField({
    label,
    name,
    defaultValue,
    placeholder = 'Pick a date',
    required,
    disabled,
    className,
}: {
    label: string;
    name: string;
    defaultValue?: string | null;
    placeholder?: string;
    required?: boolean;
    disabled?: boolean;
    className?: string;
}) {
    const id = useId();
    const [date, setDate] = useState(() =>
        parseDateValue(defaultValue?.slice(0, 10), 'yyyy-MM-dd'),
    );
    return (
        <div className={cn('relative grid gap-2', className)}>
            <Label htmlFor={id}>{label}</Label>
            <DatePicker
                id={id}
                date={date}
                onSelect={setDate}
                placeholder={placeholder}
                disabled={disabled}
            />
            <RequiredValueInput
                name={name}
                value={date ? format(date, 'yyyy-MM-dd') : ''}
                required={required}
            />
        </div>
    );
}

/**
 * A calendar date picker with a time input that submits a local
 * `yyyy-MM-ddTHH:mm` value, matching a native datetime-local input.
 */
export function DateTimeField({
    label,
    name,
    defaultValue,
    placeholder = 'Pick a date',
    required,
    disabled,
    className,
}: {
    label: string;
    name: string;
    defaultValue?: string | null;
    placeholder?: string;
    required?: boolean;
    disabled?: boolean;
    className?: string;
}) {
    const id = useId();
    const initial = parseDateValue(
        defaultValue?.slice(0, 16),
        "yyyy-MM-dd'T'HH:mm",
    );
    const [date, setDate] = useState(initial);
    const [time, setTime] = useState(initial ? format(initial, 'HH:mm') : '');
    const effectiveTime = time || '17:00';
    return (
        <div className={cn('relative grid gap-2', className)}>
            <Label htmlFor={id}>{label}</Label>
            <div className="flex gap-2">
                <DatePicker
                    id={id}
                    date={date}
                    onSelect={setDate}
                    placeholder={placeholder}
                    disabled={disabled}
                />
                <Input
                    type="time"
                    aria-label={label + ' time'}
                    value={effectiveTime}
                    onChange={(event) => setTime(event.target.value)}
                    disabled={disabled}
                    className="w-32 shrink-0"
                />
            </div>
            <RequiredValueInput
                name={name}
                value={
                    date ? format(date, 'yyyy-MM-dd') + 'T' + effectiveTime : ''
                }
                required={required}
            />
        </div>
    );
}

export function createUuid(): string {
    if (typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    // crypto.randomUUID is unavailable outside secure contexts (plain http).
    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = Array.from(bytes, (byte) =>
        byte.toString(16).padStart(2, '0'),
    ).join('');

    return [
        hex.slice(0, 8),
        hex.slice(8, 12),
        hex.slice(12, 16),
        hex.slice(16, 20),
        hex.slice(20),
    ].join('-');
}

function chunkErrorMessage(error: unknown): string {
    const response =
        error && typeof error === 'object' && 'response' in error
            ? (error.response as { status: number; data: string })
            : null;
    if (response?.status === 429) {
        return 'Too many upload requests. Wait a minute and try again.';
    }

    try {
        const body = JSON.parse(response?.data ?? '') as {
            message?: string;
            errors?: Record<string, string[]>;
        };
        const firstError = Object.values(body.errors ?? {})[0]?.[0];

        return firstError ?? body.message ?? 'The upload failed. Try again.';
    } catch {
        return 'The upload failed. Check your connection and try again.';
    }
}

/**
 * A file input that uploads the chosen file in chunks as soon as it is picked,
 * so large files stay under the server's request size limits. The form then
 * submits only the upload reference (`upload_uuid`, `total_chunks`,
 * `file_name`), which the server reassembles.
 */
export function ChunkedFileField({
    label,
    chunkUrl,
    accept,
    required,
}: {
    label: string;
    chunkUrl: string;
    accept?: string;
    required?: boolean;
}) {
    const id = useId();
    const { uploads } = usePage().props;
    const [upload, setUpload] = useState<{
        uuid: string;
        totalChunks: number;
        fileName: string;
    } | null>(null);
    const [progress, setProgress] = useState<number | null>(null);
    const [error, setError] = useState<string | null>(null);
    const controller = useRef<AbortController | null>(null);

    useEffect(() => () => controller.current?.abort(), []);

    async function send(file: File | undefined) {
        controller.current?.abort();
        setUpload(null);
        setError(null);
        setProgress(null);
        if (!file) {
            return;
        }

        if (file.size > uploads.maxKilobytes * 1024) {
            setError(
                'The file may not be greater than ' +
                    Math.round(uploads.maxKilobytes / 1024) +
                    ' MB.',
            );
            return;
        }

        const abort = new AbortController();
        controller.current = abort;
        const chunkBytes = uploads.chunkKilobytes * 1024;
        const totalChunks = Math.max(1, Math.ceil(file.size / chunkBytes));
        const uuid = createUuid();
        setProgress(0);
        try {
            for (let index = 0; index < totalChunks; index++) {
                const data = new FormData();
                data.append('upload_uuid', uuid);
                data.append('chunk_index', String(index));
                data.append(
                    'chunk',
                    file.slice(index * chunkBytes, (index + 1) * chunkBytes),
                    file.name,
                );
                await http.getClient().request({
                    method: 'post',
                    url: chunkUrl,
                    data,
                    signal: abort.signal,
                    headers: { Accept: 'application/json' },
                });
                setProgress(Math.round(((index + 1) / totalChunks) * 100));
            }

            setUpload({ uuid, totalChunks, fileName: file.name });
        } catch (exception) {
            if (!abort.signal.aborted) {
                setError(chunkErrorMessage(exception));
            }
        } finally {
            if (controller.current === abort) {
                setProgress(null);
            }
        }
    }

    return (
        <div className="relative grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                type="file"
                accept={accept}
                onChange={(event) => send(event.target.files?.[0])}
                aria-invalid={error !== null}
            />
            {progress !== null && (
                <div className="grid gap-1">
                    <div className="bg-muted h-2 overflow-hidden rounded-full">
                        <div
                            className="bg-primary h-full transition-[width]"
                            style={{ width: progress + '%' }}
                        />
                    </div>
                    <p className="text-muted-foreground text-xs">
                        Uploading… {progress}%
                    </p>
                </div>
            )}
            {upload && (
                <p className="text-muted-foreground flex items-center gap-1 text-xs">
                    <CircleCheck className="size-3.5 text-emerald-600" />
                    Ready to submit
                </p>
            )}
            {error && <p className="text-destructive text-sm">{error}</p>}
            <RequiredValueInput
                name="upload_uuid"
                value={upload?.uuid ?? ''}
                required={required}
            />
            <input
                type="hidden"
                name="total_chunks"
                value={upload?.totalChunks ?? ''}
            />
            <input
                type="hidden"
                name="file_name"
                value={upload?.fileName ?? ''}
            />
        </div>
    );
}

export type SelectOption = { value: string; label: string };

const emptyOptionValue = '__empty__';

/**
 * A shadcn select that submits its value through a hidden input, so it works
 * inside plain forms and Inertia forms. Options with an empty value act as a
 * selectable "none" choice (Radix does not allow empty item values).
 */
export function SelectField({
    label,
    name,
    options,
    defaultValue = '',
    placeholder = 'Select an option',
    required,
    disabled,
    className,
}: {
    label: string;
    name: string;
    options: SelectOption[];
    defaultValue?: string | null;
    placeholder?: string;
    required?: boolean;
    disabled?: boolean;
    className?: string;
}) {
    const id = useId();
    const [value, setValue] = useState(defaultValue ?? '');
    const hasEmptyOption = options.some((option) => option.value === '');
    return (
        <div className={cn('grid gap-2', className)}>
            <Label htmlFor={id}>{label}</Label>
            <input type="hidden" name={name} value={value} />
            <Select
                value={
                    value === '' && hasEmptyOption ? emptyOptionValue : value
                }
                onValueChange={(next) =>
                    setValue(next === emptyOptionValue ? '' : next)
                }
                required={required}
                disabled={disabled}
            >
                <SelectTrigger id={id} className="w-full">
                    <SelectValue placeholder={placeholder} />
                </SelectTrigger>
                <SelectContent>
                    {options.map((option) => (
                        <SelectItem
                            key={option.value || emptyOptionValue}
                            value={option.value || emptyOptionValue}
                        >
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}

export function TextField({
    label,
    className,
    ...props
}: ComponentProps<typeof Textarea> & { label: string }) {
    const id = useId();
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Textarea
                id={id}
                className={cn('min-h-24', className)}
                {...props}
            />
        </div>
    );
}

export function CheckboxField({
    label,
    description,
    bordered = false,
    ...props
}: ComponentProps<typeof Checkbox> & {
    label: ReactNode;
    description?: ReactNode;
    bordered?: boolean;
}) {
    const id = useId();
    return (
        <div
            className={cn(
                'flex items-start gap-3',
                bordered &&
                    'has-[[data-state=checked]]:bg-muted/50 rounded-lg border p-3',
            )}
        >
            <Checkbox id={id} {...props} />
            <div className="grid gap-1">
                <Label htmlFor={id} className="font-normal">
                    {label}
                </Label>
                {description && (
                    <p className="text-muted-foreground text-xs">
                        {description}
                    </p>
                )}
            </div>
        </div>
    );
}

export function ActionDialog({
    title,
    description,
    trigger,
    triggerVariant = 'outline',
    action,
    children,
    submit = 'Save',
    submitVariant = 'default',
    transform,
}: {
    title: string;
    description?: string;
    trigger?: ReactNode;
    triggerVariant?: VariantProps<typeof buttonVariants>['variant'];
    action: { url: string; method: 'post' | 'put' };
    children: ReactNode;
    submit?: string;
    submitVariant?: VariantProps<typeof buttonVariants>['variant'];
    transform?: ComponentProps<typeof Form>['transform'];
}) {
    const [open, setOpen] = useState(false);
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {trigger ?? <Button variant={triggerVariant}>{title}</Button>}
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>
                        {description ??
                            'Review the details before saving. All changes are recorded.'}
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={action.url}
                    method={action.method}
                    transform={transform}
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            {children}
                            {Object.keys(errors).length > 0 && (
                                <Alert variant="destructive">
                                    <CircleAlert />
                                    <AlertDescription>
                                        <ul className="list-inside list-disc">
                                            {[
                                                ...new Set(
                                                    Object.values(errors).map(
                                                        String,
                                                    ),
                                                ),
                                            ].map((error) => (
                                                <li key={error}>{error}</li>
                                            ))}
                                        </ul>
                                    </AlertDescription>
                                </Alert>
                            )}
                            <DialogFooter className="border-t pt-4">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => setOpen(false)}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    variant={submitVariant}
                                    disabled={processing}
                                >
                                    {processing ? 'Saving…' : submit}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export function Flash() {
    const { flash } = usePage<{ flash?: { success?: string } }>().props;
    return flash?.success ? (
        <Alert
            role="status"
            className="border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200"
        >
            <CircleCheck />
            <AlertDescription className="text-current">
                {flash.success}
            </AlertDescription>
        </Alert>
    ) : null;
}

export function Pagination<T>({ page }: { page: PageData<T> }) {
    return (
        <div className="text-muted-foreground flex flex-wrap items-center justify-between gap-4 text-sm">
            <span>
                {page.total} {page.total === 1 ? 'record' : 'records'} · Page{' '}
                {page.current_page} of {page.last_page}
            </span>
            <div className="flex gap-2">
                {page.prev_page_url ? (
                    <Button variant="outline" size="sm" asChild>
                        <Link href={page.prev_page_url} preserveScroll>
                            Previous
                        </Link>
                    </Button>
                ) : (
                    <Button variant="outline" size="sm" disabled>
                        Previous
                    </Button>
                )}
                {page.next_page_url ? (
                    <Button variant="outline" size="sm" asChild>
                        <Link href={page.next_page_url} preserveScroll>
                            Next
                        </Link>
                    </Button>
                ) : (
                    <Button variant="outline" size="sm" disabled>
                        Next
                    </Button>
                )}
            </div>
        </div>
    );
}

export function Section({
    title,
    description,
    children,
    action,
    className,
}: {
    title: string;
    description?: string;
    children: ReactNode;
    action?: ReactNode;
    className?: string;
}) {
    return (
        <Card
            className={cn(
                'border-sidebar-border/70 dark:border-sidebar-border gap-0 overflow-hidden py-0 shadow-none',
                className,
            )}
        >
            <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-3 border-b px-4 py-3">
                <div className="grid gap-1">
                    <CardTitle className="text-base">{title}</CardTitle>
                    {description && (
                        <CardDescription>{description}</CardDescription>
                    )}
                </div>
                {action && (
                    <div className="flex flex-wrap items-center gap-2">
                        {action}
                    </div>
                )}
            </CardHeader>
            <CardContent className="p-4">{children}</CardContent>
        </Card>
    );
}

/**
 * Formats a stored decimal string for display without floating point loss:
 * thousands are grouped and the fraction shows at least the currency precision.
 */
export function money(value: string | null | undefined, precision = 2) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const [whole, fraction = ''] = value.split('.');
    const significant = fraction.replace(/0+$/, '');
    const digits = significant.padEnd(precision, '0');

    return (
        whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',') +
        (digits ? '.' + digits : '')
    );
}

export function timestamp(value: string | null, timezone = 'UTC') {
    return value
        ? new Intl.DateTimeFormat(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
              timeZone: timezone,
          }).format(new Date(value))
        : 'Not set';
}
