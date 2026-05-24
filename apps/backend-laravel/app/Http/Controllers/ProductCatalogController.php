<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\View\View;

class ProductCatalogController extends Controller
{
    public function __invoke(): View
    {
        return view('products.index', [
            'products' => Product::active()->orderBy('type')->orderBy('price_amount')->get(),
        ]);
    }
}
