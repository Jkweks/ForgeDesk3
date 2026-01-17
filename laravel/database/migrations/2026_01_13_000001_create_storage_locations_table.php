<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This creates a hierarchical storage locations table inspired by ForgeDesk2.
     * Locations are organized as: Aisle → Rack → Shelf → Bin
     */
    public function up(): void
    {
        Schema::create('storage_locations', function (Blueprint $table) {
            $table->id();

            // Hierarchical structure
            $table->foreignId('parent_id')->nullable()->constrained('storage_locations')->onDelete('cascade');

            // Location name (e.g., "Aisle 1", "Rack A", "Shelf 3", "Bin 12")
            $table->string('name');

            // Location components (parsed from name or entered directly)
            $table->string('aisle')->nullable();
            $table->string('rack')->nullable();
            $table->string('shelf')->nullable();
            $table->string('bin')->nullable();

            // Slug/path for hierarchical identification (e.g., "1.2.3.4")
            $table->string('slug')->nullable()->unique();

            // Additional metadata
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index('parent_id');
            $table->index('aisle');
            $table->index('rack');
            $table->index('shelf');
            $table->index('bin');
            $table->index('slug');
            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('storage_locations');
    }
};
