<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\StorageLocation;
use App\Models\InventoryLocation;
use App\Models\JobReservation;
use App\Models\PurchaseOrder;
use App\Models\InventoryTransaction;
use App\Models\Machine;
use App\Models\MaintenanceTask;
use App\Models\MaintenanceRecord;
use App\Models\CycleCountSession;

class ComprehensiveSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🔥 Purging all existing data...');
        $this->purgeAllData();

        $this->command->info('👤 Creating users...');
        $users = $this->createUsers();

        $this->command->info('📦 Creating categories...');
        $categories = $this->createCategories();

        $this->command->info('🏢 Creating suppliers...');
        $suppliers = $this->createSuppliers();

        $this->command->info('📍 Creating hierarchical storage locations...');
        $locations = $this->createStorageLocations();

        $this->command->info('🔧 Creating products...');
        $products = $this->createProducts($categories, $suppliers);

        $this->command->info('📊 Linking inventory to locations...');
        $this->createInventoryLocations($products, $locations);

        $this->command->info('🏭 Creating machines...');
        $machines = $this->createMachines();

        $this->command->info('🔨 Creating maintenance tasks and records...');
        $this->createMaintenanceTasks($machines);
        $this->createMaintenanceRecords($machines);

        $this->command->info('📋 Creating job reservations...');
        $this->createJobReservations($products);

        $this->command->info('🛒 Creating purchase orders...');
        $this->createPurchaseOrders($suppliers, $products);

        $this->command->info('📝 Creating inventory transactions...');
        $this->createInventoryTransactions($products);

        $this->command->info('🔢 Creating cycle count sessions...');
        $this->createCycleCountSessions($products, $locations);

        $this->command->info('✅ Seeding completed successfully!');
        $this->displaySummary();
    }

    /**
     * Purge all existing data from the database
     */
    private function purgeAllData(): void
    {
        // Disable foreign key checks (PostgreSQL compatible)
        DB::statement('SET session_replication_role = replica');

        try {
            // Tables to truncate (in order to avoid FK issues)
            $tablesToTruncate = [
                'maintenance_records',
                'maintenance_tasks',
                'machines',
                'cycle_count_items',  // Fixed: was cycle_count_lines
                'cycle_count_sessions',
                'inventory_transaction_lines',
                'inventory_transactions',
                'purchase_order_lines',
                'purchase_orders',
                'job_reservation_items',
                'job_reservations',
                'inventory_locations',
                'storage_locations',
                'required_parts',
                'products',
                'categories',
                'suppliers',
            ];

            foreach ($tablesToTruncate as $table) {
                if ($this->tableExists($table)) {
                    try {
                        DB::table($table)->truncate();
                    } catch (\Exception $e) {
                        $this->command->warn("  ⚠ Could not truncate {$table}: " . $e->getMessage());
                    }
                }
            }

            // Users (keep admin) - use delete instead of truncate
            if ($this->tableExists('users')) {
                DB::table('users')->whereNotIn('email', ['admin@forgedesk.com'])->delete();
            }
        } finally {
            // Re-enable foreign key checks
            DB::statement('SET session_replication_role = DEFAULT');
        }

        $this->command->warn('  ✓ All data purged');
    }

    /**
     * Check if a table exists in the database
     */
    private function tableExists(string $table): bool
    {
        return DB::getSchemaBuilder()->hasTable($table);
    }

    /**
     * Create users
     */
    private function createUsers(): array
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@forgedesk.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
            ]
        );

        $users = [
            $admin,
            User::create([
                'name' => 'John Smith',
                'email' => 'john@forgedesk.com',
                'password' => Hash::make('password'),
            ]),
            User::create([
                'name' => 'Sarah Johnson',
                'email' => 'sarah@forgedesk.com',
                'password' => Hash::make('password'),
            ]),
        ];

        $this->command->info('  ✓ Created ' . count($users) . ' users');
        return $users;
    }

    /**
     * Create categories
     */
    private function createCategories(): array
    {
        $categories = [
            Category::create([
                'name' => 'Raw Materials',
                'description' => 'Aluminum extrusions, glass, and raw materials',
                'sort_order' => 1,
            ]),
            Category::create([
                'name' => 'Hardware',
                'description' => 'Hinges, locks, closers, panic devices',
                'sort_order' => 2,
            ]),
            Category::create([
                'name' => 'Finishing',
                'description' => 'Powder coating, anodizing materials',
                'sort_order' => 3,
            ]),
            Category::create([
                'name' => 'Electrical',
                'description' => 'Wiring, sensors, access control',
                'sort_order' => 4,
            ]),
            Category::create([
                'name' => 'Assembly Components',
                'description' => 'Fasteners, gaskets, weatherstripping',
                'sort_order' => 5,
            ]),
        ];

        $this->command->info('  ✓ Created ' . count($categories) . ' categories');
        return $categories;
    }

    /**
     * Create suppliers
     */
    private function createSuppliers(): array
    {
        $suppliers = [
            Supplier::create([
                'name' => 'Tubelite Inc.',
                'contact_name' => 'Mike Anderson',
                'contact_email' => 'mike@tubelite.com',
                'contact_phone' => '(555) 123-4567',
                'address' => '123 Industrial Pkwy',
                'city' => 'Detroit',
                'state' => 'MI',
                'postal_code' => '48201',
                'country' => 'USA',
                'lead_time_days' => 14,
                'is_active' => true,
            ]),
            Supplier::create([
                'name' => 'Kawneer Company',
                'contact_name' => 'Lisa Chen',
                'contact_email' => 'lisa@kawneer.com',
                'contact_phone' => '(555) 234-5678',
                'address' => '456 Manufacturing Rd',
                'city' => 'Atlanta',
                'state' => 'GA',
                'postal_code' => '30303',
                'country' => 'USA',
                'lead_time_days' => 21,
                'is_active' => true,
            ]),
            Supplier::create([
                'name' => 'YKK AP America',
                'contact_name' => 'David Park',
                'contact_email' => 'david@ykkap.com',
                'contact_phone' => '(555) 345-6789',
                'address' => '789 Commerce Blvd',
                'city' => 'Marietta',
                'state' => 'GA',
                'postal_code' => '30060',
                'country' => 'USA',
                'lead_time_days' => 28,
                'is_active' => true,
            ]),
            Supplier::create([
                'name' => 'Precision Hardware Inc.',
                'contact_name' => 'Robert Williams',
                'contact_email' => 'rob@precisionhw.com',
                'contact_phone' => '(555) 456-7890',
                'address' => '321 Hardware Way',
                'city' => 'Chicago',
                'state' => 'IL',
                'postal_code' => '60601',
                'country' => 'USA',
                'lead_time_days' => 7,
                'is_active' => true,
            ]),
            Supplier::create([
                'name' => 'Acme Glass Supply',
                'contact_name' => 'Jennifer Martinez',
                'contact_email' => 'jen@acmeglass.com',
                'contact_phone' => '(555) 567-8901',
                'address' => '654 Glass Ave',
                'city' => 'Phoenix',
                'state' => 'AZ',
                'postal_code' => '85001',
                'country' => 'USA',
                'lead_time_days' => 10,
                'is_active' => true,
            ]),
        ];

        $this->command->info('  ✓ Created ' . count($suppliers) . ' suppliers');
        return $suppliers;
    }

    /**
     * Create hierarchical storage locations
     */
    private function createStorageLocations(): array
    {
        $locations = [];

        // Aisle 1 - Raw Materials
        $aisle1 = StorageLocation::create([
            'name' => 'Aisle 1',
            'aisle' => '1',
            'description' => 'Raw materials and extrusions',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $locations[] = $aisle1;

        // Aisle 1 racks
        $a1r1 = StorageLocation::create([
            'parent_id' => $aisle1->id,
            'name' => 'Rack A',
            'aisle' => '1',
            'rack' => 'A',
            'description' => 'Aluminum extrusions',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $locations[] = $a1r1;

        // Aisle 1, Rack A shelves
        $shelves = ['1', '2', '3', '4', '5'];
        foreach ($shelves as $shelfNum) {
            $shelf = StorageLocation::create([
                'parent_id' => $a1r1->id,
                'name' => "Shelf {$shelfNum}",
                'aisle' => '1',
                'rack' => 'A',
                'shelf' => $shelfNum,
                'is_active' => true,
                'sort_order' => (int)$shelfNum,
            ]);
            $locations[] = $shelf;

            // Add some bins to selected shelves
            if (in_array($shelfNum, ['1', '3', '5'])) {
                for ($binNum = 1; $binNum <= 8; $binNum++) {
                    $bin = StorageLocation::create([
                        'parent_id' => $shelf->id,
                        'name' => "Bin {$binNum}",
                        'aisle' => '1',
                        'rack' => 'A',
                        'shelf' => $shelfNum,
                        'bin' => (string)$binNum,
                        'is_active' => true,
                        'sort_order' => $binNum,
                    ]);
                    $locations[] = $bin;
                }
            }
        }

        // Aisle 2 - Hardware
        $aisle2 = StorageLocation::create([
            'name' => 'Aisle 2',
            'aisle' => '2',
            'description' => 'Hardware and fasteners',
            'is_active' => true,
            'sort_order' => 2,
        ]);
        $locations[] = $aisle2;

        $a2r1 = StorageLocation::create([
            'parent_id' => $aisle2->id,
            'name' => 'Rack A',
            'aisle' => '2',
            'rack' => 'A',
            'description' => 'Hinges and locks',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $locations[] = $a2r1;

        $a2r2 = StorageLocation::create([
            'parent_id' => $aisle2->id,
            'name' => 'Rack B',
            'aisle' => '2',
            'rack' => 'B',
            'description' => 'Door closers and panic devices',
            'is_active' => true,
            'sort_order' => 2,
        ]);
        $locations[] = $a2r2;

        // Aisle 3 - Finishing
        $aisle3 = StorageLocation::create([
            'name' => 'Aisle 3',
            'aisle' => '3',
            'description' => 'Finishing materials',
            'is_active' => true,
            'sort_order' => 3,
        ]);
        $locations[] = $aisle3;

        $a3r1 = StorageLocation::create([
            'parent_id' => $aisle3->id,
            'name' => 'Rack A',
            'aisle' => '3',
            'rack' => 'A',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $locations[] = $a3r1;

        // Aisle 4 - Electrical
        $aisle4 = StorageLocation::create([
            'name' => 'Aisle 4',
            'aisle' => '4',
            'description' => 'Electrical components',
            'is_active' => true,
            'sort_order' => 4,
        ]);
        $locations[] = $aisle4;

        $a4r1 = StorageLocation::create([
            'parent_id' => $aisle4->id,
            'name' => 'Rack A',
            'aisle' => '4',
            'rack' => 'A',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $locations[] = $a4r1;

        // Receiving Area
        $receiving = StorageLocation::create([
            'name' => 'Receiving Area',
            'aisle' => 'RCV',
            'description' => 'Temporary receiving and inspection',
            'is_active' => true,
            'sort_order' => 10,
        ]);
        $locations[] = $receiving;

        // Returns Area
        $returns = StorageLocation::create([
            'name' => 'Returns Area',
            'aisle' => 'RET',
            'description' => 'Return and RMA staging',
            'is_active' => true,
            'sort_order' => 11,
        ]);
        $locations[] = $returns;

        $this->command->info('  ✓ Created ' . count($locations) . ' storage locations');
        return $locations;
    }

    /**
     * Create products
     */
    private function createProducts($categories, $suppliers): array
    {
        $products = [];

        // Raw Materials
        $rawMaterialCategory = $categories[0];
        $tubelite = $suppliers[0];

        $products[] = Product::create([
            'name' => 'T400 Top Rail - BL',
            'sku' => 'T400-TR-BL',
            'part_number' => 'T400-TR',
            'finish' => 'BL',
            'category_id' => $rawMaterialCategory->id,
            'supplier_id' => $tubelite->id,
            'description' => 'Tubelite 400 Series Top Rail, Black finish',
            'quantity_on_hand' => 150,
            'quantity_committed' => 20,
            'quantity_on_order' => 50,
            'unit_price' => 45.50,
            'reorder_point' => 30,
            'reorder_quantity' => 100,
            'safety_stock' => 20,
            'lead_time_days' => 14,
            'unit_of_measure' => 'EA',
            'status' => 'in_stock',
            'is_active' => true,
        ]);

        $products[] = Product::create([
            'name' => 'T400 Bottom Rail - BL',
            'sku' => 'T400-BR-BL',
            'part_number' => 'T400-BR',
            'finish' => 'BL',
            'category_id' => $rawMaterialCategory->id,
            'supplier_id' => $tubelite->id,
            'description' => 'Tubelite 400 Series Bottom Rail, Black finish',
            'quantity_on_hand' => 135,
            'quantity_committed' => 18,
            'quantity_on_order' => 0,
            'unit_price' => 42.00,
            'reorder_point' => 30,
            'reorder_quantity' => 100,
            'safety_stock' => 20,
            'lead_time_days' => 14,
            'unit_of_measure' => 'EA',
            'status' => 'in_stock',
            'is_active' => true,
        ]);

        $products[] = Product::create([
            'name' => 'T400 Stile - C2',
            'sku' => 'T400-ST-C2',
            'part_number' => 'T400-ST',
            'finish' => 'C2',
            'category_id' => $rawMaterialCategory->id,
            'supplier_id' => $tubelite->id,
            'description' => 'Tubelite 400 Series Stile, Clear anodized',
            'quantity_on_hand' => 85,
            'quantity_committed' => 30,
            'quantity_on_order' => 100,
            'unit_price' => 52.75,
            'reorder_point' => 40,
            'reorder_quantity' => 120,
            'safety_stock' => 25,
            'lead_time_days' => 14,
            'unit_of_measure' => 'EA',
            'status' => 'in_stock',
            'is_active' => true,
        ]);

        // Hardware
        $hardwareCategory = $categories[1];
        $hardwareSupplier = $suppliers[3];

        $products[] = Product::create([
            'name' => 'Continuous Hinge - 83" - Dark Bronze',
            'sku' => 'HINGE-CONT-83-DB',
            'part_number' => 'HINGE-CONT-83',
            'finish' => 'DB',
            'category_id' => $hardwareCategory->id,
            'supplier_id' => $hardwareSupplier->id,
            'description' => '83 inch continuous hinge, dark bronze finish',
            'quantity_on_hand' => 42,
            'quantity_committed' => 12,
            'quantity_on_order' => 25,
            'unit_price' => 89.50,
            'reorder_point' => 15,
            'reorder_quantity' => 50,
            'safety_stock' => 10,
            'lead_time_days' => 7,
            'unit_of_measure' => 'EA',
            'status' => 'in_stock',
            'is_active' => true,
        ]);

        $products[] = Product::create([
            'name' => 'Mortise Lock - Grade 1 - Satin Chrome',
            'sku' => 'LOCK-MORT-G1-SC',
            'part_number' => 'LOCK-MORT-G1',
            'finish' => 'SC',
            'category_id' => $hardwareCategory->id,
            'supplier_id' => $hardwareSupplier->id,
            'description' => 'Grade 1 mortise lockset, satin chrome',
            'quantity_on_hand' => 28,
            'quantity_committed' => 8,
            'quantity_on_order' => 0,
            'unit_price' => 245.00,
            'reorder_point' => 12,
            'reorder_quantity' => 30,
            'safety_stock' => 8,
            'lead_time_days' => 7,
            'unit_of_measure' => 'EA',
            'status' => 'in_stock',
            'is_active' => true,
        ]);

        $products[] = Product::create([
            'name' => 'Door Closer - Heavy Duty - Aluminum',
            'sku' => 'CLOSER-HD-AL',
            'part_number' => 'CLOSER-HD',
            'finish' => 'AL',
            'category_id' => $hardwareCategory->id,
            'supplier_id' => $hardwareSupplier->id,
            'description' => 'Heavy duty door closer, aluminum finish',
            'quantity_on_hand' => 15,
            'quantity_committed' => 6,
            'quantity_on_order' => 20,
            'unit_price' => 178.50,
            'reorder_point' => 10,
            'reorder_quantity' => 25,
            'safety_stock' => 5,
            'lead_time_days' => 7,
            'unit_of_measure' => 'EA',
            'status' => 'low_stock',
            'is_active' => true,
        ]);

        $products[] = Product::create([
            'name' => 'Panic Device - Rim Type - Dark Bronze',
            'sku' => 'PANIC-RIM-DB',
            'part_number' => 'PANIC-RIM',
            'finish' => 'DB',
            'category_id' => $hardwareCategory->id,
            'supplier_id' => $hardwareSupplier->id,
            'description' => 'Rim type panic exit device, dark bronze',
            'quantity_on_hand' => 8,
            'quantity_committed' => 4,
            'quantity_on_order' => 15,
            'unit_price' => 425.00,
            'reorder_point' => 8,
            'reorder_quantity' => 20,
            'safety_stock' => 4,
            'lead_time_days' => 7,
            'unit_of_measure' => 'EA',
            'status' => 'critical',
            'is_active' => true,
        ]);

        // Glass
        $glassSupplier = $suppliers[4];

        $products[] = Product::create([
            'name' => 'Tempered Glass - 1/4" - Clear',
            'sku' => 'GLASS-TEMP-025-CLR',
            'part_number' => 'GLASS-TEMP-025',
            'finish' => 'CLR',
            'category_id' => $rawMaterialCategory->id,
            'supplier_id' => $glassSupplier->id,
            'description' => '1/4 inch tempered glass, clear',
            'quantity_on_hand' => 65,
            'quantity_committed' => 15,
            'quantity_on_order' => 30,
            'unit_price' => 125.00,
            'reorder_point' => 20,
            'reorder_quantity' => 50,
            'safety_stock' => 12,
            'lead_time_days' => 10,
            'unit_of_measure' => 'SF',
            'status' => 'in_stock',
            'is_active' => true,
        ]);

        $products[] = Product::create([
            'name' => 'Tempered Glass - 1/2" - Low-E',
            'sku' => 'GLASS-TEMP-050-LOWE',
            'part_number' => 'GLASS-TEMP-050',
            'finish' => 'LOWE',
            'category_id' => $rawMaterialCategory->id,
            'supplier_id' => $glassSupplier->id,
            'description' => '1/2 inch tempered glass with Low-E coating',
            'quantity_on_hand' => 42,
            'quantity_committed' => 12,
            'quantity_on_order' => 40,
            'unit_price' => 185.00,
            'reorder_point' => 18,
            'reorder_quantity' => 45,
            'safety_stock' => 10,
            'lead_time_days' => 10,
            'unit_of_measure' => 'SF',
            'status' => 'in_stock',
            'is_active' => true,
        ]);

        // Finishing Materials
        $finishingCategory = $categories[2];
        $kawneer = $suppliers[1];

        $products[] = Product::create([
            'name' => 'Powder Coating - Black - 50lb',
            'sku' => 'POWDER-BL-50',
            'part_number' => 'POWDER-BL',
            'finish' => null,
            'category_id' => $finishingCategory->id,
            'supplier_id' => $kawneer->id,
            'description' => 'Black powder coating material, 50lb bag',
            'quantity_on_hand' => 125,
            'quantity_committed' => 0,
            'quantity_on_order' => 0,
            'unit_price' => 95.00,
            'reorder_point' => 30,
            'reorder_quantity' => 80,
            'safety_stock' => 20,
            'lead_time_days' => 21,
            'unit_of_measure' => 'BAG',
            'status' => 'in_stock',
            'is_active' => true,
        ]);

        // Electrical
        $electricalCategory = $categories[3];

        $products[] = Product::create([
            'name' => 'Card Reader - Proximity',
            'sku' => 'CARD-PROX-001',
            'part_number' => 'CARD-PROX',
            'finish' => null,
            'category_id' => $electricalCategory->id,
            'supplier_id' => $suppliers[2]->id,
            'description' => 'Proximity card reader for access control',
            'quantity_on_hand' => 18,
            'quantity_committed' => 3,
            'quantity_on_order' => 12,
            'unit_price' => 145.00,
            'reorder_point' => 8,
            'reorder_quantity' => 15,
            'safety_stock' => 5,
            'lead_time_days' => 28,
            'unit_of_measure' => 'EA',
            'status' => 'in_stock',
            'is_active' => true,
        ]);

        // Assembly Components
        $assemblyCategory = $categories[4];

        $products[] = Product::create([
            'name' => 'Stainless Steel Screws #10 - 1" - 100 Pack',
            'sku' => 'SCREW-SS-10-1-100',
            'part_number' => 'SCREW-SS-10-1',
            'finish' => null,
            'category_id' => $assemblyCategory->id,
            'supplier_id' => $hardwareSupplier->id,
            'description' => '#10 x 1" stainless steel screws, box of 100',
            'quantity_on_hand' => 245,
            'quantity_committed' => 30,
            'quantity_on_order' => 0,
            'unit_price' => 12.50,
            'reorder_point' => 50,
            'reorder_quantity' => 200,
            'safety_stock' => 30,
            'lead_time_days' => 7,
            'unit_of_measure' => 'BOX',
            'status' => 'in_stock',
            'is_active' => true,
        ]);

        $products[] = Product::create([
            'name' => 'EPDM Gasket - 1/4" x 100ft Roll',
            'sku' => 'GASKET-EPDM-025-100',
            'part_number' => 'GASKET-EPDM-025',
            'finish' => null,
            'category_id' => $assemblyCategory->id,
            'supplier_id' => $tubelite->id,
            'description' => '1/4" EPDM gasket material, 100ft roll',
            'quantity_on_hand' => 32,
            'quantity_committed' => 8,
            'quantity_on_order' => 20,
            'unit_price' => 78.00,
            'reorder_point' => 12,
            'reorder_quantity' => 25,
            'safety_stock' => 8,
            'lead_time_days' => 14,
            'unit_of_measure' => 'ROLL',
            'status' => 'in_stock',
            'is_active' => true,
        ]);

        $products[] = Product::create([
            'name' => 'Weatherstripping - Silicone - 50ft Roll',
            'sku' => 'WEATHER-SIL-50',
            'part_number' => 'WEATHER-SIL',
            'finish' => null,
            'category_id' => $assemblyCategory->id,
            'supplier_id' => $kawneer->id,
            'description' => 'Silicone weatherstripping, 50ft roll',
            'quantity_on_hand' => 28,
            'quantity_committed' => 5,
            'quantity_on_order' => 0,
            'unit_price' => 45.00,
            'reorder_point' => 10,
            'reorder_quantity' => 20,
            'safety_stock' => 6,
            'lead_time_days' => 21,
            'unit_of_measure' => 'ROLL',
            'status' => 'in_stock',
            'is_active' => true,
        ]);

        $this->command->info('  ✓ Created ' . count($products) . ' products');
        return $products;
    }

    /**
     * Create inventory locations (link products to storage locations)
     */
    private function createInventoryLocations($products, $locations): void
    {
        $count = 0;

        // Get leaf locations (bins or lowest level without children)
        $leafLocations = collect($locations)->filter(function ($location) {
            return !StorageLocation::where('parent_id', $location->id)->exists();
        });

        foreach ($products as $index => $product) {
            // Assign each product to 1-3 locations
            $numLocations = rand(1, 3);
            $selectedLocations = $leafLocations->random(min($numLocations, $leafLocations->count()));

            $totalQty = $product->quantity_on_hand;
            $locations = $selectedLocations->values();

            foreach ($locations as $locIndex => $location) {
                $isPrimary = ($locIndex === 0);

                // Distribute quantity
                if ($locIndex === $locations->count() - 1) {
                    // Last location gets remaining quantity
                    $qty = $totalQty;
                } else {
                    // Distribute proportionally
                    $qty = (int) ($totalQty / ($locations->count() - $locIndex));
                    $totalQty -= $qty;
                }

                if ($qty > 0) {
                    InventoryLocation::create([
                        'product_id' => $product->id,
                        'storage_location_id' => $location->id,
                        'location' => $location->name, // Keep for backward compatibility
                        'quantity' => $qty,
                        'quantity_committed' => $isPrimary ? $product->quantity_committed : 0,
                        'is_primary' => $isPrimary,
                        'notes' => null,
                    ]);

                    $count++;
                }
            }
        }

        $this->command->info("  ✓ Created {$count} inventory location assignments");
    }

    /**
     * Create machines
     */
    private function createMachines(): array
    {
        $machines = [
            Machine::create([
                'name' => 'CNC Router #1',
                'equipment_type' => 'CNC Router',
                'manufacturer' => 'Multicam',
                'model' => 'Series 3000',
                'serial_number' => 'MC3000-2019-001',
                'location' => 'Production Floor - West',
                'status' => 'operational',
                'purchase_date' => '2019-03-15',
                'notes' => 'Primary CNC router for aluminum cutting',
            ]),
            Machine::create([
                'name' => 'Powder Coating Booth',
                'equipment_type' => 'Finishing',
                'manufacturer' => 'Nordson',
                'model' => 'Encore HD',
                'serial_number' => 'NOR-HD-2020-045',
                'location' => 'Finishing Department',
                'status' => 'operational',
                'purchase_date' => '2020-07-22',
                'notes' => 'Main powder coating booth',
            ]),
            Machine::create([
                'name' => 'Hydraulic Press #2',
                'equipment_type' => 'Press',
                'manufacturer' => 'Dake',
                'model' => 'Force 100',
                'serial_number' => 'DK-F100-2018-033',
                'location' => 'Assembly Area - South',
                'status' => 'operational',
                'purchase_date' => '2018-11-10',
                'notes' => '100-ton hydraulic press',
            ]),
            Machine::create([
                'name' => 'Glass Cutting Table',
                'equipment_type' => 'Cutting',
                'manufacturer' => 'Bottero',
                'model' => 'Smart Cut',
                'serial_number' => 'BOT-SC-2021-012',
                'location' => 'Glass Shop',
                'status' => 'operational',
                'purchase_date' => '2021-05-18',
                'notes' => 'Automated glass cutting system',
            ]),
            Machine::create([
                'name' => 'Fork Lift #3',
                'equipment_type' => 'Material Handling',
                'manufacturer' => 'Toyota',
                'model' => '8FGU25',
                'serial_number' => 'TOY-8FGU-2017-088',
                'location' => 'Warehouse',
                'status' => 'maintenance',
                'purchase_date' => '2017-02-28',
                'notes' => '5000lb capacity forklift - currently in maintenance',
            ]),
        ];

        $this->command->info('  ✓ Created ' . count($machines) . ' machines');
        return $machines;
    }

    /**
     * Create maintenance tasks
     */
    private function createMaintenanceTasks($machines): void
    {
        $count = 0;

        foreach ($machines as $machine) {
            $tasks = [
                MaintenanceTask::create([
                    'machine_id' => $machine->id,
                    'title' => 'Daily Safety Inspection',
                    'description' => 'Check safety guards, emergency stops, and warning labels',
                    'frequency' => 'daily',
                    'estimated_duration' => 15,
                    'assigned_to' => 'Production Team',
                    'priority' => 'high',
                ]),
                MaintenanceTask::create([
                    'machine_id' => $machine->id,
                    'title' => 'Weekly Lubrication',
                    'description' => 'Lubricate all moving parts and check oil levels',
                    'frequency' => 'weekly',
                    'estimated_duration' => 30,
                    'assigned_to' => 'Maintenance Team',
                    'priority' => 'medium',
                ]),
            ];

            if (in_array($machine->equipment_type, ['CNC Router', 'Cutting'])) {
                MaintenanceTask::create([
                    'machine_id' => $machine->id,
                    'title' => 'Blade/Bit Inspection',
                    'description' => 'Inspect and replace cutting tools as needed',
                    'frequency' => 'weekly',
                    'estimated_duration' => 45,
                    'assigned_to' => 'Maintenance Team',
                    'priority' => 'high',
                ]);
                $count++;
            }

            $count += count($tasks);
        }

        $this->command->info("  ✓ Created {$count} maintenance tasks");
    }

    /**
     * Create maintenance records
     */
    private function createMaintenanceRecords($machines): void
    {
        $count = 0;

        foreach ($machines as $machine) {
            // Create 3-5 historical records per machine
            $numRecords = rand(3, 5);

            for ($i = 0; $i < $numRecords; $i++) {
                $daysAgo = rand(1, 90);

                MaintenanceRecord::create([
                    'machine_id' => $machine->id,
                    'title' => $this->getRandomMaintenanceTitle(),
                    'description' => 'Routine maintenance performed',
                    'performed_by' => $this->getRandomTechnician(),
                    'performed_at' => now()->subDays($daysAgo),
                    'downtime_minutes' => rand(0, 120),
                    'status' => 'completed',
                    'priority' => ['low', 'medium', 'high'][rand(0, 2)],
                    'cost' => rand(50, 500),
                ]);

                $count++;
            }
        }

        $this->command->info("  ✓ Created {$count} maintenance records");
    }

    /**
     * Create job reservations
     */
    private function createJobReservations($products): void
    {
        $jobReservations = [
            JobReservation::create([
                'job_number' => 'JOB-2026-001',
                'job_name' => 'Downtown Office Building - Phase 1',
                'customer_name' => 'ABC Construction',
                'requested_by' => 'Mike Johnson',
                'needed_by' => now()->addDays(14),
                'status' => 'committed',
                'notes' => 'Priority project - storefront doors and frames',
            ]),
            JobReservation::create([
                'job_number' => 'JOB-2026-002',
                'job_name' => 'Hospital Expansion - East Wing',
                'customer_name' => 'HealthCare Builders',
                'requested_by' => 'Sarah Williams',
                'needed_by' => now()->addDays(30),
                'status' => 'committed',
                'notes' => 'High-security entrance doors',
            ]),
            JobReservation::create([
                'job_number' => 'JOB-2026-003',
                'job_name' => 'Retail Plaza - Store Fronts',
                'customer_name' => 'Retail Developers Inc',
                'requested_by' => 'Tom Anderson',
                'needed_by' => now()->addDays(45),
                'status' => 'draft',
                'notes' => 'Multiple storefront assemblies',
            ]),
        ];

        // Add items to reservations
        $itemCount = 0;
        foreach ($jobReservations as $index => $reservation) {
            // Each reservation gets 3-6 random products
            $selectedProducts = collect($products)->random(rand(3, 6));

            foreach ($selectedProducts as $product) {
                $requestedQty = rand(2, 10);
                $committedQty = $reservation->status === 'committed' ? $requestedQty : 0;

                DB::table('job_reservation_items')->insert([
                    'job_reservation_id' => $reservation->id,
                    'product_id' => $product->id,
                    'requested_quantity' => $requestedQty,
                    'committed_quantity' => $committedQty,
                    'consumed_quantity' => 0,
                    'notes' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $itemCount++;
            }
        }

        $this->command->info('  ✓ Created ' . count($jobReservations) . " job reservations with {$itemCount} line items");
    }

    /**
     * Create purchase orders
     */
    private function createPurchaseOrders($suppliers, $products): void
    {
        $statuses = ['draft', 'submitted', 'approved', 'received'];

        $pos = [];
        foreach ($suppliers as $index => $supplier) {
            $status = $statuses[$index % count($statuses)];

            $po = PurchaseOrder::create([
                'po_number' => 'PO-2026-' . str_pad($index + 1, 4, '0', STR_PAD_LEFT),
                'supplier_id' => $supplier->id,
                'order_date' => now()->subDays(rand(5, 30)),
                'expected_delivery_date' => now()->addDays(rand(7, 45)),
                'status' => $status,
                'notes' => 'Standard order',
            ]);

            // Add 2-5 line items
            $lineCount = rand(2, 5);
            $supplierProducts = collect($products)->where('supplier_id', $supplier->id);

            if ($supplierProducts->count() > 0) {
                $selectedProducts = $supplierProducts->random(min($lineCount, $supplierProducts->count()));

                foreach ($selectedProducts as $product) {
                    $qtyOrdered = rand(10, 100);
                    $qtyReceived = $status === 'received' ? $qtyOrdered : 0;

                    DB::table('purchase_order_lines')->insert([
                        'purchase_order_id' => $po->id,
                        'product_id' => $product->id,
                        'quantity_ordered' => $qtyOrdered,
                        'quantity_received' => $qtyReceived,
                        'unit_price' => $product->unit_price,
                        'notes' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $pos[] = $po;
        }

        $this->command->info('  ✓ Created ' . count($pos) . ' purchase orders');
    }

    /**
     * Create inventory transactions
     */
    private function createInventoryTransactions($products): void
    {
        $types = ['receipt', 'adjustment', 'consumption', 'cycle_count', 'transfer', 'return'];
        $count = 0;

        // Create 20-30 transactions
        $numTransactions = rand(20, 30);

        for ($i = 0; $i < $numTransactions; $i++) {
            $type = $types[array_rand($types)];
            $daysAgo = rand(1, 60);

            $transaction = InventoryTransaction::create([
                'transaction_type' => $type,
                'reference_number' => 'TXN-' . now()->format('Y') . '-' . str_pad($i + 1, 5, '0', STR_PAD_LEFT),
                'transaction_date' => now()->subDays($daysAgo),
                'notes' => $this->getTransactionNote($type),
            ]);

            // Add 1-3 line items
            $lineCount = rand(1, 3);
            $selectedProducts = collect($products)->random($lineCount);

            foreach ($selectedProducts as $product) {
                $quantity = rand(1, 20);
                if (in_array($type, ['consumption', 'transfer'])) {
                    $quantity = -$quantity; // Negative for outbound
                }

                DB::table('inventory_transaction_lines')->insert([
                    'transaction_id' => $transaction->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_cost' => $product->unit_price,
                    'notes' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $count++;
        }

        $this->command->info("  ✓ Created {$count} inventory transactions");
    }

    /**
     * Create cycle count sessions
     */
    private function createCycleCountSessions($products, $locations): void
    {
        $sessions = [
            CycleCountSession::create([
                'session_number' => 'CC-2026-001',
                'location' => null,
                'category_id' => null,
                'status' => 'completed',
                'scheduled_date' => now()->subDays(15),
                'started_at' => now()->subDays(15),
                'completed_at' => now()->subDays(14),
                'assigned_to' => null,
                'reviewed_by' => null,
                'notes' => 'Full inventory count for month end',
            ]),
            CycleCountSession::create([
                'session_number' => 'CC-2026-002',
                'location' => 'Aisle 2',
                'category_id' => null,
                'status' => 'in_progress',
                'scheduled_date' => now(),
                'started_at' => now(),
                'completed_at' => null,
                'assigned_to' => null,
                'reviewed_by' => null,
                'notes' => 'Hardware section verification',
            ]),
            CycleCountSession::create([
                'session_number' => 'CC-2026-003',
                'location' => null,
                'category_id' => null,
                'status' => 'planned',
                'scheduled_date' => now()->addDays(30),
                'started_at' => null,
                'completed_at' => null,
                'assigned_to' => null,
                'reviewed_by' => null,
                'notes' => 'Quarterly physical inventory',
            ]),
        ];

        // Add count lines for completed session
        $completedSession = $sessions[0];
        $selectedProducts = collect($products)->random(min(10, count($products)));

        foreach ($selectedProducts as $product) {
            $systemQty = $product->quantity_on_hand;
            $countedQty = $systemQty + rand(-2, 2); // Small variance
            $variance = $countedQty - $systemQty;
            $varianceStatus = abs($variance) <= 1 ? 'within_tolerance' : 'requires_review';

            DB::table('cycle_count_items')->insert([
                'session_id' => $completedSession->id,
                'product_id' => $product->id,
                'location_id' => null,
                'system_quantity' => $systemQty,
                'counted_quantity' => $countedQty,
                'variance' => $variance,
                'variance_status' => $varianceStatus,
                'count_notes' => null,
                'counted_by' => null,
                'counted_at' => now()->subDays(14),
                'adjustment_created' => false,
                'transaction_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('  ✓ Created ' . count($sessions) . ' cycle count sessions');
    }

    /**
     * Helper methods
     */
    private function getRandomMaintenanceTitle(): string
    {
        $titles = [
            'Routine Maintenance',
            'Safety Inspection',
            'Lubrication Service',
            'Filter Replacement',
            'Calibration Check',
            'Belt Replacement',
            'Cleaning and Adjustment',
        ];

        return $titles[array_rand($titles)];
    }

    private function getRandomTechnician(): string
    {
        $techs = [
            'John Martinez',
            'Sarah Chen',
            'Mike Thompson',
            'Lisa Anderson',
            'David Park',
        ];

        return $techs[array_rand($techs)];
    }

    private function getTransactionNote($type): string
    {
        $notes = [
            'receipt' => 'Received from supplier',
            'adjustment' => 'Inventory adjustment',
            'consumption' => 'Used in production',
            'cycle_count' => 'Physical count adjustment',
            'transfer' => 'Transferred between locations',
            'return' => 'Returned to supplier',
        ];

        return $notes[$type] ?? 'Transaction recorded';
    }

    /**
     * Display summary of seeded data
     */
    private function displaySummary(): void
    {
        $this->command->info("\n" . str_repeat('=', 60));
        $this->command->info('SEED DATA SUMMARY');
        $this->command->info(str_repeat('=', 60));

        $counts = [
            'Users' => User::count(),
            'Categories' => Category::count(),
            'Suppliers' => Supplier::count(),
            'Products' => Product::count(),
            'Storage Locations' => StorageLocation::count(),
            'Inventory Locations' => InventoryLocation::count(),
            'Machines' => Machine::count(),
            'Maintenance Tasks' => MaintenanceTask::count(),
            'Maintenance Records' => MaintenanceRecord::count(),
            'Job Reservations' => JobReservation::count(),
            'Purchase Orders' => PurchaseOrder::count(),
            'Inventory Transactions' => InventoryTransaction::count(),
            'Cycle Count Sessions' => CycleCountSession::count(),
        ];

        foreach ($counts as $label => $count) {
            $this->command->info(sprintf('  %-30s %s', $label . ':', $count));
        }

        $this->command->info(str_repeat('=', 60));

        $this->command->info("\n📋 Test Credentials:");
        $this->command->info("  Email: admin@forgedesk.com");
        $this->command->info("  Password: password");

        $this->command->info("\n📍 Storage Location Hierarchy:");
        $this->command->info("  • Aisle 1 → Rack A → Shelves 1-5 → Bins 1-8");
        $this->command->info("  • Aisle 2 → Racks A-B");
        $this->command->info("  • Aisle 3 → Rack A");
        $this->command->info("  • Aisle 4 → Rack A");
        $this->command->info("  • Receiving Area");
        $this->command->info("  • Returns Area");

        $this->command->info("\n✨ All seed data has been created successfully!");
        $this->command->info(str_repeat('=', 60) . "\n");
    }
}
