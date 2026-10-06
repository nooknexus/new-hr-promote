<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('code', 20)->unique();
            $table->unsignedInteger('max_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('leave_type_id')->constrained();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 5, 1);
            $table->text('reason')->nullable();
            $table->enum('step1_status', ['pending', 'step1_approved', 'approved', 'rejected'])->default('pending');
            $table->foreignId('step1_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('step1_reason')->nullable();
            $table->timestamp('step1_at')->nullable();
            $table->enum('step2_status', ['pending', 'step1_approved', 'approved', 'rejected'])->default('pending');
            $table->foreignId('step2_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('step2_reason')->nullable();
            $table->timestamp('step2_at')->nullable();
            $table->enum('status', ['pending', 'step1_approved', 'approved', 'rejected'])->default('pending');
            $table->string('external_name', 200)->nullable();
            $table->string('cid', 13)->nullable();
            $table->timestamps();

            $table->index(['cid', 'start_date']);
            $table->index(['user_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_types');
    }
};
