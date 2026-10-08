<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('candidate_imports');

        Schema::create('resume_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_posting_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_filename');
            $table->string('upload_path')->nullable();
            $table->boolean('rate_with_ai')->default(false);
            $table->string('status')->default('pending');
            $table->unsignedInteger('total')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('resume_upload_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resume_upload_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_application_id')->nullable()->constrained()->nullOnDelete();
            $table->string('filename');
            $table->string('file_path')->nullable();
            $table->string('status')->default('pending');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['resume_upload_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resume_upload_items');
        Schema::dropIfExists('resume_uploads');

        Schema::create('candidate_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->boolean('rate_with_ai')->default(true);
            $table->string('spreadsheet_path');
            $table->string('archive_path')->nullable();
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->json('issues')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }
};
