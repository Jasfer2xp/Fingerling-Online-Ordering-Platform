# Installation Guide

## 📋 Prerequisites

Before installing the Fingerling Trading Platform, ensure your system meets these requirements:

### Server Requirements
- **PHP**: Version 8.0 or higher
- **MySQL**: Version 8.0 or higher  
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Memory**: Minimum 512MB RAM
- **Storage**: At least 1GB free space

### PHP Extensions Required
- `mysqli` or `pdo_mysql`
- `gd` (for image processing)
- `fileinfo` (for file uploads)
- `json`
- `session`
- `mbstring`

## 🚀 Installation Steps

### Step 1: Download and Extract

1. Download the platform files
2. Extract to your web server directory:
   - **XAMPP**: `C:\xampp\htdocs\PLATFORM`
   - **WAMP**: `C:\wamp64\www\PLATFORM`
   - **Linux**: `/var/www/html/PLATFORM`

### Step 2: Database Setup

1. **Create Database**:
   ```sql
   CREATE DATABASE fingerling_platform;
   ```

2. **Import Database Schema**:
   - Open phpMyAdmin or MySQL command line
   - Select the `fingerling_platform` database
   - Import the provided SQL file: `database/schema.sql`

3. **Create Database User** (Optional but recommended):
   ```sql
   CREATE USER 'fingerling_user'@'localhost' IDENTIFIED BY 'secure_password';
   GRANT ALL PRIVILEGES ON fingerling_platform.* TO 'fingerling_user'@'localhost';
   FLUSH PRIVILEGES;
   ```

### Step 3: Configuration

1. **Database Configuration**:
   Edit `config/config.php`:
   ```php
   // Database Configuration
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'fingerling_platform');
   define('DB_USER', 'fingerling_user');
   define('DB_PASS', 'secure_password');
   ```

2. **Application Settings**:
   ```php
   // Application Configuration
   define('APP_NAME', 'Fingerling Trading Platform');
   define('BASE_URL', 'http://localhost/PLATFORM/');
   define('UPLOAD_PATH', 'uploads/');
   ```

### Step 4: File Permissions

Set appropriate permissions for upload directories:

**Windows (XAMPP/WAMP)**:
- Right-click on `uploads/` folder
- Properties → Security → Edit
- Give "Full Control" to "Users" group

**Linux/macOS**:
```bash
chmod 755 uploads/
chmod 755 uploads/products/
chmod 755 uploads/profiles/
```

### Step 5: Web Server Configuration

#### Apache (.htaccess)
Create `.htaccess` in the root directory:
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
```

#### Nginx
Add to your Nginx configuration:
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass 127.0.0.1:9000;
    fastcgi_index index.php;
    include fastcgi_params;
}
```

### Step 6: Initial Setup

1. **Access the Platform**:
   Open your browser and navigate to:
   `http://localhost/PLATFORM/`

2. **Create Admin Account**:
   - Go to `http://localhost/PLATFORM/auth/register.php`
   - Register as an admin user
   - Manually update the database to set user_type = 'admin'

3. **Test Installation**:
   - Login with admin credentials
   - Verify all panels are accessible
   - Test basic functionality

## 🔧 Advanced Configuration

### Email Configuration (Optional)

For email notifications, configure SMTP settings in `config/config.php`:
```php
// Email Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SMTP_ENCRYPTION', 'tls');
```

### File Upload Limits

Adjust PHP settings for larger file uploads:

**php.ini**:
```ini
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 300
memory_limit = 256M
```

### Security Hardening

1. **Change Default Passwords**:
   - Update database passwords
   - Use strong, unique passwords

2. **Hide Sensitive Files**:
   ```apache
   <Files "config.php">
       Order allow,deny
       Deny from all
   </Files>
   ```

3. **Enable HTTPS** (Production):
   - Obtain SSL certificate
   - Update BASE_URL to use https://
   - Force HTTPS redirects

## 🐛 Troubleshooting

### Common Issues

#### Database Connection Error
- Verify database credentials in `config/config.php`
- Ensure MySQL service is running
- Check database user permissions

#### File Upload Issues
- Check upload directory permissions
- Verify PHP upload limits
- Ensure `uploads/` directory exists

#### Blank Pages
- Enable PHP error reporting:
  ```php
  ini_set('display_errors', 1);
  error_reporting(E_ALL);
  ```
- Check web server error logs

#### Permission Denied
- Verify file/folder permissions
- Check web server user ownership
- Ensure proper directory structure

### Debug Mode

Enable debug mode for development:
```php
// In config/config.php
define('DEBUG_MODE', true);
define('DISPLAY_ERRORS', true);
```

## 📊 Performance Optimization

### Database Optimization
- Enable MySQL query cache
- Add appropriate indexes
- Regular database maintenance

### Web Server Optimization
- Enable gzip compression
- Set proper cache headers
- Optimize static file serving

### PHP Optimization
- Enable OPcache
- Increase memory limits
- Optimize session handling

## 🔄 Updates and Maintenance

### Regular Maintenance
- Database backups
- Log file rotation
- Security updates
- Performance monitoring

### Update Process
1. Backup database and files
2. Download new version
3. Replace files (preserve config)
4. Run database migrations
5. Test functionality

## 📞 Support

If you encounter issues during installation:

1. Check the troubleshooting section
2. Verify system requirements
3. Review error logs
4. Contact technical support

---

**Installation complete!** Your Fingerling Trading Platform should now be ready for use.
