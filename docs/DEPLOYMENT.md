# Deployment Guide

## Production Deployment

This system is currently deployed and running at:
**https://admin.infiniteautorentals.com**

## Server Configuration

### Production Server Details
- **Server IP**: 198.91.25.229
- **Web Server**: Nginx
- **PHP Version**: 8.1+
- **Database**: MySQL 8.0
- **SSL**: Let's Encrypt certificate

### File Locations
```
/var/www/admin.infiniteautorentals.com/
├── simple_erp.php (main application)
└── (nginx serves this directory)
```

### Database Configuration
```sql
Database: car_rental_erp
Host: localhost
User: root
Password: [configured in production]
```

## Nginx Configuration

```nginx
server {
    listen 80;
    server_name admin.infiniteautorentals.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl;
    server_name admin.infiniteautorentals.com;
    
    ssl_certificate /etc/letsencrypt/live/admin.infiniteautorentals.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/admin.infiniteautorentals.com/privkey.pem;
    
    root /var/www/admin.infiniteautorentals.com;
    index simple_erp.php;
    
    location / {
        try_files $uri $uri/ /simple_erp.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index simple_erp.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## SSL Certificate

SSL certificate is managed by Certbot and auto-renews:
```bash
# Certificate installation
certbot --nginx -d admin.infiniteautorentals.com

# Auto-renewal check
certbot renew --dry-run
```

## Database Backup

Regular backups should be configured:
```bash
# Create backup
mysqldump -u root -p car_rental_erp > backup_$(date +%Y%m%d).sql

# Restore backup
mysql -u root -p car_rental_erp < backup_file.sql
```

## Monitoring

### Health Check
The system can be monitored by checking:
- HTTPS response: `curl -I https://admin.infiniteautorentals.com`
- Database connectivity: Check dashboard statistics load
- Form functionality: Test vehicle/customer creation

### Log Files
```bash
# Nginx logs
tail -f /var/log/nginx/access.log
tail -f /var/log/nginx/error.log

# PHP logs
tail -f /var/log/php8.1-fpm.log
```

## Updates and Maintenance

### Updating the Application
1. Download new version from GitHub
2. Backup current files and database
3. Replace `simple_erp.php` with new version
4. Test functionality
5. Update database schema if needed

### Security Updates
- Keep PHP updated
- Update Nginx regularly
- Monitor SSL certificate expiration
- Regular database backups

## Performance Optimization

### PHP Configuration
```ini
# /etc/php/8.1/fpm/php.ini
memory_limit = 256M
max_execution_time = 30
upload_max_filesize = 10M
post_max_size = 10M
```

### Database Optimization
```sql
-- Add indexes for better performance
CREATE INDEX idx_vehicle_status ON vehicles(status);
CREATE INDEX idx_reservation_dates ON reservations(start_date, end_date);
CREATE INDEX idx_customer_email ON customers(email);
```

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Check MySQL service: `systemctl status mysql`
   - Verify credentials in `simple_erp.php`
   - Check database exists: `mysql -u root -p -e "SHOW DATABASES;"`

2. **PHP Errors**
   - Check PHP-FPM: `systemctl status php8.1-fpm`
   - Review error logs: `/var/log/nginx/error.log`
   - Verify file permissions: `chown -R www-data:www-data /var/www/admin.infiniteautorentals.com`

3. **SSL Issues**
   - Check certificate: `certbot certificates`
   - Renew if needed: `certbot renew`
   - Verify Nginx config: `nginx -t`

### Emergency Contacts
- Server Administrator: [Your contact info]
- Database Administrator: [Your contact info]
- SSL Certificate: Let's Encrypt (auto-managed)

## Backup and Recovery

### Full System Backup
```bash
# Files
tar -czf erp_files_backup.tar.gz /var/www/admin.infiniteautorentals.com/

# Database
mysqldump -u root -p car_rental_erp > erp_database_backup.sql

# Nginx config
cp /etc/nginx/sites-available/admin.infiniteautorentals.com nginx_backup.conf
```

### Recovery Process
1. Restore files to web directory
2. Import database backup
3. Restore Nginx configuration
4. Restart services
5. Test functionality

