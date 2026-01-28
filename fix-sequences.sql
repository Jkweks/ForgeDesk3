-- Fix PostgreSQL sequence issues
-- This script resets all auto-increment sequences to match the current max ID in each table

-- Fix cycle_count_items sequence (the main issue)
SELECT setval('cycle_count_items_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM cycle_count_items), false);

-- Fix cycle_count_sessions sequence
SELECT setval('cycle_count_sessions_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM cycle_count_sessions), false);

-- Fix other common tables
SELECT setval('users_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM users), false);
SELECT setval('categories_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM categories), false);
SELECT setval('products_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM products), false);
SELECT setval('suppliers_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM suppliers), false);
SELECT setval('orders_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM orders), false);
SELECT setval('order_items_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM order_items), false);
SELECT setval('inventory_transactions_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM inventory_transactions), false);
SELECT setval('inventory_locations_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM inventory_locations), false);
SELECT setval('job_reservations_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM job_reservations), false);
SELECT setval('job_reservation_items_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM job_reservation_items), false);
SELECT setval('purchase_orders_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM purchase_orders), false);
SELECT setval('purchase_order_items_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM purchase_order_items), false);
SELECT setval('machines_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM machines), false);
SELECT setval('assets_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM assets), false);
SELECT setval('maintenance_tasks_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM maintenance_tasks), false);
SELECT setval('maintenance_records_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM maintenance_records), false);
SELECT setval('machine_tooling_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM machine_tooling), false);
SELECT setval('storage_locations_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM storage_locations), false);
SELECT setval('required_parts_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM required_parts), false);

-- Display confirmation
SELECT 'Sequences fixed successfully!' as status;
