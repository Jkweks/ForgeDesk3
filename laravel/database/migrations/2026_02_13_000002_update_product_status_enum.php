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
        // First, update any existing 'out_of_stock' records to 'critical'
        DB::table('products')
            ->where('status', 'out_of_stock')
            ->update(['status' => 'critical']);

        // For MySQL, we need to alter the enum type
        DB::statement("ALTER TABLE products MODIFY COLUMN status ENUM('in_stock', 'low_stock', 'critical') DEFAULT 'in_stock'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore the old enum values
        DB::statement("ALTER TABLE products MODIFY COLUMN status ENUM('in_stock', 'low_stock', 'critical', 'out_of_stock') DEFAULT 'in_stock'");
    }
};
