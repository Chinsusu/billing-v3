# Sprint 42-46 UI Foundation Design

## Goal

Make the Billing v3 Blade UI clearer, more consistent, and usable on desktop and mobile without changing billing, provisioning, wallet, or authentication behavior.

## Scope

This sprint covers the shared customer/admin shell, common visual components, key dashboards, table-heavy admin screens, and screenshot QA. It does not introduce a SPA framework, change backend workflows, or redesign reseller/accounting features.

## UX Direction

Use an operational SaaS style: restrained, dense enough for repeated admin work, and obvious about state and next action. Customer pages should emphasize wallet, unpaid invoices, services, and renewal alerts. Admin pages should emphasize queues, health, failed actions, integrations, and fast navigation to workbench screens.

## Design System

- Keep Public Sans and the existing dark sidebar pattern.
- Use indigo for customer primary actions.
- Use blue/teal for admin primary actions; reserve red for destructive or failed states.
- Add shared Blade/CSS primitives for page headers, stat cards, action groups, responsive tables, filter bars, status badges, empty states, and compact form sections.
- Keep card radius at 8px and avoid card nesting where the card is not a distinct repeated item.

## Screens

- Customer dashboard becomes a scannable account overview with key stats and recent activity cards.
- Admin dashboard becomes an operations command center instead of a long list of duplicate navigation buttons.
- Admin tables get consistent responsive behavior so mobile never overflows outside the viewport.
- Provider accounts, bank integrations, customers, ops health, and API keys get clearer action placement and safer visual hierarchy.

## Accessibility and Responsive Rules

- Topbars must wrap or hide non-essential metadata on narrow viewports.
- Tables must scroll inside their panel on mobile.
- Footer text must wrap and must not overflow.
- Letter spacing remains zero.
- Buttons keep stable padding and do not rely on color alone for destructive meaning.

## Verification

Run targeted Laravel feature tests, Laravel Pint, browser screenshot checks for desktop and mobile, and smoke the same key pages on the test server.
