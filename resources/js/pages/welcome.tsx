import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    ClipboardList,
    FileCheck2,
    LockKeyhole,
    Scale,
    ShieldCheck,
} from 'lucide-react';
import { dashboard, login, register } from '@/routes';

export default function Welcome() {
    const { auth } = usePage().props;
    return (
        <>
            <Head title="Quotation comparison" />
            <div className="bg-background text-foreground min-h-screen">
                <header className="mx-auto flex max-w-6xl items-center justify-between px-6 py-6">
                    <Link
                        href={login()}
                        className="flex items-center gap-3 font-semibold"
                    >
                        <span className="flex size-9 items-center justify-center rounded-lg bg-emerald-700 text-white">
                            <Scale className="size-5" />
                        </span>
                        Quotation
                    </Link>
                    <nav className="flex items-center gap-5 text-sm">
                        {auth.user ? (
                            <Link href={dashboard()} className="font-medium">
                                Open workspace{' '}
                                <ArrowRight className="ml-1 inline size-4" />
                            </Link>
                        ) : (
                            <>
                                <Link href={login()}>Sign in</Link>
                                <Link
                                    href={register()}
                                    className="rounded-lg border px-4 py-2"
                                >
                                    Create account
                                </Link>
                            </>
                        )}
                    </nav>
                </header>
                <main className="mx-auto max-w-6xl px-6 py-16 md:py-24">
                    <div className="grid items-center gap-12 lg:grid-cols-2">
                        <div>
                            <p className="mb-5 text-xs font-semibold tracking-[0.2em] text-emerald-700 uppercase dark:text-emerald-400">
                                A clear path to the right supplier
                            </p>
                            <h1 className="text-5xl leading-tight font-semibold tracking-tight md:text-6xl">
                                Better procurement.
                                <br />
                                <span className="text-emerald-700 dark:text-emerald-400">
                                    Every decision, documented.
                                </span>
                            </h1>
                            <p className="text-muted-foreground mt-6 max-w-lg text-lg leading-relaxed">
                                Bring requests, supplier quotations and
                                approvals into one workspace. Compare the
                                complete offer and keep the evidence behind your
                                choice.
                            </p>
                            <Link
                                href={auth.user ? dashboard() : login()}
                                className="mt-8 inline-flex items-center gap-3 rounded-lg bg-emerald-700 px-6 py-3 font-medium text-white hover:bg-emerald-800"
                            >
                                Enter your workspace
                                <ArrowRight className="size-4" />
                            </Link>
                            <p className="text-muted-foreground mt-4 text-sm">
                                Supplier access is invitation-only. Account
                                permissions are assigned by your administrator.
                            </p>
                        </div>
                        <div className="bg-card rounded-2xl border p-6 shadow-sm">
                            <div className="mb-6 flex items-center justify-between border-b pb-5">
                                <span className="text-sm font-semibold">
                                    The quotation lifecycle
                                </span>
                                <ShieldCheck className="size-5 text-emerald-600" />
                            </div>
                            <div className="grid gap-5">
                                {[
                                    {
                                        icon: ClipboardList,
                                        title: 'Define the requirement',
                                        text: 'One set of items, criteria and commercial conditions.',
                                    },
                                    {
                                        icon: LockKeyhole,
                                        title: 'Receive sealed quotations',
                                        text: 'Suppliers prepare and submit their own structured offers.',
                                    },
                                    {
                                        icon: Scale,
                                        title: 'Compare with evidence',
                                        text: 'Price, delivery, payment and verified compliance.',
                                    },
                                    {
                                        icon: FileCheck2,
                                        title: 'Approve independently',
                                        text: 'A recorded decision with the exact quotation revision.',
                                    },
                                ].map(({ icon: Icon, title, text }, index) => (
                                    <div key={title} className="flex gap-4">
                                        <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                            <Icon className="size-5" />
                                        </div>
                                        <div>
                                            <p className="font-medium">
                                                <span className="text-muted-foreground mr-2 text-xs">
                                                    0{index + 1}
                                                </span>
                                                {title}
                                            </p>
                                            <p className="text-muted-foreground mt-1 text-sm leading-relaxed">
                                                {text}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </main>
                <footer className="text-muted-foreground mx-auto max-w-6xl border-t px-6 py-6 text-sm">
                    One buying organization. A shared process. An accountable
                    decision.
                </footer>
            </div>
        </>
    );
}
