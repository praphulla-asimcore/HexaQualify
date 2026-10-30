# HexaQualify

*Pre-pitch vendor screening for Hexamatics Group.* Built from [`docs/hexaqualify-spec.md`](docs/hexaqualify-spec.md) (v1.1), Phase 1 plus the AI-suggested scoring and reminder emails from Phase 2.

Vendors get a secure invitation link, verify their email with a one-time code, fill in the questionnaire for their service category and submit. The server scores the answers against a hidden guide, applies knockout rules and returns **Qualified**, **Not qualified** or **Needs review**. A reviewer confirms the result or overrides it, and must give a reason to override.

## Stack

Laravel 12 · PHP 8.3 · PostgreSQL 16 · Redis queues · Inertia + Vue 3 · Tailwind CSS · Pest. The spec asked for Laravel 11, but 11 is end-of-life and `composer audit` reports five unpatched framework advisories for it. Laravel 12 has the same structure and passes the audit.

## Running locally

```bash
composer install && npm ci && npm run build
cp .env.example .env && php artisan key:generate
# set DB_* for a local Postgres, then:
php artisan migrate --seed                         # 4 starter questionnaires (Appendix B)
php artisan db:seed --class=DemoSeeder             # optional demo staff + vendors (not for production)
php artisan serve
```

Set `HQ_DEV_LOGIN=true` to get a "Sign in as admin, reviewer or viewer" form on `/login` without Google. It is disabled in production. For real SSO, set `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET`, and set `HQ_BOOTSTRAP_ADMIN` to the first admin's email before seeding.

Run the queue worker and scheduler for emails, reminders, expiry and the retention purge:

```bash
php artisan queue:work & php artisan schedule:work
```

Tests run against Postgres (`hexaqualify_test`): `vendor/bin/pest`.

## Where things live

| Concern | Location |
|---|---|
| Scoring engine (pure, §6.1) | `app/Services/Scoring/ScoringEngine.php` · tests `tests/Unit/ScoringEngineTest.php` |
| Split of vendor-safe structure / hidden guide, versioning | `app/Services/QuestionnaireService.php` |
| Vendor portal (token + OTP) | `app/Http/Controllers/Vendor/PortalController.php` |
| Vendor payload leak test (§7) | `tests/Feature/VendorPayloadTest.php` |
| AI suggestions (§6.4) | `app/Services/AiScoringService.php` |
| Design tokens / glass (§10) | `resources/css/tokens.css`, `resources/css/app.css`, `/styleguide` |
| Scheduled jobs | `routes/console.php` (`hexaqualify:expire`, `:remind`, `:purge`) |

## Security notes (§7)

- Invitation tokens are 32 CSPRNG bytes and only their SHA-256 is stored. Because of this, **Resend issues a new link** and the old one stops working. The link is shown to staff once, after it is created or resent.
- OTP codes are HMAC-hashed and valid for 10 minutes. After 5 wrong attempts the invitation is locked for 15 minutes. OTP and token endpoints are rate-limited per IP and per token.
- Vendor endpoints only read `structure_json`. The scoring guide lives in `scoring_guides` and is never loaded by `PortalController`.
- Strict CSP with per-request nonces, HSTS over TLS, `SameSite=Lax`, secure, HttpOnly and encrypted sessions. Fonts are self-hosted, so the CSP allows no third-party sources.
- `audit_log` is append-only, enforced by a Postgres trigger as well as in the model. CSV exports are protected against formula injection.

## Not yet done / needs input

- **Official brand files.** `hexabusiness.com` is blocked from the build environment, so `public/brand/hexa-logo.png` is the logo supplied with the request. The favicons are a crop of its "H" until `Hexa-Icon.png` and the SVG logos are dropped into `public/brand/`. Brand colours were sampled from the logo gradient: `#BC0FA5 → #6A10CD → #0013FF`.
- **Light-theme review amber** is `#8A5700` instead of `#9A6200`, so badge text keeps ≥ 4.5:1 contrast on its tinted background.
- **Phase 2 items not built:** file/evidence uploads with ClamAV (the table and scoring rule already exist), request-clarification loop, CSV bulk invite, and XLSX export (CSV export is in).
- **Infrastructure items:** backups, encryption at rest, hosting region, pen test and the axe/Lighthouse audits.
