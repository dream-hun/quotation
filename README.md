# Quotation

A procurement workspace for requesting, comparing and approving supplier quotations. Buyers publish a request with fixed items and evaluation criteria, invited suppliers submit sealed quotations before a deadline, the buyer records compliance findings and recommends one offer, and an independent approver makes the award. Every significant action is kept in an audit trail.

Product scope and acceptance criteria are in [`docs/`](docs):

- [01 Product Specification](docs/01%20Product%20Specification.md)
- [02 User Requirements](docs/02%20User%20Requirements.md)
- [03 Database Design](docs/03%20Database%20Design.md)
- [04 System Architecture](docs/04%20System%20Architecture.md)

## Stack

- PHP 8.4+ with Laravel 13, Fortify authentication (passwords, two-factor, passkeys) and Spatie permissions
- React with Inertia v3, Tailwind CSS and Wayfinder route helpers
- MySQL (database-backed sessions, cache and queue)
- Pest, PHPStan and Pint for quality checks

## Requirements

- PHP 8.4 or newer (the money calculations use `BcMath\Number`) with the `bcmath`, `pdo_mysql`, `mbstring` and `fileinfo` extensions
- Composer 2
- Node.js 22 or newer and npm
- MySQL 8

## Local setup

1. Create an empty MySQL database named `quotation` (or adjust `DB_*` in `.env`).
2. Install dependencies, create `.env`, generate the app key, migrate and build assets:

    ```bash
    composer run setup
    ```

3. Optionally load demo data (see [Demo accounts](#demo-accounts)):

    ```bash
    php artisan db:seed
    ```

4. Start the app server, log viewer and Vite:

    ```bash
    composer run dev
    ```

5. In a second terminal, start the scheduler. Requests do not close at their deadline and emails are not sent without it:

    ```bash
    php artisan schedule:work
    ```

If you use Laravel Herd or Valet, the site is also available at `http://quotation.test`.

## First administrator

There are no default administrator credentials. On a fresh installation:

1. Register an account through the app at `/register` and verify its email. With the default `log` mailer, the verification link is written to `storage/logs/laravel.log`.
2. Grant it the administrator role:

    ```bash
    php artisan procurement:setup you@example.com
    ```

    The command refuses to run once an administrator exists.

3. Sign in and use **Administration** to set the buying organization and timezone, add currencies and suppliers, and create buyer, approver, auditor and supplier accounts.

An account cannot be both a supplier representative and hold an internal role.

## Roles

| Role                    | Can                                                                                                    |
| ----------------------- | ------------------------------------------------------------------------------------------------------ |
| Buyer                   | Create and publish requests, invite suppliers, extend or cancel, evaluate quotations and recommend one |
| Supplier representative | See its own invitations, save and submit quotations, withdraw, ask clarifications                      |
| Approver                | Approve, return or reject a recommendation assigned to them (never one they created or evaluated)      |
| Auditor                 | Read completed evidence and audit history                                                              |
| Administrator           | Manage accounts, suppliers, currencies and the organization (no access to quotation contents)          |

## Background processing

A single scheduled command does all background work:

```bash
php artisan procurement:process
```

It closes requests whose deadline has passed, delivers pending notification emails (failed sends are retried up to five times), prunes abandoned upload chunks and records a worker heartbeat. The scheduler runs it every minute (see `routes/console.php`).

In production, add this cron entry for the application user:

```
* * * * * cd /path/to/quotation && php artisan schedule:run >> /dev/null 2>&1
```

Check that the scheduler is alive with:

```bash
php artisan procurement:process --health
```

The Administration page also shows the worker heartbeat and the count of failed email notifications. In-app notifications are always recorded, even if email delivery fails.

## Demo accounts

`php artisan db:seed` creates a demo organization, one request (`RFQ-DEMO-001`) and these accounts. Every one has the password `password`. Use them only for local development.

| Email                           | Role                                                                               |
| ------------------------------- | ---------------------------------------------------------------------------------- |
| `test@example.com`              | No role; grant administrator with `php artisan procurement:setup test@example.com` |
| `buyer@procurement.example`     | Buyer                                                                              |
| `approver@procurement.example`  | Approver                                                                           |
| `supplier1@procurement.example` | Kigali Office Supplies                                                             |
| `supplier2@procurement.example` | Umucyo Furniture                                                                   |
| `supplier3@procurement.example` | Imena Trading                                                                      |

## Production deployment

1. Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` and serve the site over HTTPS.
2. Configure a real mailer (`MAIL_MAILER`, host and credentials, `MAIL_FROM_ADDRESS`). The default `log` mailer only writes emails to `storage/logs`.
3. Deploy:

    ```bash
    composer install --no-dev --optimize-autoloader
    npm ci && npm run build
    php artisan migrate --force
    php artisan optimize
    ```

4. Add the scheduler cron entry above.
5. Back up the database and `storage/app` together. Uploaded documents live on the `local` disk, and the database references them.

Uploads are limited to 10 files per record and accept PDF, DOCX, XLSX, PNG and JPEG. The per-file size limit defaults to 10 MB and is set with `PROCUREMENT_MAX_UPLOAD_KILOBYTES`. Set `PROCUREMENT_REQUIRE_QUOTATION_ATTACHMENT=true` to require suppliers to attach at least one document before submitting.

## Quality checks

```bash
composer run test      # Pint, PHPStan and the Pest suite
npm run check          # frontend formatting and lint
npm run types:check    # TypeScript
```

`composer run ci:check` runs everything the CI workflow runs.
