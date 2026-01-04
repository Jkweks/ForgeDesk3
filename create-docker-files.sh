#!/bin/bash

# Quick script to create all Docker files in current directory
# Run this in /mnt/homeNAS/Container/ForgeDesk3

echo "Creating Docker configuration files..."

# Create docker-compose.yml
cat > docker-compose.yml << 'EOF'
version: '3.8'

services:
  postgres:
    image: postgres:16-alpine
    container_name: forgedesk_postgres
    restart: unless-stopped
    environment:
      POSTGRES_DB: forgedesk
      POSTGRES_USER: forgedesk
      POSTGRES_PASSWORD: forgedesk_secure_password
      PGDATA: /var/lib/postgresql/data/pgdata
    volumes:
      - postgres_data:/var/lib/postgresql/data
    ports:
      - "5430:5432"
    networks:
      - forgedesk_network
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U forgedesk"]
      interval: 10s
      timeout: 5s
      retries: 5

  redis:
    image: redis:7-alpine
    container_name: forgedesk_redis
    restart: unless-stopped
    networks:
      - forgedesk_network
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5

  app:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: forgedesk_app
    restart: unless-stopped
    working_dir: /var/www/html
    volumes:
      - ./laravel:/var/www/html
      - ./php/php.ini:/usr/local/etc/php/conf.d/custom.ini
    environment:
      - DB_CONNECTION=pgsql
      - DB_HOST=postgres
      - DB_PORT=5432
      - DB_DATABASE=forgedesk
      - DB_USERNAME=forgedesk
      - DB_PASSWORD=forgedesk_secure_password
      - REDIS_HOST=redis
      - REDIS_PORT=6379
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_healthy
    networks:
      - forgedesk_network

  nginx:
    image: nginx:alpine
    container_name: forgedesk_nginx
    restart: unless-stopped
    ports:
      - "8040:80"
    volumes:
      - ./laravel:/var/www/html
      - ./nginx/default.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - app
    networks:
      - forgedesk_network

  queue:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: forgedesk_queue
    restart: unless-stopped
    working_dir: /var/www/html
    command: php artisan queue:work --sleep=3 --tries=3 --max-time=3600
    volumes:
      - ./laravel:/var/www/html
    environment:
      - DB_CONNECTION=pgsql
      - DB_HOST=postgres
      - DB_PORT=5432
      - DB_DATABASE=forgedesk
      - DB_USERNAME=forgedesk
      - DB_PASSWORD=forgedesk_secure_password
      - REDIS_HOST=redis
    depends_on:
      - postgres
      - redis
    networks:
      - forgedesk_network

networks:
  forgedesk_network:
    driver: bridge

volumes:
  postgres_data:
    driver: local
EOF

echo "✓ Created docker-compose.yml"

# Create Dockerfile
cat > Dockerfile << 'EOF'
FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    git curl libpng-dev libzip-dev zip unzip \
    postgresql-dev oniguruma-dev nodejs npm

RUN docker-php-ext-install \
    pdo pdo_pgsql pgsql mbstring zip exif pcntl bcmath gd

RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

RUN chown -R www-data:www-data /var/www/html

USER www-data

EXPOSE 9000

CMD ["php-fpm"]
EOF

echo "✓ Created Dockerfile"

# Create nginx directory and config
mkdir -p nginx

cat > nginx/default.conf << 'EOF'
server {
    listen 80;
    server_name localhost;
    root /var/www/html/public;
    index index.php index.html;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    client_max_body_size 50M;
}
EOF

echo "✓ Created nginx/default.conf"

# Create php directory and config
mkdir -p php

cat > php/php.ini << 'EOF'
upload_max_filesize = 50M
post_max_size = 50M
memory_limit = 256M
max_execution_time = 300
max_input_time = 300

[Date]
date.timezone = America/New_York
EOF

echo "✓ Created php/php.ini"

echo ""
echo "✅ All Docker files created!"
echo ""
echo "File structure:"
tree -L 2 2>/dev/null || ls -R

echo ""
echo "Next steps:"
echo "1. Build containers: docker compose build"
echo "2. Start services: docker compose up -d"
echo "3. Check status: docker compose ps"