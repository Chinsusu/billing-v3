<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        return view('admin.products.index', [
            'products' => Product::orderBy('created_at', 'desc')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.products.create', ['product' => new Product]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        Product::create($request->validated() + ['config' => []]);

        return redirect('/admin/products')->with('status', 'Product created.');
    }

    public function edit(Product $product): View
    {
        return view('admin.products.edit', ['product' => $product]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated() + ['config' => $product->config ?? []]);

        return redirect('/admin/products')->with('status', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        abort_unless(request()->user()?->can('products.delete'), 403);

        $product->update(['status' => 'archived']);

        return redirect('/admin/products')->with('status', 'Product archived.');
    }
}
