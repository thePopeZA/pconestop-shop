# Midvaal Gym — Claude Instructions

## Stack
- Laravel 12 · PHP 8.2 · Blade templates · Tailwind CSS 4 · Vite 7
- MySQL · Laravel queues/jobs · DomPDF for PDFs
- No React/Vue — server-rendered Blade only

## Project layout
```
app/Http/Controllers/Admin/   → all staff-facing controllers
app/Http/Controllers/Member/  → member portal controllers
app/Http/Controllers/         → Auth, Kiosk, Signup, Signing
app/Services/                 → business logic (PricingService, ArrearsService, etc.)
resources/views/admin/        → staff UI
resources/views/member/       → member portal
resources/views/kiosk*        → front-door kiosk
resources/views/layouts/      → base layouts
resources/js/                 → Vite entry points (vanilla JS only)
database/migrations/          → schema history
docs/SYSTEM_MAP.md            → full feature inventory with LIVE/DORMANT/PENDING status
```

## Coding rules
- Always check `docs/SYSTEM_MAP.md` before adding/changing features — it tracks what's LIVE, DORMANT, PENDING
- Gates: `NOTIFICATIONS_LIVE` (WhatsApp), `IKHOKHA_LIVE` (payments) — never remove these guards
- Netcash: system NEVER writes to Netcash — imports only, raises staff to-dos
- Membership types are a fixed set: `single | couple | family | casual` — never expand without explicit instruction
- Permissions gates: `view-admin`, `manage-records`, `view-financials`, `delete-records`, `override-member-status`, `approve-cancellations`, `manage-users`, `manage-pricing`, `record-payments`
- Blade only — no JS frameworks, minimal JS, Tailwind utility classes
- Follow existing controller/service patterns — no new architectural patterns without asking

## Key services
- `PricingService` — all price calculations, never inline pricing logic
- `ArrearsService` — owing classification
- `MembershipStateService` — freeze/runaway/lifecycle
- `CancellationService` — cancellation engine

## Environment flags
```
NOTIFICATIONS_LIVE=false   # WhatsApp — default OFF
IKHOKHA_LIVE=false         # Online payments — default OFF
```

## Pending work (not yet built — see SYSTEM_MAP.md for detail)
1. Netcash DO-report import UI + account-holder matching
2. Scheduler (arrears monthly + notification dispatch daily)
3. Notification dispatcher + master ON/OFF switch + preview file
4. Full 8-message notification set with timing rules
5. Fully-computed billing (auto age-tier updates)
6. Member pay-history display on Billing tab