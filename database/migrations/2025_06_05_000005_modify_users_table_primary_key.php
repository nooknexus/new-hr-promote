<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, ensure we have a cid_hash column with enough width
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'cid_hash')) {
                $table->string('cid_hash', 255)->nullable()->after('id');
            } else {
                // Change column to ensure it has enough space for SHA256 hash
                $table->string('cid_hash', 255)->nullable()->change();
            }
        });
        
        // Update existing rows - generate cid_hash from thaid_pid if available
        DB::statement('UPDATE users SET cid_hash = SHA2(thaid_pid, 256) WHERE thaid_pid IS NOT NULL AND (cid_hash IS NULL OR cid_hash = "")');
        
        // Find duplicate cid_hash values and fix them by appending user id
        $duplicates = DB::select('SELECT cid_hash, COUNT(*) as count FROM users WHERE cid_hash IS NOT NULL AND cid_hash != "" GROUP BY cid_hash HAVING count > 1');
        
        foreach ($duplicates as $duplicate) {
            // Get all users with this duplicate cid_hash
            $users = DB::select('SELECT id, cid_hash FROM users WHERE cid_hash = ?', [$duplicate->cid_hash]);
            
            // Skip the first one (keep original) and update others with a unique suffix
            for ($i = 1; $i < count($users); $i++) {
                $user = $users[$i];
                $newCidHash = $duplicate->cid_hash . ':' . $user->id;
                DB::update('UPDATE users SET cid_hash = ? WHERE id = ?', [$newCidHash, $user->id]);
            }
        }
        
        // For rows without any cid_hash, generate a placeholder value that's guaranteed unique
        DB::statement('UPDATE users SET cid_hash = CONCAT("user:", id, ":", UUID()) WHERE cid_hash IS NULL OR cid_hash = ""');
        
        // Set NOT NULL constraint after all values are populated
        DB::statement('ALTER TABLE users MODIFY cid_hash VARCHAR(255) NOT NULL');
        
        // Now we can safely make the column unique
        Schema::table('users', function (Blueprint $table) {
            // Add a unique index on cid_hash
            $table->unique('cid_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['cid_hash']);
            $table->string('cid_hash', 255)->nullable()->change();
        });
    }
};
