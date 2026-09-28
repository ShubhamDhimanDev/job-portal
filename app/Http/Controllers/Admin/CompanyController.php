<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CompanyStoreRequest;
use App\Http\Requests\Admin\CompanyUpdateRequest;
use App\Models\Company;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->query('search');

        $companies = Company::query()
            ->withCount('jobPostings')
            ->when(filled($search), fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Company $company) => [
                'id' => $company->id,
                'name' => $company->name,
                'contact_person' => $company->contact_person,
                'contact_email' => $company->contact_email,
                'contact_phone' => $company->contact_phone,
                'website' => $company->website,
                'notes' => $company->notes,
                'job_postings_count' => $company->job_postings_count,
            ]);

        return Inertia::render('admin/companies/index', [
            'companies' => $companies,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/companies/create');
    }

    public function store(CompanyStoreRequest $request): RedirectResponse
    {
        Company::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Company created.']);

        return to_route('admin.companies.index');
    }

    public function edit(Company $company): Response
    {
        return Inertia::render('admin/companies/edit', [
            'company' => $company->only([
                'id', 'name', 'contact_person', 'contact_email', 'contact_phone', 'website', 'notes',
            ]),
        ]);
    }

    public function update(CompanyUpdateRequest $request, Company $company): RedirectResponse
    {
        $company->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Company updated.']);

        return to_route('admin.companies.index');
    }

    public function destroy(Company $company): RedirectResponse
    {
        try {
            $company->delete();
        } catch (QueryException) {
            return back()->withErrors([
                'company' => 'This company cannot be deleted because it still has job postings. Close or reassign those postings first.',
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Company deleted.']);

        return to_route('admin.companies.index');
    }
}
