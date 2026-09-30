import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, Check, Minus } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { dashboard, login, register } from '@/routes';

const steps = [
    {
        title: 'Define the requirement',
        text: 'Items, quantities, evaluation criteria and commercial conditions are fixed before any supplier is invited.',
    },
    {
        title: 'Receive sealed quotations',
        text: 'Invited suppliers prepare structured offers against the same lines. Nothing opens before the deadline.',
    },
    {
        title: 'Compare with evidence',
        text: 'Price, delivery and payment terms sit next to recorded compliance findings for every mandatory criterion.',
    },
    {
        title: 'Approve independently',
        text: 'An approver who took no part in the evaluation awards the exact quotation revision that was recommended.',
    },
];

const controls = [
    {
        term: 'Sealed submissions',
        detail: 'Quotation contents stay closed to buyers until the request deadline has passed.',
    },
    {
        term: 'Separation of duties',
        detail: 'The person who prepares an evaluation can never approve it. Administrators cannot read quotations.',
    },
    {
        term: 'Frozen evidence',
        detail: 'Published requirements and recommended revisions are locked, so the award matches what was compared.',
    },
    {
        term: 'Audit trail',
        detail: 'Every significant action is recorded with who, what and when, and is readable by auditors.',
    },
    {
        term: 'Strong authentication',
        detail: 'Two-factor authentication and passkeys for every account, including supplier representatives.',
    },
];

const roles = [
    {
        role: 'Buyer',
        can: 'Publishes requests, invites suppliers, evaluates quotations and recommends one.',
    },
    {
        role: 'Supplier',
        can: 'Sees its own invitations, asks clarifications, saves and submits quotations.',
    },
    {
        role: 'Approver',
        can: 'Approves, returns or rejects recommendations they did not prepare.',
    },
    {
        role: 'Auditor',
        can: 'Reads completed evidence and the full history of each request.',
    },
    {
        role: 'Administrator',
        can: 'Manages accounts, suppliers and currencies, without access to quotation contents.',
    },
];

const offers = [
    {
        supplier: 'Northline Scientific',
        total: '48,250.00',
        delivery: '14 days',
        payment: '30 days net',
        criteria: '6 / 6',
        eligible: true,
        recommended: true,
    },
    {
        supplier: 'Meridian Lab Supply',
        total: '46,900.00',
        delivery: '30 days',
        payment: 'Advance',
        criteria: '5 / 6',
        eligible: false,
        recommended: false,
    },
    {
        supplier: 'Arcadia Instruments',
        total: '51,120.00',
        delivery: '10 days',
        payment: '45 days net',
        criteria: '6 / 6',
        eligible: true,
        recommended: false,
    },
];

function ComparisonSheet() {
    return (
        <figure className="bg-card overflow-hidden rounded-lg border">
            <div className="flex flex-wrap items-center justify-between gap-x-6 gap-y-1 border-b px-4 py-3 text-sm sm:px-5">
                <p className="font-medium">
                    Laboratory equipment
                    <span className="text-muted-foreground ml-2 font-mono text-xs">
                        RFQ-2026-0142
                    </span>
                </p>
                <p className="text-muted-foreground text-xs">
                    3 quotations · closed 12 Sep, 17:00 · amounts in USD
                </p>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full min-w-[640px] text-left text-sm whitespace-nowrap">
                    <thead className="text-muted-foreground text-xs">
                        <tr className="border-b">
                            <th scope="col" className="px-5 py-2.5 font-medium">
                                Supplier
                            </th>
                            <th
                                scope="col"
                                className="px-3 py-2.5 text-right font-medium"
                            >
                                Total
                            </th>
                            <th scope="col" className="px-3 py-2.5 font-medium">
                                Delivery
                            </th>
                            <th scope="col" className="px-3 py-2.5 font-medium">
                                Payment
                            </th>
                            <th scope="col" className="px-3 py-2.5 font-medium">
                                Mandatory criteria
                            </th>
                            <th scope="col" className="px-5 py-2.5 font-medium">
                                Outcome
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {offers.map((offer) => (
                            <tr
                                key={offer.supplier}
                                className={
                                    offer.recommended
                                        ? 'bg-brand/5 shadow-[inset_2px_0_0_var(--brand)]'
                                        : undefined
                                }
                            >
                                <th
                                    scope="row"
                                    className="px-5 py-3 font-medium"
                                >
                                    {offer.supplier}
                                </th>
                                <td className="px-3 py-3 text-right tabular-nums">
                                    {offer.total}
                                </td>
                                <td className="text-muted-foreground px-3 py-3">
                                    {offer.delivery}
                                </td>
                                <td className="text-muted-foreground px-3 py-3">
                                    {offer.payment}
                                </td>
                                <td className="px-3 py-3">
                                    <span className="inline-flex items-center gap-1.5 tabular-nums">
                                        {offer.eligible ? (
                                            <Check
                                                aria-hidden="true"
                                                className="size-3.5 text-emerald-600 dark:text-emerald-400"
                                            />
                                        ) : (
                                            <Minus
                                                aria-hidden="true"
                                                className="size-3.5 text-red-600 dark:text-red-400"
                                            />
                                        )}
                                        {offer.criteria}
                                    </span>
                                </td>
                                <td className="px-5 py-3">
                                    {offer.recommended ? (
                                        <span className="text-brand font-medium">
                                            Recommended
                                        </span>
                                    ) : (
                                        <span className="text-muted-foreground">
                                            {offer.eligible
                                                ? 'Eligible'
                                                : 'Excluded'}
                                        </span>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <figcaption className="text-muted-foreground border-t px-4 py-3 text-xs sm:px-5">
                Illustrative example. The lowest price was excluded for failing
                a mandatory criterion; the recommendation now waits for an
                independent approver.
            </figcaption>
        </figure>
    );
}

function SectionHeading({
    label,
    title,
    id,
}: {
    label: string;
    title: string;
    id: string;
}) {
    return (
        <div className="lg:col-span-4">
            <p className="text-muted-foreground text-sm">{label}</p>
            <h2
                id={id}
                className="mt-2 text-2xl font-semibold tracking-tight text-balance sm:text-3xl"
            >
                {title}
            </h2>
        </div>
    );
}

function DefinitionRows({
    rows,
}: {
    rows: { term: string; detail: string }[];
}) {
    return (
        <dl className="divide-y border-y lg:col-span-8">
            {rows.map((row) => (
                <div
                    key={row.term}
                    className="grid gap-1 py-5 sm:grid-cols-3 sm:gap-6"
                >
                    <dt className="font-semibold">{row.term}</dt>
                    <dd className="text-muted-foreground leading-relaxed sm:col-span-2">
                        {row.detail}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

const primaryLink =
    'bg-primary text-primary-foreground hover:bg-primary/90 focus-visible:ring-ring inline-flex items-center gap-2 rounded-md font-medium transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none';

export default function Welcome() {
    const { auth } = usePage().props;
    const workspace = auth.user ? dashboard() : login();

    return (
        <>
            <Head title="Quotation comparison" />
            <div className="bg-background text-foreground min-h-dvh">
                <a
                    href="#main"
                    className="bg-primary text-primary-foreground sr-only z-50 rounded-md px-3 py-2 text-sm focus:not-sr-only focus:fixed focus:top-3 focus:left-3"
                >
                    Skip to content
                </a>
                <header className="border-b">
                    <div className="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
                        <Link
                            href="/"
                            className="focus-visible:ring-ring flex items-center gap-2.5 rounded-md font-semibold focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                        >
                            <span className="bg-primary text-primary-foreground flex size-7 items-center justify-center rounded-md">
                                <AppLogoIcon className="size-4" />
                            </span>
                            Quotation
                        </Link>
                        <nav
                            aria-label="Account"
                            className="flex items-center gap-1 text-sm"
                        >
                            {auth.user ? (
                                <Link
                                    href={dashboard()}
                                    className={`${primaryLink} h-9 px-3.5`}
                                >
                                    Open workspace
                                    <ArrowRight
                                        aria-hidden="true"
                                        className="size-4"
                                    />
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={login()}
                                        className="text-muted-foreground hover:text-foreground focus-visible:ring-ring inline-flex h-9 items-center rounded-md px-3 font-medium transition-colors focus-visible:ring-2 focus-visible:outline-none"
                                    >
                                        Sign in
                                    </Link>
                                    <Link
                                        href={register()}
                                        className={`${primaryLink} h-9 px-3.5`}
                                    >
                                        Create account
                                    </Link>
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                <main id="main">
                    <section className="mx-auto max-w-6xl px-4 pt-16 pb-12 sm:px-6 md:pt-24 md:pb-16">
                        <div className="grid gap-8 lg:grid-cols-12">
                            <h1 className="text-4xl leading-[1.08] font-semibold tracking-tight text-balance sm:text-5xl lg:col-span-8 lg:text-6xl">
                                Supplier quotations, compared on evidence and
                                approved independently.
                            </h1>
                            <div className="flex flex-col justify-end gap-6 lg:col-span-4">
                                <p className="text-muted-foreground text-lg leading-relaxed">
                                    One workspace for requests, sealed offers
                                    and awards. Every decision keeps the record
                                    of why it was made.
                                </p>
                                <div className="flex flex-wrap items-center gap-x-5 gap-y-3">
                                    <Link
                                        href={workspace}
                                        className={`${primaryLink} h-11 px-5`}
                                    >
                                        Enter your workspace
                                        <ArrowRight
                                            aria-hidden="true"
                                            className="size-4"
                                        />
                                    </Link>
                                    <a
                                        href="#process"
                                        className="decoration-border hover:decoration-foreground text-sm font-medium underline underline-offset-4 transition-colors"
                                    >
                                        How it works
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div className="mt-14 md:mt-20">
                            <ComparisonSheet />
                        </div>
                    </section>

                    <section
                        id="process"
                        aria-labelledby="process-title"
                        className="border-t"
                    >
                        <div className="mx-auto grid max-w-6xl gap-10 px-4 py-16 sm:px-6 md:py-24 lg:grid-cols-12">
                            <SectionHeading
                                id="process-title"
                                label="Process"
                                title="Four steps, one record."
                            />
                            <ol className="grid gap-x-8 gap-y-10 sm:grid-cols-2 lg:col-span-8">
                                {steps.map((step, index) => (
                                    <li
                                        key={step.title}
                                        className="border-t pt-5"
                                    >
                                        <p className="text-muted-foreground font-mono text-sm tabular-nums">
                                            {String(index + 1).padStart(2, '0')}
                                        </p>
                                        <h3 className="mt-3 font-semibold">
                                            {step.title}
                                        </h3>
                                        <p className="text-muted-foreground mt-2 leading-relaxed">
                                            {step.text}
                                        </p>
                                    </li>
                                ))}
                            </ol>
                        </div>
                    </section>

                    <section
                        aria-labelledby="controls-title"
                        className="bg-muted/50 border-t"
                    >
                        <div className="mx-auto grid max-w-6xl gap-10 px-4 py-16 sm:px-6 md:py-24 lg:grid-cols-12">
                            <SectionHeading
                                id="controls-title"
                                label="Controls"
                                title="Built for procurement that will be reviewed."
                            />
                            <DefinitionRows rows={controls} />
                        </div>
                    </section>

                    <section aria-labelledby="roles-title" className="border-t">
                        <div className="mx-auto grid max-w-6xl gap-10 px-4 py-16 sm:px-6 md:py-24 lg:grid-cols-12">
                            <SectionHeading
                                id="roles-title"
                                label="Roles"
                                title="Each person sees only their part."
                            />
                            <DefinitionRows
                                rows={roles.map(({ role, can }) => ({
                                    term: role,
                                    detail: can,
                                }))}
                            />
                        </div>
                    </section>

                    <section className="border-t">
                        <div className="mx-auto flex max-w-6xl flex-col gap-6 px-4 py-16 sm:px-6 md:flex-row md:items-end md:justify-between md:py-20">
                            <div>
                                <h2 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                                    Every decision, documented.
                                </h2>
                                <p className="text-muted-foreground mt-3 max-w-xl text-lg">
                                    Supplier access is invitation-only. Your
                                    administrator assigns roles and permissions.
                                </p>
                            </div>
                            <Link
                                href={workspace}
                                className={`${primaryLink} h-11 shrink-0 self-start px-5 md:self-auto`}
                            >
                                Enter your workspace
                                <ArrowRight
                                    aria-hidden="true"
                                    className="size-4"
                                />
                            </Link>
                        </div>
                    </section>
                </main>

                <footer className="border-t">
                    <div className="text-muted-foreground mx-auto flex max-w-6xl flex-col gap-2 px-4 py-6 text-sm sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <p className="text-foreground font-medium">Quotation</p>
                        <p>
                            One buying organization. A shared process. An
                            accountable decision.
                        </p>
                    </div>
                </footer>
            </div>
        </>
    );
}
