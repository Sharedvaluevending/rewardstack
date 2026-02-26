<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $products = Product::orderBy('sort_order')->get();

        return Inertia::render('Admin/Products/Index', [
            'products' => $products,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product)
    {
        return Inertia::render('Admin/Products/Edit', [
            'product' => $product,
            'previewConfig' => $product->getPreviewConfigWithDefaults(),
            'printConfig' => $product->getPrintConfigWithDefaults(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'base_price' => 'required|numeric|min:0',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'preview_template' => 'nullable|string',
            'preview_config' => 'nullable|array',
            'print_config' => 'nullable|array',
        ]);

        $product->update($validated);

        return back()->with('success', 'Product updated successfully');
    }
}
