-- Mass insert storage locations with hierarchical structure
-- Structure: Aisle A (Racks 1-5) → Shelves
--           Aisle B (Racks 1-5) → Shelves
--           Aisle C (Racks 1-3) → Shelves

BEGIN;

-- Insert Aisle A
WITH aisle_a AS (
    INSERT INTO storage_locations (name, type, depth, path, created_at, updated_at)
    VALUES ('A', 'aisle', 0, NULL, NOW(), NOW())
    RETURNING id
),
-- Insert Racks for Aisle A
rack_a1 AS (
    INSERT INTO storage_locations (name, type, parent_id, depth, path, created_at, updated_at)
    SELECT 'A.1', 'rack', id, 1, id::text, NOW(), NOW()
    FROM aisle_a
    RETURNING id, parent_id
),
rack_a2 AS (
    INSERT INTO storage_locations (name, type, parent_id, depth, path, created_at, updated_at)
    SELECT 'A.2', 'rack', id, 1, id::text, NOW(), NOW()
    FROM aisle_a
    RETURNING id, parent_id
),
rack_a3 AS (
    INSERT INTO storage_locations (name, type, parent_id, depth, path, created_at, updated_at)
    SELECT 'A.3', 'rack', id, 1, id::text, NOW(), NOW()
    FROM aisle_a
    RETURNING id, parent_id
),
rack_a4 AS (
    INSERT INTO storage_locations (name, type, parent_id, depth, path, created_at, updated_at)
    SELECT 'A.4', 'rack', id, 1, id::text, NOW(), NOW()
    FROM aisle_a
    RETURNING id, parent_id
),
rack_a5 AS (
    INSERT INTO storage_locations (name, type, parent_id, depth, path, created_at, updated_at)
    SELECT 'A.5', 'rack', id, 1, id::text, NOW(), NOW()
    FROM aisle_a
    RETURNING id, parent_id
)
-- Insert Shelves for Aisle A Racks
INSERT INTO storage_locations (name, type, parent_id, depth, path, created_at, updated_at)
SELECT 'A.1.' || shelf_num, 'shelf', id, 2, parent_id::text || '/' || id::text, NOW(), NOW()
FROM rack_a1, generate_series(1, 7) AS shelf_num
UNION ALL
SELECT 'A.2.' || shelf_num, 'shelf', id, 2, parent_id::text || '/' || id::text, NOW(), NOW()
FROM rack_a2, generate_series(1, 7) AS shelf_num
UNION ALL
SELECT 'A.3.' || shelf_num, 'shelf', id, 2, parent_id::text || '/' || id::text, NOW(), NOW()
FROM rack_a3, generate_series(1, 7) AS shelf_num
UNION ALL
SELECT 'A.4.' || shelf_num, 'shelf', id, 2, parent_id::text || '/' || id::text, NOW(), NOW()
FROM rack_a4, generate_series(1, 6) AS shelf_num
UNION ALL
SELECT 'A.5.' || shelf_num, 'shelf', id, 2, parent_id::text || '/' || id::text, NOW(), NOW()
FROM rack_a5, generate_series(1, 6) AS shelf_num;

-- Insert Aisle B with Racks and Shelves
WITH aisle_b AS (
    INSERT INTO storage_locations (name, type, depth, path, created_at, updated_at)
    VALUES ('B', 'aisle', 0, NULL, NOW(), NOW())
    RETURNING id
),
racks_b AS (
    INSERT INTO storage_locations (name, type, parent_id, depth, path, created_at, updated_at)
    SELECT 'B.' || rack_num, 'rack', id, 1, id::text, NOW(), NOW()
    FROM aisle_b, generate_series(1, 5) AS rack_num
    RETURNING id, parent_id, name
)
-- Insert Shelves for all Aisle B Racks (1-6 shelves per rack)
INSERT INTO storage_locations (name, type, parent_id, depth, path, created_at, updated_at)
SELECT name || '.' || shelf_num, 'shelf', id, 2, parent_id::text || '/' || id::text, NOW(), NOW()
FROM racks_b, generate_series(1, 6) AS shelf_num;

-- Insert Aisle C with Racks and Shelves
WITH aisle_c AS (
    INSERT INTO storage_locations (name, type, depth, path, created_at, updated_at)
    VALUES ('C', 'aisle', 0, NULL, NOW(), NOW())
    RETURNING id
),
racks_c AS (
    INSERT INTO storage_locations (name, type, parent_id, depth, path, created_at, updated_at)
    SELECT 'C.' || rack_num, 'rack', id, 1, id::text, NOW(), NOW()
    FROM aisle_c, generate_series(1, 3) AS rack_num
    RETURNING id, parent_id, name
)
-- Insert Shelves for all Aisle C Racks (1-4 shelves per rack)
INSERT INTO storage_locations (name, type, parent_id, depth, path, created_at, updated_at)
SELECT name || '.' || shelf_num, 'shelf', id, 2, parent_id::text || '/' || id::text, NOW(), NOW()
FROM racks_c, generate_series(1, 4) AS shelf_num;

COMMIT;

-- Display summary of what was created
SELECT
    type,
    COUNT(*) as count
FROM storage_locations
WHERE name LIKE 'A%' OR name LIKE 'B%' OR name LIKE 'C%'
GROUP BY type
ORDER BY
    CASE type
        WHEN 'aisle' THEN 1
        WHEN 'rack' THEN 2
        WHEN 'shelf' THEN 3
    END;
