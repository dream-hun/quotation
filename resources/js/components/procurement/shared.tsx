import { Form, Link, http, usePage } from '@inertiajs/react';
import type { VariantProps } from 'class-variance-authority';
import { format, isValid, parse } from 'date-fns';
import {
    ArrowUpRight,
    CalendarIcon,
    CircleAlert,
    CircleCheck,
    MoreHorizontal,
    X,
} from 'lucide-react';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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

const statusDots = {
    success: 'bg-emerald-500',
    danger: 'bg-red-500',
    warning: 'bg-amber-500',
    info: 'bg-sky-500',
    neutral: 'bg-slate-400',
};

export function statusTone(value: string): keyof typeof statusTones {
    return statusToneMap[value] ?? 'neutral';
}

export function statusLabel(value: string): string {
    const label = value.replaceAll('_', ' ');
    return label[0].toUpperCase() + label.slice(1);
}

export function Status({ value }: { value: string }) {
    const tone = statusTone(value);
    return (
        <Badge
            variant="outline"
            className={cn('gap-1.5 rounded-full capitalize', statusTones[tone])}
        >
            <span
                aria-hidden="true"
                className={cn('size-1.5 rounded-full', statusDots[tone])}
            />
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
                    <div className="text-muted-foreground flex flex-wrap items-center gap-2 text-sm">
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

export function StatGrid({ children }: { children: ReactNode }) {
    return (
        <div className="grid auto-rows-min gap-4 md:grid-cols-3">
            {children}
        </div>
    );
}

const statTones = {
    info: {
        card: 'border-sky-200/70 bg-sky-50/70 dark:border-sky-900/60 dark:bg-sky-950/30',
        icon: 'bg-sky-100 text-sky-700 dark:bg-sky-900/60 dark:text-sky-300',
        past: 'bg-sky-200 dark:bg-sky-900',
        current: 'bg-sky-600 dark:bg-sky-400',
    },
    warning: {
        card: 'border-amber-200/70 bg-amber-50/70 dark:border-amber-900/60 dark:bg-amber-950/30',
        icon: 'bg-amber-100 text-amber-700 dark:bg-amber-900/60 dark:text-amber-300',
        past: 'bg-amber-200 dark:bg-amber-900',
        current: 'bg-amber-500 dark:bg-amber-400',
    },
    success: {
        card: 'border-emerald-200/70 bg-emerald-50/70 dark:border-emerald-900/60 dark:bg-emerald-950/30',
        icon: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300',
        past: 'bg-emerald-200 dark:bg-emerald-900',
        current: 'bg-emerald-600 dark:bg-emerald-400',
    },
};

type StatTrend = {
    /** One count per week, oldest first; the last entry is the current week. */
    values: number[];
    /** ISO start date of each week. */
    weeks: string[];
    /** What is counted, e.g. "published". */
    noun: string;
};

function weekLabel(isoDate: string): string {
    return format(parse(isoDate, 'yyyy-MM-dd', new Date()), 'd MMM');
}

function TrendColumns({
    trend,
    tone,
}: {
    trend: StatTrend;
    tone: keyof typeof statTones;
}) {
    const max = Math.max(...trend.values, 1);
    const lastIndex = trend.values.length - 1;
    return (
        <div aria-hidden="true" className="flex h-14 items-end gap-0.5">
            {trend.values.map((value, index) => (
                <div
                    key={trend.weeks[index]}
                    className="group/bar relative flex h-full flex-1 items-end justify-center"
                >
                    <div
                        className={cn(
                            'w-full max-w-6 rounded-t-[4px] transition-opacity group-hover/bar:opacity-80',
                            value === 0
                                ? 'bg-border h-0.5 rounded-none'
                                : index === lastIndex
                                  ? statTones[tone].current
                                  : statTones[tone].past,
                        )}
                        style={
                            value === 0
                                ? undefined
                                : {
                                      height: `${Math.max((value / max) * 100, 8)}%`,
                                  }
                        }
                    />
                    <span className="bg-popover text-popover-foreground pointer-events-none absolute bottom-full z-10 mb-1.5 hidden rounded-md border px-2 py-1 text-xs whitespace-nowrap shadow-sm group-hover/bar:block">
                        <span className="text-muted-foreground">
                            Week of {weekLabel(trend.weeks[index])}
                        </span>{' '}
                        <span className="font-medium tabular-nums">
                            {value}
                        </span>
                    </span>
                </div>
            ))}
        </div>
    );
}

export function StatCard({
    label,
    value,
    hint,
    href,
    icon: Icon,
    tone,
    trend,
}: {
    label: string;
    value: ReactNode;
    hint?: ReactNode;
    href?: ComponentProps<typeof Link>['href'];
    icon: ComponentType<{ className?: string }>;
    tone: keyof typeof statTones;
    trend?: StatTrend;
}) {
    const total = trend?.values.reduce((sum, count) => sum + count, 0) ?? 0;
    const content = (
        <>
            <span className="flex items-start justify-between gap-3">
                <span className="flex items-center gap-3">
                    <span
                        className={cn(
                            'flex size-9 shrink-0 items-center justify-center rounded-lg',
                            statTones[tone].icon,
                        )}
                    >
                        <Icon aria-hidden="true" className="size-4.5" />
                    </span>
                    <span className="text-sm font-medium">{label}</span>
                </span>
                {href && (
                    <ArrowUpRight
                        aria-hidden="true"
                        className="text-muted-foreground size-4 opacity-0 transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100"
                    />
                )}
            </span>
            <span className="grid gap-0.5">
                <span className="text-3xl font-semibold tracking-tight tabular-nums">
                    {value}
                </span>
                {hint && (
                    <span className="text-muted-foreground text-sm">
                        {hint}
                    </span>
                )}
            </span>
            {trend && (
                <span className="grid gap-2">
                    <TrendColumns trend={trend} tone={tone} />
                    <span className="text-muted-foreground flex justify-between gap-2 text-xs">
                        <span>
                            <span className="text-foreground font-medium tabular-nums">
                                {total}
                            </span>{' '}
                            {trend.noun} in {trend.values.length} weeks
                        </span>
                        <span>This week</span>
                    </span>
                    <span className="sr-only">
                        {`Weekly ${trend.noun}, oldest first: `}
                        {trend.values
                            .map(
                                (count, index) =>
                                    `week of ${weekLabel(trend.weeks[index])}: ${count}`,
                            )
                            .join(', ')}
                    </span>
                </span>
            )}
        </>
    );
    const className = cn(
        'relative flex flex-col gap-4 rounded-xl border p-5',
        statTones[tone].card,
    );
    return href ? (
        <Link
            href={href}
            className={cn(
                className,
                'group focus-visible:ring-ring transition-shadow hover:shadow-sm focus-visible:ring-2 focus-visible:outline-none',
            )}
        >
            {content}
        </Link>
    ) : (
        <div className={className}>{content}</div>
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
            {Icon && (
                <Icon
                    aria-hidden="true"
                    className="text-muted-foreground mb-1 size-6"
                />
            )}
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
    open: controlledOpen,
    onOpenChange,
}: {
    title: string;
    description?: string;
    trigger?: ReactNode | null;
    triggerVariant?: VariantProps<typeof buttonVariants>['variant'];
    action: { url: string; method: 'post' | 'put' };
    children: ReactNode;
    submit?: string;
    submitVariant?: VariantProps<typeof buttonVariants>['variant'];
    transform?: ComponentProps<typeof Form>['transform'];
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
}) {
    const [uncontrolledOpen, setUncontrolledOpen] = useState(false);
    const open = controlledOpen ?? uncontrolledOpen;
    const setOpen = onOpenChange ?? setUncontrolledOpen;
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            {trigger !== null && (
                <DialogTrigger asChild>
                    {trigger ?? (
                        <Button variant={triggerVariant}>{title}</Button>
                    )}
                </DialogTrigger>
            )}
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

/**
 * The trailing actions column of a data table: one grouped menu per row, so
 * rows never carry inline buttons.
 */
export function RowActions({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <DropdownMenu modal={false}>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="data-[state=open]:bg-muted size-8"
                >
                    <MoreHorizontal />
                    <span className="sr-only">{label}</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-48">
                {children}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export function DataTableFrame({ children }: { children: ReactNode }) {
    return <div className="overflow-hidden rounded-md border">{children}</div>;
}

export function TableToolbar({
    search,
    searchLabel,
    filters,
    children,
    onChange,
}: {
    search: string | null | undefined;
    searchLabel: string;
    filters: {
        name: string;
        label: string;
        value: string | null | undefined;
        options: { value: string; label: string }[];
    }[];
    children?: ReactNode;
    onChange: (values: Record<string, string>) => void;
}) {
    const current = Object.fromEntries([
        ['q', search ?? ''],
        ...filters.map((filter) => [filter.name, filter.value ?? '']),
    ]) as Record<string, string>;
    const apply = (changes: Record<string, string>) =>
        onChange(
            Object.fromEntries(
                Object.entries({ ...current, ...changes }).filter(
                    ([, value]) => value !== '',
                ),
            ),
        );
    const hasFilters = Object.values(current).some(Boolean);
    return (
        <div className="flex flex-wrap items-center justify-between gap-2">
            <div className="flex flex-1 flex-wrap items-center gap-2">
                <form
                    role="search"
                    className="w-full sm:w-64"
                    onSubmit={(event) => {
                        event.preventDefault();
                        apply({
                            q: (
                                event.currentTarget.elements.namedItem(
                                    'q',
                                ) as HTMLInputElement
                            ).value.trim(),
                        });
                    }}
                >
                    <Input
                        key={search ?? ''}
                        name="q"
                        type="search"
                        aria-label={searchLabel}
                        placeholder={searchLabel}
                        defaultValue={search ?? ''}
                        className="h-9"
                    />
                </form>
                {filters.map((filter) => (
                    <Select
                        key={filter.name}
                        value={filter.value || 'all'}
                        onValueChange={(value) =>
                            apply({
                                [filter.name]: value === 'all' ? '' : value,
                            })
                        }
                    >
                        <SelectTrigger
                            aria-label={filter.label}
                            className="h-9 w-auto min-w-36"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">
                                All {filter.label.toLowerCase()}
                            </SelectItem>
                            {filter.options.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                ))}
                {hasFilters && (
                    <Button
                        variant="ghost"
                        className="h-9 px-3"
                        onClick={() => onChange({})}
                    >
                        Reset
                        <X />
                    </Button>
                )}
            </div>
            {children}
        </div>
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
                'border-sidebar-border/70 dark:border-sidebar-border gap-0 overflow-hidden rounded-xl py-0 shadow-none',
                className,
            )}
        >
            <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-3 border-b px-4 py-3 md:px-5">
                <div className="grid gap-0.5">
                    <CardTitle className="text-sm font-semibold">
                        {title}
                    </CardTitle>
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
            <CardContent className="p-4 md:p-5">{children}</CardContent>
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
