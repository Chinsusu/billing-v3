# Sprint 42-46 UI Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox syntax for tracking.

**Goal:** Upgrade the Blade UI foundation so Billing v3 is clearer, more consistent, and responsive across customer and admin workflows.

**Architecture:** Add shared anonymous Blade components and a shared layout override partial instead of rewriting the app or changing backend logic. Convert the highest-traffic dashboards and table screens first, then verify with Laravel tests and Playwright screenshots.

**Tech Stack:** Laravel Blade, embedded CSS, anonymous Blade components, PHPUnit feature tests, Playwright screenshot QA.

**Desktop-first addendum:** Admin and customer desktop overrides live in separate Blade partials. Desktop polish targets browser widths first; mobile remains protected against overflow and will get a dedicated pass later.

---

### Task 1: Shared UI Foundation

**Files:**
- Create: `apps/backend-laravel/resources/views/layouts/partials/ui-foundation.blade.php`
- Create: `apps/backend-laravel/resources/views/layouts/partials/ui-desktop-admin.blade.php`
- Create: `apps/backend-laravel/resources/views/layouts/partials/ui-desktop-customer.blade.php`
- Modify: `apps/backend-laravel/resources/views/layouts/app.blade.php`
- Modify: `apps/backend-laravel/resources/views/layouts/admin.blade.php`

- [x] Add shared CSS tokens for customer/admin primary colors, panels, buttons, tables, topbars, footers, and mobile behavior.
- [x] Split desktop admin and customer overrides into separate partials.
- [x] Include the shared partial from both layouts after their existing CSS blocks.
- [x] Add stable shell body classes for customer and admin layouts.
- [x] Fix the customer footer year output.

### Task 2: Blade Components

**Files:**
- Create: `apps/backend-laravel/resources/views/components/page-header.blade.php`
- Create: `apps/backend-laravel/resources/views/components/stat-card.blade.php`
- Create: `apps/backend-laravel/resources/views/components/empty-state.blade.php`
- Create: `apps/backend-laravel/resources/views/components/status-badge.blade.php`

- [x] Add small anonymous components for repeated page headers, stats, empty states, and badges.
- [x] Keep components presentation-only and pass all data from existing views.

### Task 3: Customer UX Polish

**Files:**
- Modify: `apps/backend-laravel/resources/views/dashboard.blade.php`
- Modify: `apps/backend-laravel/resources/views/products/index.blade.php`
- Modify: `apps/backend-laravel/resources/views/api-keys/index.blade.php`

- [x] Convert the customer dashboard to stat cards and recent activity panels.
- [x] Polish the product catalog cards for easier scanning.
- [x] Make API key forms and tables use consistent sections and responsive table behavior.

### Task 4: Admin UX Polish

**Files:**
- Modify: `apps/backend-laravel/resources/views/admin/dashboard.blade.php`
- Modify: `apps/backend-laravel/resources/views/admin/customers/index.blade.php`
- Modify: `apps/backend-laravel/resources/views/admin/provisioning-provider-accounts/index.blade.php`
- Modify: `apps/backend-laravel/resources/views/admin/bank-integrations/index.blade.php`
- Modify: `apps/backend-laravel/resources/views/admin/ops-health/index.blade.php`

- [x] Replace duplicate dashboard navigation buttons with operations-focused cards and quick actions.
- [x] Standardize filters, primary actions, tables, and empty states.
- [x] Use badges for health, enabled/disabled, configured, and queue state.

### Task 5: Verification

- [x] Run Laravel feature tests covering dashboard, catalog, API keys, admin operations, provider accounts, bank integrations, and ops health.
- [x] Run Laravel Pint.
- [x] Capture desktop and mobile screenshots for login, products, customer dashboard, admin dashboard, admin customers, provider accounts, ops health, and API keys.
- [x] Confirm mobile tables scroll inside their panel and topbars/footers no longer overflow.
