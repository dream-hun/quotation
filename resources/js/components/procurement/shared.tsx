import { Form, Link, usePage } from '@inertiajs/react';
import type { VariantProps } from 'class-variance-authority';
import { CircleAlert, CircleCheck } from 'lucide-react';
import { useId, useState } from 'react';
import type { ComponentProps, ComponentType, ReactNode } from 'react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { buttonVariants } from '@/components/ui/button';
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
                {page.total} records · Page {page.current_page} of{' '}
                {page.last_page}
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

export function timestamp(value: string | null, timezone = 'UTC') {
    return value
        ? new Intl.DateTimeFormat(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
              timeZone: timezone,
          }).format(new Date(value))
        : 'Not set';
}
