import { Form, Link, usePage } from '@inertiajs/react';
import { useId, useState } from 'react';
import type { ComponentProps, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PageData } from '@/types/procurement';

export function Status({ value }: { value: string }) {
    const color = [
        'awarded',
        'eligible',
        'submitted',
        'clean',
        'approved',
        'responded',
    ].includes(value)
        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'
        : ['excluded', 'rejected', 'cancelled', 'withdrawn', 'failed'].includes(
                value,
            )
          ? 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300'
          : 'bg-muted text-muted-foreground';
    return (
        <span
            className={
                'inline-flex rounded-full px-2.5 py-1 text-xs font-medium capitalize ' +
                color
            }
        >
            {value.replaceAll('_', ' ')}
        </span>
    );
}

export function Field({
    label,
    children,
    ...props
}: ComponentProps<typeof Input> & { label: string; children?: ReactNode }) {
    const id = useId();
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            {children ?? <Input id={id} {...props} />}
        </div>
    );
}

export function SelectField({
    label,
    children,
    ...props
}: ComponentProps<'select'> & { label: string }) {
    const id = useId();
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <select
                id={id}
                className="bg-background h-10 w-full rounded-md border px-3 text-sm"
                {...props}
            >
                {children}
            </select>
        </div>
    );
}

export function TextField({
    label,
    ...props
}: ComponentProps<'textarea'> & { label: string }) {
    const id = useId();
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <textarea
                id={id}
                className="bg-background focus-visible:ring-ring min-h-24 w-full rounded-md border p-3 text-sm focus-visible:ring-2"
                {...props}
            />
        </div>
    );
}

export function ActionDialog({
    title,
    description,
    trigger,
    action,
    children,
    submit = 'Save',
    transform,
}: {
    title: string;
    description?: string;
    trigger?: ReactNode;
    action: { url: string; method: 'post' | 'put' };
    children: ReactNode;
    submit?: string;
    transform?: ComponentProps<typeof Form>['transform'];
}) {
    const [open, setOpen] = useState(false);
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {trigger ?? <Button variant="outline">{title}</Button>}
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
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
                    className="grid gap-5"
                >
                    {({ errors, processing }) => (
                        <>
                            {children}
                            {Object.keys(errors).length > 0 && (
                                <div
                                    role="alert"
                                    className="border-destructive/30 bg-destructive/5 text-destructive rounded-md border p-3 text-sm"
                                >
                                    <ul className="list-inside list-disc">
                                        {Object.entries(errors).map(
                                            ([key, error]) => (
                                                <li key={key}>
                                                    {key}: {String(error)}
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                </div>
                            )}
                            <div className="flex justify-end gap-2 border-t pt-4">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => setOpen(false)}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Saving…' : submit}
                                </Button>
                            </div>
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
        <div
            role="status"
            className="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200"
        >
            {flash.success}
        </div>
    ) : null;
}

export function Pagination<T>({ page }: { page: PageData<T> }) {
    return (
        <div className="text-muted-foreground flex items-center justify-between gap-4 text-sm">
            <span>
                {page.total} records · Page {page.current_page} of{' '}
                {page.last_page}
            </span>
            <div className="flex gap-3">
                {page.prev_page_url && (
                    <Link href={page.prev_page_url} preserveScroll>
                        Previous
                    </Link>
                )}
                {page.next_page_url && (
                    <Link href={page.next_page_url} preserveScroll>
                        Next
                    </Link>
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
}: {
    title: string;
    description?: string;
    children: ReactNode;
    action?: ReactNode;
}) {
    return (
        <section className="bg-card overflow-hidden rounded-xl border">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4">
                <div>
                    <h2 className="font-semibold">{title}</h2>
                    {description && (
                        <p className="text-muted-foreground mt-1 text-sm">
                            {description}
                        </p>
                    )}
                </div>
                {action}
            </div>
            <div className="p-5">{children}</div>
        </section>
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
