<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Models\ProvisioningProviderAccount;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProductController extends Controller
{
    private const AUDIT_FIELDS = ['code', 'name', 'type', 'status', 'price_amount', 'currency', 'duration_days', 'auto_renew_allowed', 'auto_renew_window_hours', 'auto_renew_retry_delay_minutes', 'auto_renew_max_attempts', 'description', 'provider_account_id', 'provider_plan_code', 'provider_region', 'provider_provision_path', 'provider_options', 'lifecycle_source', 'lifecycle_unit', 'lifecycle_count', 'provider_lifecycle_path', 'provider_lifecycle_ordered_at_path', 'provider_lifecycle_expires_at_path', 'provider_lifecycle_date_format', 'provider_lifecycle_timezone', 'provider_renew_path', 'provider_suspend_path', 'provider_cancel_path', 'provider_sync_path'];

    public function index(): View
    {
        return view('admin.products.index', [
            'products' => Product::with('providerAccount')->withCount('providerRoutes')->orderBy('created_at', 'desc')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.products.create', [
            'product' => new Product(['provider_options' => []]),
            'providerAccounts' => ProvisioningProviderAccount::orderBy('name')->get(),
        ]);
    }

    public function store(StoreProductRequest $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validated();
        $routes = $this->routesForSave($validated['provider_routes'] ?? []);
        $product = Product::create($this->attributesForSave($validated, null));
        $this->syncProviderRoutes($product, $routes);
        $audit->record($request->user(), 'created', $product, [], $audit->snapshot($product, self::AUDIT_FIELDS), [], $request);

        return redirect('/admin/products')->with('status', 'Product created.');
    }

    public function edit(Product $product): View
    {
        return view('admin.products.edit', [
            'product' => $product->load('providerRoutes.providerAccount'),
            'providerAccounts' => ProvisioningProviderAccount::orderBy('name')->get(),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validated();
        $routes = $this->routesForSave($validated['provider_routes'] ?? []);
        $before = $audit->snapshot($product, self::AUDIT_FIELDS);
        $product->update($this->attributesForSave($validated, $product));
        $this->syncProviderRoutes($product, $routes);
        $product->refresh();
        [$beforeChanges, $afterChanges] = $audit->diff($before, $audit->snapshot($product, self::AUDIT_FIELDS));
        $audit->record($request->user(), 'updated', $product, $beforeChanges, $afterChanges, [], $request);

        return redirect('/admin/products')->with('status', 'Product updated.');
    }

    public function destroy(Product $product, AuditLogger $audit): RedirectResponse
    {
        abort_unless(request()->user()?->can('products.delete'), 403);

        $before = $audit->snapshot($product, self::AUDIT_FIELDS);
        $product->update(['status' => 'archived']);
        $product->refresh();
        [$beforeChanges, $afterChanges] = $audit->diff($before, $audit->snapshot($product, self::AUDIT_FIELDS));
        $audit->record(request()->user(), 'archived', $product, $beforeChanges, $afterChanges, [], request());

        return redirect('/admin/products')->with('status', 'Product archived.');
    }

    private function attributesForSave(array $attributes, ?Product $product): array
    {
        unset($attributes['provider_routes']);
        $cloudminiOptions = $attributes['cloudmini_options'] ?? [];
        unset($attributes['cloudmini_options']);

        $attributes['config'] = $product?->config ?? [];
        $attributes['provider_account_id'] = $this->nullableString($attributes['provider_account_id'] ?? null);
        $attributes['provider_plan_code'] = $this->nullableString($attributes['provider_plan_code'] ?? null);
        $attributes['provider_region'] = $this->nullableString($attributes['provider_region'] ?? null);
        $attributes['provider_provision_path'] = $this->nullableString($attributes['provider_provision_path'] ?? null);
        $attributes['provider_options'] = array_replace(
            $this->jsonObject($attributes['provider_options'] ?? null),
            $this->cloudminiOptionsForSave(is_array($cloudminiOptions) ? $cloudminiOptions : []),
        );
        $attributes['provider_lifecycle_path'] = $this->nullableString($attributes['provider_lifecycle_path'] ?? null);
        $attributes['provider_lifecycle_ordered_at_path'] = $this->nullableString($attributes['provider_lifecycle_ordered_at_path'] ?? null);
        $attributes['provider_lifecycle_expires_at_path'] = $this->nullableString($attributes['provider_lifecycle_expires_at_path'] ?? null);
        $attributes['provider_renew_path'] = $this->nullableString($attributes['provider_renew_path'] ?? null);
        $attributes['provider_suspend_path'] = $this->nullableString($attributes['provider_suspend_path'] ?? null);
        $attributes['provider_cancel_path'] = $this->nullableString($attributes['provider_cancel_path'] ?? null);
        $attributes['provider_sync_path'] = $this->nullableString($attributes['provider_sync_path'] ?? null);

        return $attributes;
    }

    private function syncProviderRoutes(Product $product, array $routes): void
    {
        $product->providerRoutes()->delete();

        foreach ($routes as $route) {
            if (($route['provider_account_id'] ?? null) === null || ($route['billing_group_id'] ?? null) === null) {
                continue;
            }

            $product->providerRoutes()->create($route);
        }
    }

    private function routesForSave(array $routes): array
    {
        return collect($routes)
            ->flatMap(function (array $route): array {
                $locations = collect($route['locations'] ?? [])
                    ->map(fn (mixed $location): ?string => $this->nullableString($location))
                    ->filter()
                    ->unique()
                    ->values();

                if ($locations->isEmpty() && $this->nullableString($route['billing_group_id'] ?? null) !== null) {
                    $locations = collect([$this->nullableString($route['billing_group_id'])]);
                }

                $baseRoute = [
                    'provider_account_id' => $this->nullableString($route['provider_account_id'] ?? null),
                    'enabled' => (bool) ($route['enabled'] ?? false),
                    'priority' => max(1, (int) ($route['priority'] ?? 100)),
                    'weight' => max(1, (int) ($route['weight'] ?? 100)),
                    'node_selector_type' => $route['node_selector_type'] ?? 'auto',
                    'node_name' => $this->nullableString($route['node_name'] ?? null),
                    'options' => $this->jsonObject($route['options'] ?? null),
                ];

                return $locations
                    ->map(fn (string $location): array => $baseRoute + ['billing_group_id' => $location])
                    ->all();
            })
            ->filter(fn (array $route): bool => $route['provider_account_id'] !== null && $route['billing_group_id'] !== null)
            ->values()
            ->all();
    }

    private function cloudminiOptionsForSave(array $options): array
    {
        return collect([
            'kind' => $this->nullableString($options['kind'] ?? null),
            'protocol' => $this->nullableString($options['protocol'] ?? null),
            'speed_limit_mbps' => $this->nullableInteger($options['speed_limit_mbps'] ?? null),
            'bandwidth_limit_mb' => $this->nullableInteger($options['bandwidth_limit_mb'] ?? null),
            'preferred_outbound_ip' => $this->nullableString($options['preferred_outbound_ip'] ?? null),
            'reserve_capacity' => (bool) ($options['reserve_capacity'] ?? false),
        ])
            ->reject(fn (mixed $value, string $key): bool => $value === null || ($key === 'reserve_capacity' && $value === false))
            ->all();
    }

    private function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return $value === '' ? null : $value;
    }

    private function nullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function jsonObject(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
