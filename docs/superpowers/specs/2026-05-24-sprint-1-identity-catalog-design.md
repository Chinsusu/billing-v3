# Sprint 1 Identity Catalog Design

## Goal

Ship the first usable application layer on top of Sprint 0: session authentication, role-based admin access, product catalog management, and simple customer/admin dashboard shells.

## Scope

Sprint 1 includes:

- Email/password register, login, logout using Laravel session auth.
- Spatie Permission roles: `super_admin`, `ops_admin`, `support`, `finance`, `customer`, and `reseller`.
- Initial permissions for product and dashboard access: `admin.access`, `products.view`, `products.create`, `products.update`, `products.delete`.
- User model integration with Spatie roles.
- Product model, migration, factory, admin CRUD controller, and customer product listing.
- Blade dashboard shells for `/dashboard`, `/admin`, `/admin/products`, and `/products`.
- Seeds for roles, permissions, an admin user, customer user, and sample products.

Sprint 1 does not include wallet, invoice, payment, order, service provisioning, Vuexy assets, RabbitMQ worker behavior, or provider integration.

## Architecture

Laravel remains the control plane. Authentication uses first-party Laravel guards and sessions instead of adding a UI starter kit, keeping the surface small and easy to test. Authorization uses Spatie Permission because later finance/provider actions need explicit permissions and auditable roles.

Products are stored in a dedicated `products` table with integer VND pricing (`price_amount` as `BIGINT`). Controllers remain thin and validate requests through Laravel form requests. Views use plain Blade layouts for now; Vuexy integration can replace the layout later without changing route contracts.

## Routes

- `GET /register`, `POST /register`
- `GET /login`, `POST /login`
- `POST /logout`
- `GET /dashboard`
- `GET /products`
- `GET /admin`
- `GET /admin/products`
- `GET /admin/products/create`, `POST /admin/products`
- `GET /admin/products/{product}/edit`, `PUT /admin/products/{product}`
- `DELETE /admin/products/{product}`

## Data Model

`products` fields:

- `id` UUID primary key
- `code` unique string
- `name`
- `type` enum-like string: `proxy` or `vps`
- `status` enum-like string: `active`, `draft`, `archived`
- `price_amount` integer minor-unit amount
- `currency` default `VND`
- `duration_days`
- `description`
- `config` JSON
- timestamps

## Testing

Feature tests cover:

- Register creates a customer and redirects to the customer dashboard.
- Login/logout works for existing users.
- Guest cannot access customer/admin pages.
- Customer cannot access admin pages.
- Admin can access admin dashboard and create/update/archive products.
- Customer product listing only shows active products.
- Seeders create required roles/permissions and sample users.

The Sprint 1 acceptance gate is:

- `php artisan test` passes.
- `./vendor/bin/pint --test` passes.
- Existing Go worker checks still pass.
- GitHub Actions pass on the feature branch.
