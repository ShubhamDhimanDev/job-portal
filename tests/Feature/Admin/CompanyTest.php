<?php

use App\Models\Company;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guest is redirected away from company admin routes', function () {
    $this->get('/admin/companies')->assertRedirect('/login');
    $this->get('/admin/companies/create')->assertRedirect('/login');
    $this->post('/admin/companies')->assertRedirect('/login');
});

test('authenticated admin can view the companies index', function () {
    $user = User::factory()->create();
    Company::factory()->count(3)->create();

    $response = $this->actingAs($user)->get('/admin/companies');

    $response->assertOk();
});

test('authenticated admin can create a company', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/admin/companies', [
        'name' => 'Acme Corp',
        'contact_person' => 'Jane Doe',
        'contact_email' => 'jane@acme.test',
        'contact_phone' => '555-0100',
        'website' => 'https://acme.test',
        'notes' => 'Met at a job fair.',
    ]);

    $response->assertRedirect(route('admin.companies.index'));
    $this->assertDatabaseHas('companies', ['name' => 'Acme Corp', 'contact_email' => 'jane@acme.test']);
});

test('company creation requires a name', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/admin/companies', ['name' => '']);

    $response->assertSessionHasErrors('name');
    expect(Company::query()->count())->toBe(0);
});

test('authenticated admin can edit and update a company', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create(['name' => 'Old Name']);

    $this->actingAs($user)->get(route('admin.companies.edit', $company))->assertOk();

    $response = $this->actingAs($user)->put(route('admin.companies.update', $company), [
        'name' => 'New Name',
        'contact_person' => $company->contact_person,
        'contact_email' => $company->contact_email,
        'contact_phone' => $company->contact_phone,
        'website' => $company->website,
        'notes' => $company->notes,
    ]);

    $response->assertRedirect(route('admin.companies.index'));
    expect($company->fresh()->name)->toBe('New Name');
});

test('authenticated admin can delete a company with no job postings', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();

    $response = $this->actingAs($user)->delete(route('admin.companies.destroy', $company));

    $response->assertRedirect(route('admin.companies.index'));
    expect(Company::query()->count())->toBe(0);
});

test('deleting a company with existing job postings fails gracefully instead of 500ing', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    JobPosting::factory()->for($company)->create();

    $response = $this->actingAs($user)->delete(route('admin.companies.destroy', $company));

    $response->assertSessionHasErrors('company');
    $response->assertStatus(302);
    expect(Company::query()->count())->toBe(1);
});
