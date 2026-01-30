#!/bin/bash
# Clear Laravel caches

echo "Clearing Laravel caches..."

# If running in Docker
if [ -f "docker-compose.yml" ]; then
    echo "Detected Docker setup, clearing caches in container..."
    docker-compose exec -T app php artisan route:clear
    docker-compose exec -T app php artisan cache:clear
    docker-compose exec -T app php artisan config:clear
    docker-compose exec -T app php artisan view:clear
    echo "✓ Docker caches cleared"
else
    # If running directly
    cd laravel
    php artisan route:clear
    php artisan cache:clear
    php artisan config:clear
    php artisan view:clear
    echo "✓ Caches cleared"
fi

echo "Done! Please refresh your browser."
