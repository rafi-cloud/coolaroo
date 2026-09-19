<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAllergenRequest;
use App\Http\Requests\Admin\UpdateAllergenRequest;
use App\Models\Allergen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AllergenController extends Controller
{
    public function index(): View
    {
        return view('admin.catalogue.allergens.index', ['allergens' => Allergen::orderBy('allergen_name')->get()]);
    }

    public function create(): View
    {
        return view('admin.catalogue.allergens.create');
    }

    public function store(StoreAllergenRequest $request): RedirectResponse
    {
        Allergen::create($request->validated());

        return redirect()->route('admin.allergens.index')->with('status', 'allergen-created');
    }

    public function edit(Allergen $allergen): View
    {
        return view('admin.catalogue.allergens.edit', ['allergen' => $allergen]);
    }

    public function update(UpdateAllergenRequest $request, Allergen $allergen): RedirectResponse
    {
        $allergen->update($request->validated());

        return redirect()->route('admin.allergens.index')->with('status', 'allergen-updated');
    }

    public function destroy(Allergen $allergen): RedirectResponse
    {
        if ($allergen->isInUse()) {
            throw ValidationException::withMessages([
                'allergen' => 'Cannot delete an allergen currently used by a menu item.',
            ]);
        }

        $allergen->delete();

        return redirect()->route('admin.allergens.index')->with('status', 'allergen-deleted');
    }
}
