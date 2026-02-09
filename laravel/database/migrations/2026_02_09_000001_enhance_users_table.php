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
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('role')->default('viewer')->after('password')->comment('admin, manager, fabricator, viewer');
            $table->boolean('is_active')->default(true)->after('role');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            $table->softDeletes();
        });

        // Update existing users to split name into first_name/last_name
        DB::statement("
            UPDATE users
            SET
                first_name = SUBSTRING_INDEX(name, ' ', 1),
                last_name = SUBSTRING_INDEX(name, ' ', -1)
            WHERE first_name IS NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name', 'role', 'is_active', 'last_login_at', 'deleted_at']);
        });
    }
};
