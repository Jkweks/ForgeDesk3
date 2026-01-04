#!/bin/bash

# ForgeDesk Fixed Setup Script
# Handles existing directories and docker-compose issues

set -e

echo "🔧 ForgeDesk Setup - Fixed Version"
echo ""

# Fix docker-compose first
echo "📦 Fixing docker-compose..."
if ! command -v docker compose &> /dev/null; then
    echo "Installing python3-distutils..."
    sudo apt-get update
    sudo apt-get install -y python3-distutils python3-setuptools
fi

# Check if we can use 'docker compose' (v2) instead of 'docker-compose' (v1)
if docker compose version &> /dev/null; then
    echo "✓ Using Docker Compose v2"
    DOCKER_COMPOSE="docker compose"
else
    echo "✓ Using Docker Compose v1"
    DOCKER_COMPOSE="docker-compose"
fi

# Clean up existing laravel directory if it exists but is incomplete
if [ -d "laravel" ] && [ ! -f "laravel/artisan" ]; then
    echo "⚠️  Found incomplete Laravel installation, cleaning up..."
    sudo rm -rf laravel
fi

# Create fresh Laravel installation
if [ ! -d "laravel" ] || [ ! -f "laravel/artisan" ]; then
    echo "📦 Creating fresh Laravel project..."
    mkdir -p laravel
    
    # Use composer to create Laravel in the existing directory
    docker run --rm -v $(pwd)/laravel:/app -w /app composer:latest create-project laravel/laravel . --no-interaction --prefer-dist
    
    # Set permissions
    sudo chown -R $USER:$USER laravel
    chmod -R 755 laravel
else
    echo "✓ Laravel already installed"
fi

# Create/Update .env file
if [ ! -f "laravel/.env" ]; then
    echo "📝 Creating environment file..."
    cp laravel/.env.example laravel/.env
fi

# Configure environment
echo "⚙️  Configuring environment..."
cd laravel

# Update .env with proper values
cat > .env << 'ENVEOF'
APP_NAME=ForgeDesk
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8040

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=forgedesk
DB_USERNAME=forgedesk
DB_PASSWORD=forgedesk_secure_password_change_me

BROADCAST_DRIVER=log
CACHE_DRIVER=redis
FILESYSTEM_DISK=local
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120

REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"
ENVEOF

cd ..

echo "🐳 Building Docker containers..."
$DOCKER_COMPOSE build

echo "🚀 Starting services..."
$DOCKER_COMPOSE up -d

echo "⏳ Waiting for services to be ready..."
sleep 20

# Check if services are running
echo "Checking service status..."
$DOCKER_COMPOSE ps

echo ""
echo "📚 Installing Laravel dependencies..."
$DOCKER_COMPOSE exec -T app composer install --no-interaction --prefer-dist

echo "🔑 Generating application key..."
$DOCKER_COMPOSE exec -T app php artisan key:generate --force

echo "🔐 Installing Laravel Breeze..."
$DOCKER_COMPOSE exec -T app composer require laravel/breeze --dev --no-interaction
$DOCKER_COMPOSE exec -T app php artisan breeze:install api --no-interaction

echo "🗄️  Running database migrations..."
$DOCKER_COMPOSE exec -T app php artisan migrate --force

echo "🔗 Creating storage link..."
$DOCKER_COMPOSE exec -T app php artisan storage:link

echo "🔒 Setting permissions..."
$DOCKER_COMPOSE exec -T app chown -R www-data:www-data /var/www/html/storage || true
$DOCKER_COMPOSE exec -T app chown -R www-data:www-data /var/www/html/bootstrap/cache || true

# Set correct permissions on host
sudo chown -R $USER:$USER laravel/storage laravel/bootstrap/cache
chmod -R 775 laravel/storage laravel/bootstrap/cache

echo ""
echo "✅ ForgeDesk Docker setup complete!"
echo ""
echo "🌐 Access at: http://localhost:8040"
echo "📊 Database: localhost:5430"
echo ""
echo "⚠️  NEXT STEPS:"
echo "1. Edit .env if needed: nano laravel/.env"
echo "2. Run: ./add-laravel-files.sh (to add Models/Controllers)"
echo "3. Run: $DOCKER_COMPOSE exec app php artisan migrate"
echo "4. Run: $DOCKER_COMPOSE exec app php artisan db:seed"
echo ""
echo "Useful commands:"
echo "  $DOCKER_COMPOSE ps              # Check status"
echo "  $DOCKER_COMPOSE logs -f         # View logs"
echo "  $DOCKER_COMPOSE exec app bash   # Enter container"
echo "  $DOCKER_COMPOSE down            # Stop services"
echo ""