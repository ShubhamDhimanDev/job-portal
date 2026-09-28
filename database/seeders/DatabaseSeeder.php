<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::factory()->create([
            'name' => 'Pradhi Admin',
            'email' => 'admin@pradhiassociates.com',
        ]);

        if (app()->environment('local')) {
            Company::factory(5)->create()->each(function (Company $company) use ($admin): void {
                JobPosting::factory(random_int(1, 3))
                    ->published()
                    ->for($company)
                    ->for($admin, 'postedBy')
                    ->create();
            });
        }
    }
}
