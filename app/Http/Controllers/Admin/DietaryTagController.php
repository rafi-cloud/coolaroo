<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDietaryTagRequest;
use App\Http\Requests\Admin\UpdateDietaryTagRequest;
use App\Models\DietaryTag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DietaryTagController extends Controller
{
    public function index(): View
    {
        return view('admin.catalogue.dietary-tags.index', ['tags' => DietaryTag::orderBy('tag_name')->get()]);
    }

    public function create(): View
    {
        return view('admin.catalogue.dietary-tags.create');
    }

    public function store(StoreDietaryTagRequest $request): RedirectResponse
    {
        DietaryTag::create($request->validated());

        return redirect()->route('admin.dietary-tags.index')->with('status', 'tag-created');
    }

    public function edit(DietaryTag $dietary_tag): View
    {
        return view('admin.catalogue.dietary-tags.edit', ['tag' => $dietary_tag]);
    }

    public function update(UpdateDietaryTagRequest $request, DietaryTag $dietary_tag): RedirectResponse
    {
        $dietary_tag->update($request->validated());

        return redirect()->route('admin.dietary-tags.index')->with('status', 'tag-updated');
    }

    public function destroy(DietaryTag $dietary_tag): RedirectResponse
    {
        if ($dietary_tag->isInUse()) {
            throw ValidationException::withMessages([
                'tag' => 'Cannot delete a dietary tag currently used by a menu item.',
            ]);
        }

        $dietary_tag->delete();

        return redirect()->route('admin.dietary-tags.index')->with('status', 'tag-deleted');
    }
}
