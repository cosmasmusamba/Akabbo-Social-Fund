# Akabbo Social Fund
## Enterprise Savings & Loan Management System (ESLMS)

A secure, scalable, mobile-first web platform for managing savings groups, SACCOs, and financial cooperatives.

---

## Technology Stack
| Layer        | Technology                     |
|--------------|-------------------------------|
| Backend      | PHP 8.1+ (OOP, Class-based)   |
| Frontend     | Tailwind CSS                   |
| Interactions | AJAX / Vanilla JavaScript      |
| Database     | MySQL 8+ / MariaDB             |
| Auth         | Session-Based + CSRF           |

---

## Quick Setup

### 1. Database Setup
```sql
mysql -u root -p < database/schema.sql
```

### 2. Web Server Configuration
Point your virtual host document root to the `/public` directory.

**Apache** — `.htaccess` files are included.

**Nginx:**
```nginx
root /path/to/akabbo/public;
index index.php;
location / { try_files $uri $uri/ /index.php?$query_string; }
location ~ \.php$ { fastcgi_pass unix:/run/php/php8.1-fpm.sock; include fastcgi_params; fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name; }
```

### 3. Configuration
Edit `config/config.php` or set environment variables:
```
DB_HOST=localhost
DB_NAME=akabbo_fund
DB_USER=root
DB_PASS=yourpassword
APP_ENV=production
APP_URL=https://yourdomain.com
```

### 4. Default Login
- **Email:** `admin@akabbofund.org`
- **Password:** `Admin@123`
- **Important:** Change password immediately after first login.

---

## Directory Structure
```
/app            → Controllers, Models, Services, Helpers
/config         → App config, Database config
/database       → SQL schema and seeders
/public         → Web root (index.php, assets)
/resources      → Views, Layouts, Components
/routes         → URL routing
/storage        → Logs, Backups, Uploads
```

---

## Key Features
- ✅ Role-Based Access Control (9 roles)
- ✅ Complete loan lifecycle management
- ✅ Savings accounts with interest calculation
- ✅ Repayment schedule generation
- ✅ Real-time AJAX interactions
- ✅ Enterprise audit logging
- ✅ CSV/PDF report exports
- ✅ Mobile-first responsive design
- ✅ Soft-delete with recovery (Trash)
- ✅ Security: CSRF, XSS, SQL injection prevention
- ✅ Global search
- ✅ In-system notifications

---

© 2025 Akabbo Social Fund · v1.0.0
