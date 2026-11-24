# Ecommerce Dashboard - Copilot Instructions

## Project
- PHP 8 + mysqli/MySQL + Bootstrap 5, no framework; pages are direct PHP entry points.
- Main entry: `index.php`; database/session/bootstrap: `config.php`; SQL dump reference: `backups/`.
- Modules: `customers/`, `products/`, `categories/`, `sales/orders/`, `sales/payments/`, `sales/returns/`, `sales/reports/`, `sales/pos/`, `inventory/`, `users/`, `notifications/`.
- Shared code: `layouts/header.php`, `sidebar.php`, `topbar.php`, `footer.php`, `global-search-control.php`; `includes/rbac.php`, `app-settings.php`, `error-handler.php`, `admin-notifications.php`, `pos-checkout.php`.
- Main assets: `assets/css/style.css`, `assets/js/*.js`; uploaded files belong in `uploads/`.
- Dependencies are in `composer.json`; vendor packages include PHPMailer and Stripe.

## Architecture And Security
- Start every protected page through `config.php` and preserve `checkAuth()`, `enforceAccess()`, `appUserCan()`, and `appModuleEnabled()`.
- Use prepared statements (`prepare`/`bind_param`) for database input; never build SQL from raw request values.
- Verify CSRF with `verifyCsrfToken()` for state-changing POST actions and use `csrfToken()` in forms.
- Escape all dynamic HTML with `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`; return JSON or call `sendAppError()` for endpoint failures.
- Use password hashing APIs, strict integer validation, `utf8mb4`, transactions for multi-table writes, and soft deletes (`is_deleted`) where the module uses them.
- Preserve module names, permissions, role management, activity logs, and module-status checks. Do not add routes or database writes outside the existing RBAC model.
- `config.php` also loads app settings and RBAC and may ensure schema elements at runtime. Read existing patterns before adding a table or column.

## Layout And Design
- Reuse `layouts/header.php` (loads Bootstrap, icons, fonts, shared CSS/JS) and include `sidebar.php` plus `topbar.php` for dashboard pages.
- Default design: navy `#14243d`, gold `#c6a15b`, cream `#f5f0e6`, white surfaces, `Inter` for UI text, `Playfair Display` for dashboard headings.
- Use CSS variables and existing classes from `assets/css/style.css`; key patterns include `.app`, `.app-sidebar`, `.app-main`, `.topbar`, `.page-head`, `.card`, `.stat-card`, `.table-card`, `.table`, `.btn`, and `.form-control`.
- Keep the existing card, border, shadow, rounded, spacing, icon, status, breadcrumb, pagination, and table patterns; use Bootstrap 5 utilities before adding custom CSS.
- Keep desktop, tablet, and mobile behavior; sidebar becomes a Bootstrap offcanvas below desktop. POS has its own compact fixed-height layout.
- Preserve dynamic themes, store name/logo, language/RTL, currency, and module-enabled behavior. Do not hardcode a second design system.

## Coding Conventions
- Make focused PHP changes; follow each module's existing CRUD/search/pagination/action pattern and place reusable logic in `includes/` or `layouts/`.
- Prefer existing JS files and CDN-loaded Bootstrap/SweetAlert/Bootstrap Icons. Do not duplicate AJAX handlers or CSS for a feature.
- Use `$_GET` for filters and pagination, `$_POST` for mutations, and redirects after successful mutations.
- Keep destructive operations protected and prefer soft delete/trash/restore behavior.
- Validate uploads and IDs; store uploads in `uploads/` and preserve existing image fallbacks.
- Use the latest SQL dump only as schema/data reference; do not modify backup files or seed/restore data during normal feature work.

## Database Relationships
- Core entities: `admins` -> `roles` -> `role_permissions` -> `permissions`; module status is stored in `module_status`.
- `categories` -> `products`; `products` -> `inventory` and `inventory_movements`.
- `customers` <- `orders` -> `order_items` -> `products`; `coupons` and `shipping` may relate to orders.
- `payments`, `returns`, and `notifications` are sales operations tables. Preserve foreign keys and decimal money fields.
- Prefer existing fields such as `is_deleted`, `status`, `created_at`, and `updated_at`; inspect the live schema/matching SQL dump before schema changes.

## Development Workflow
- Analyze relevant files and dependencies only; preserve unrelated code.
- Before changing UI, inspect the destination page, its layout include, shared CSS, and matching module page.
- Before changing data behavior, inspect its create/edit/delete/restore page and SQL relationships.
- Run PHP syntax checks (`php -l`) for changed PHP files and verify both direct and AJAX paths where present.
- Test permissions, CSRF, soft delete/restore, empty states, filters, pagination, uploads, mobile layout, and POS stock changes.
