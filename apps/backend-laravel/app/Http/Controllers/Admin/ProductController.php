<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Models\ProvisioningProviderAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        return view('admin.products.index', [
            'products' => Product::with('providerAccount')->orderBy('created_at', 'desc')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.products.create', [
            'product' => new Product(['provider_options' => []]),
            'providerAccounts' => ProvisioningProviderAccount::orderBy('name')->get(),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        Product::create($this->attributesForSave($request->validated(), null));

        return redirect('/admin/products')->with('status', 'Product created.');
    }

    public function edit(Product $product): View
    {
        return view('admin.products.edit', [
            'product' => $product,
            'providerAccounts' => ProvisioningProviderAccount::orderBy('name')->get(),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($this->attributesForSave($request->validated(), $product));

        return redirect('/admin/products')->with('status', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        abort_unless(request()->user()?->can('products.delete'), 403);

        $product->update(['status' => 'archived']);

        return redirect('/admin/products')->with('status', 'Product archived.');
    }

    private function attributesForSave(array $attributes, ?Product $product): array
    {
        $attributes['config'] = $product?->config ?? [];
        $attributes['provider_account_id'] = $this->nullableString($attributes['provider_account_id'] ?? null);
        $attributes['provider_plan_code'] = $this->nullableString($attributes['provider_plan_code'] ?? null);
        $attributes['provider_region'] = $this->nullableString($attributes['provider_region'] ?? null);
        $attributes['provider_provision_path'] = $this->nullableString($attributes['provider_provision_path'] ?? null);
        $attributes['provider_options'] = $this->jsonObject($attributes['provider_options'] ?? null);

        return $attributes;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return $value === '' ? null : $value;
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
