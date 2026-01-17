#!/bin/bash

# ForgeDesk Database Setup Script
# This script will migrate and seed the database with default users

echo "🔧 ForgeDesk Database Setup"
echo "============================"
echo ""

# Check if containers are running
echo "📦 Checking if containers are running..."
if ! docker compose ps | grep -q "forgedesk_app.*running"; then
    echo "❌ Error: ForgeDesk containers are not running!"
    echo "Please start them with: docker compose up -d"
    exit 1
fi
echo "✅ Containers are running"
echo ""

# Run migrations
echo "🗄️  Running database migrations..."
docker compose exec -T app php artisan migrate --force
if [ $? -ne 0 ]; then
    echo "❌ Migration failed!"
    exit 1
fi
echo "✅ Migrations completed"
echo ""

# Run seeders
echo "🌱 Seeding database with default data..."
docker compose exec -T app php artisan db:seed --force
if [ $? -ne 0 ]; then
    echo "❌ Seeding failed!"
    exit 1
fi
echo "✅ Database seeded successfully"
echo ""

# Clear cache
echo "🧹 Clearing application cache..."
docker compose exec -T app php artisan config:clear
docker compose exec -T app php artisan cache:clear
docker compose exec -T app php artisan route:clear
echo "✅ Cache cleared"
echo ""

echo "✅ Setup complete!"
echo ""
echo "📝 Default Login Credentials:"
echo "   Email:    admin@forgedesk.local"
echo "   Password: password"
echo ""
echo "   OR"
echo ""
echo "   Email:    demo@forgedesk.local"
echo "   Password: demo123"
echo ""
echo "🌐 Access ForgeDesk at: https://tfd.kweks.co"
