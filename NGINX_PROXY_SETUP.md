# ForgeDesk Nginx Proxy Manager Setup Guide

This guide will help you configure ForgeDesk to work behind Nginx Proxy Manager at `https://tfd.kweks.co`.

## Prerequisites

- ForgeDesk running at `http://192.168.4.251:8040`
- Nginx Proxy Manager instance configured
- DNS record for `tfd.kweks.co` pointing to your Nginx Proxy Manager server

## Step 1: Configure Nginx Proxy Manager

### Create Proxy Host

1. Log into your Nginx Proxy Manager admin panel
2. Go to **Hosts** → **Proxy Hosts**
3. Click **Add Proxy Host**

### Details Tab

Configure the following:

| Setting | Value |
|---------|-------|
| **Domain Names** | `tfd.kweks.co` |
| **Scheme** | `http` |
| **Forward Hostname / IP** | `192.168.4.251` |
| **Forward Port** | `8040` |
| **Cache Assets** | ✅ Enabled (recommended) |
| **Block Common Exploits** | ✅ Enabled |
| **Websockets Support** | ✅ Enabled |

### SSL Tab

Configure SSL certificate:

| Setting | Value |
|---------|-------|
| **SSL Certificate** | Select existing or request new Let's Encrypt certificate |
| **Force SSL** | ✅ Enabled (redirect HTTP to HTTPS) |
| **HTTP/2 Support** | ✅ Enabled |
| **HSTS Enabled** | ✅ Enabled |
| **HSTS Subdomains** | ✅ Enabled (if desired) |

### Advanced Tab

Add the following custom Nginx configuration:

```nginx
# Pass real client IP and protocol information
proxy_set_header X-Real-IP $remote_addr;
proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
proxy_set_header X-Forwarded-Proto $scheme;
proxy_set_header X-Forwarded-Host $host;
proxy_set_header X-Forwarded-Port $server_port;
proxy_set_header Host $host;

# Increase timeouts for long-running requests
proxy_connect_timeout 60s;
proxy_send_timeout 60s;
proxy_read_timeout 60s;

# Enable buffering for better performance
proxy_buffering on;
proxy_buffer_size 4k;
proxy_buffers 8 4k;
proxy_busy_buffers_size 8k;

# Allow larger uploads (for product imports)
client_max_body_size 50M;
```

4. Click **Save**

## Step 2: Start ForgeDesk

If ForgeDesk isn't already running, start it:

```bash
cd /home/user/ForgeDesk3
docker compose up -d
```

Verify all containers are running:

```bash
docker compose ps
```

You should see:
- `forgedesk_nginx` - running on port 8040
- `forgedesk_app` - PHP-FPM application
- `forgedesk_postgres` - PostgreSQL database
- `forgedesk_redis` - Redis cache
- `forgedesk_queue` - Background job worker

## Step 3: Setup Database

Run migrations and seed the database with default users:

```bash
cd /home/user/ForgeDesk3
./setup-database.sh
```

This script will:
1. Run database migrations
2. Seed default admin and demo users
3. Clear application cache

### Manual Database Setup (if script fails)

If the script fails, run commands manually:

```bash
# Run migrations
docker compose exec app php artisan migrate --force

# Seed database
docker compose exec app php artisan db:seed --force

# Clear cache
docker compose exec app php artisan config:clear
docker compose exec app php artisan cache:clear
docker compose exec app php artisan route:clear
```

## Step 4: Test the Configuration

### 1. Test Direct Access (Local Network)

First, verify ForgeDesk works directly:

```bash
curl -I http://192.168.4.251:8040
```

You should see a `200 OK` response.

### 2. Test Through Proxy

Visit: `https://tfd.kweks.co`

You should see the ForgeDesk login page.

### 3. Login

Use the default credentials:

**Admin User:**
- Email: `admin@forgedesk.local`
- Password: `password`

**Demo User:**
- Email: `demo@forgedesk.local`
- Password: `demo123`

### 4. Verify HTTPS

Check your browser's address bar:
- Should show `🔒 https://tfd.kweks.co`
- SSL certificate should be valid
- No mixed content warnings

### 5. Test API Endpoints

Open browser console (F12) and verify:
- No CORS errors
- API calls use relative paths (`/api/v1/*`)
- Authentication token is stored in localStorage
- All requests show as HTTPS

## Troubleshooting

### Issue: 401 Unauthorized on Login

**Cause:** Database not seeded with default users

**Solution:**
```bash
docker compose exec app php artisan db:seed --class=AdminSeeder --force
```

### Issue: 502 Bad Gateway

**Cause:** ForgeDesk containers not running or nginx config error

**Solution:**
```bash
# Check container status
docker compose ps

# Restart containers
docker compose down
docker compose up -d

# Check logs
docker compose logs nginx
docker compose logs app
```

### Issue: Mixed Content Warnings

**Cause:** APP_URL not set to HTTPS

**Solution:** Already configured in `.env`:
```
APP_URL=https://tfd.kweks.co
SESSION_SECURE_COOKIE=true
```

Restart containers:
```bash
docker compose restart app nginx
```

### Issue: Session/Authentication Issues

**Cause:** Cookies not being set correctly

**Solution:**

1. Clear browser cookies for `tfd.kweks.co`
2. Verify `.env` settings:
   ```
   SESSION_DOMAIN=tfd.kweks.co
   SESSION_SECURE_COOKIE=true
   SESSION_SAME_SITE=lax
   ```
3. Restart app:
   ```bash
   docker compose restart app
   ```

### Issue: Cannot Connect to Database

**Cause:** PostgreSQL container not running or wrong credentials

**Solution:**
```bash
# Check postgres container
docker compose ps postgres

# Check logs
docker compose logs postgres

# Restart postgres
docker compose restart postgres

# Verify connection
docker compose exec postgres psql -U forgedesk -d forgedesk -c "SELECT 1;"
```

### Issue: Assets/CSS Not Loading

**Cause:** Vite build not compiled or path issues

**Solution:**
```bash
# Rebuild Vite assets
docker compose exec app npm install
docker compose exec app npm run build

# Or use the host's npm if mounted
cd laravel
npm install
npm run build
```

## Monitoring

### Check Application Logs

```bash
# All logs
docker compose logs -f

# Specific service logs
docker compose logs -f app
docker compose logs -f nginx
docker compose logs -f postgres
```

### Check Laravel Logs

```bash
# View Laravel application logs
docker compose exec app tail -f storage/logs/laravel.log
```

### Health Check

Test the health endpoint:

```bash
curl https://tfd.kweks.co/up
```

Should return: `200 OK`

## Security Recommendations

### 1. Change Default Passwords

After first login, change the default admin password:

1. Log in as admin
2. Go to Profile/Settings
3. Change password to a strong, unique password

### 2. Restrict Trusted Proxies (Production)

In `.env`, change:
```bash
# Current (trusts all proxies)
TRUSTED_PROXIES=*

# Recommended (specify your nginx proxy manager IP)
TRUSTED_PROXIES=192.168.4.xxx
```

Replace `192.168.4.xxx` with your actual Nginx Proxy Manager IP.

### 3. Disable Debug Mode

Already configured in `.env`:
```
APP_DEBUG=false
APP_ENV=production
```

### 4. Enable Rate Limiting

Add to `nginx/default.conf` for DDoS protection:

```nginx
limit_req_zone $binary_remote_addr zone=forgedesk:10m rate=10r/s;

server {
    limit_req zone=forgedesk burst=20 nodelay;
    # ... rest of config
}
```

### 5. Regular Backups

Set up automated database backups:

```bash
# Create backup script
cat > /home/user/ForgeDesk3/backup-database.sh << 'EOF'
#!/bin/bash
BACKUP_DIR="/home/user/ForgeDesk3/backups"
DATE=$(date +%Y%m%d_%H%M%S)
mkdir -p "$BACKUP_DIR"
docker compose exec -T postgres pg_dump -U forgedesk forgedesk | gzip > "$BACKUP_DIR/forgedesk_$DATE.sql.gz"
find "$BACKUP_DIR" -name "*.sql.gz" -mtime +30 -delete
EOF

chmod +x /home/user/ForgeDesk3/backup-database.sh

# Add to cron (daily at 2 AM)
# 0 2 * * * /home/user/ForgeDesk3/backup-database.sh
```

## Configuration Files Reference

### Files Modified for Proxy Setup

1. **`.env`** - Application environment configuration
   - `APP_URL=https://tfd.kweks.co`
   - `SESSION_SECURE_COOKIE=true`
   - `SESSION_DOMAIN=tfd.kweks.co`
   - `TRUSTED_PROXIES=*`

2. **`nginx/default.conf`** - Nginx reverse proxy configuration
   - Added `fastcgi_param` directives for X-Forwarded headers

3. **`laravel/bootstrap/app.php`** - Laravel application bootstrap
   - Registered TrustProxies middleware

4. **`laravel/app/Http/Middleware/TrustProxies.php`** - Proxy trust middleware
   - Handles X-Forwarded-* headers from upstream proxy

## Architecture Diagram

```
Internet
   ↓
DNS (tfd.kweks.co)
   ↓
Nginx Proxy Manager (HTTPS)
   ↓ [X-Forwarded-* headers]
192.168.4.251:8040 (HTTP)
   ↓
ForgeDesk Nginx Container
   ↓ FastCGI
ForgeDesk PHP-FPM (Laravel)
   ↓
PostgreSQL Database
```

## Support

If you encounter issues:

1. Check logs: `docker compose logs -f`
2. Verify all containers are running: `docker compose ps`
3. Test direct access: `curl -I http://192.168.4.251:8040`
4. Check Nginx Proxy Manager logs
5. Verify DNS resolves: `nslookup tfd.kweks.co`

## Default Credentials

**Remember to change these after first login!**

- **Admin**: admin@forgedesk.local / password
- **Demo**: demo@forgedesk.local / demo123
