<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->enum('status', ['pending', 'step1_approved', 'approved', 'rejected', 'cancelled'])
                ->default('pending')
                ->change();
        });
    }

    public function down(): void
    {
        DB::table('leave_requests')->where('status', 'cancelled')->update(['status' => 'rejected']);

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->enum('status', ['pending', 'step1_approved', 'approved', 'rejected'])
                ->default('pending')
                ->change();
        });
    }
};
