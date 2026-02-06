<?php
/**
 * Quick script to set net_cost to unit_cost where net_cost is null or 0
 * Run with: php set_net_cost.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;
use Illuminate\Support\Facades\DB;

echo "Setting net_cost to unit_cost where net_cost is null or 0...\n";

DB::beginTransaction();

try {
    $updated = Product::where(function($query) {
        $query->whereNull('net_cost')
              ->orWhere('net_cost', 0);
    })
    ->update([
        'net_cost' => DB::raw('unit_cost')
    ]);

    DB::commit();

    echo "✓ Updated {$updated} products\n";
    echo "Net cost now matches unit cost for products that had null or 0 net cost.\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "✗ Error: " . $e->getMessage() . "\n";
}
