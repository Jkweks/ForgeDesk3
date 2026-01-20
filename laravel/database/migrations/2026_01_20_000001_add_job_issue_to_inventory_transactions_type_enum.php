<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Modify the enum to add 'job_issue'
        DB::statement("ALTER TABLE inventory_transactions MODIFY COLUMN type ENUM('receipt', 'shipment', 'adjustment', 'transfer', 'return', 'cycle_count', 'job_issue')");
    }

    public function down(): void
    {
        // Remove 'job_issue' from the enum
        DB::statement("ALTER TABLE inventory_transactions MODIFY COLUMN type ENUM('receipt', 'shipment', 'adjustment', 'transfer', 'return', 'cycle_count')");
    }
};
