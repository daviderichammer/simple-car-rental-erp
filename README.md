# Simple Car Rental ERP System

A lightweight, straightforward Enterprise Resource Planning (ERP) system designed specifically for car rental businesses. Built with PHP and MySQL, this system prioritizes simplicity and ease of use over complex features.

## 🌟 Key Features

- **Vehicle Management**: Add, view, and update vehicle information and status
- **Customer Management**: Complete customer database with contact information
- **Reservation System**: Book and track vehicle rentals
- **Maintenance Scheduling**: Schedule and monitor vehicle maintenance
- **Dashboard**: Real-time statistics and overview
- **Mobile Responsive**: Works seamlessly on desktop and mobile devices

## 🎯 Design Philosophy

This system follows a **SIMPLE, SIMPLE, SIMPLE** architecture:
- ✅ Basic HTML forms that actually work
- ✅ No complex JavaScript frameworks
- ✅ No API dependencies
- ✅ Easy to modify and extend
- ✅ Reliable form submissions
- ✅ Clean, professional interface

## 🚀 Live Demo

**Production System**: https://admin.infiniteautorentals.com

## 📋 Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- SSL certificate (recommended)

## 🛠️ Installation

### 1. Database Setup
```sql
mysql -u root -p < create_database_schema.sql
```

### 2. Web Server Configuration
```bash
# Copy files to web directory
cp simple_erp.php /var/www/your-domain/
chmod 644 simple_erp.php

# Configure your web server to serve the PHP file
```

### 3. Database Configuration
Edit the database connection settings in `simple_erp.php`:
```php
$host = 'localhost';
$dbname = 'car_rental_erp';
$username = 'root';
$password = 'your_password';
```

## 📁 File Structure

```
simple-car-rental-erp/
├── simple_erp.php              # Main application file
├── create_database_schema.sql  # Database setup script
├── README.md                   # This file
└── docs/                       # Documentation (if needed)
```

## 🗄️ Database Schema

The system uses 5 main tables:
- `vehicles` - Vehicle inventory and details
- `customers` - Customer information
- `reservations` - Rental bookings
- `maintenance_schedules` - Vehicle maintenance tracking
- `financial_transactions` - Payment and billing records

## 🔧 Customization

### Adding New Fields
1. Update the database schema
2. Modify the HTML forms in `simple_erp.php`
3. Update the PHP processing logic

### Styling Changes
All CSS is embedded in the PHP file for simplicity. Look for the `<style>` section to modify appearance.

## 🔒 Security Features

- ✅ SQL injection protection using prepared statements
- ✅ Input validation and sanitization
- ✅ HTTPS encryption (when properly configured)
- ✅ Error handling and user feedback

## 📱 Mobile Support

The system is fully responsive and works on:
- Desktop computers
- Tablets
- Mobile phones
- Touch devices

## 🆘 Support

For issues or questions:
1. Check the code comments in `simple_erp.php`
2. Review the database schema in `create_database_schema.sql`
3. Test functionality on the live demo site

## 📝 License

This project is designed for practical use in car rental businesses. Modify and adapt as needed for your specific requirements.

## 🏗️ Architecture Notes

This system intentionally avoids:
- Complex JavaScript frameworks
- External API dependencies
- Microservices architecture
- Complex build processes

Instead, it provides:
- Single-file application
- Direct database connections
- Simple HTML forms
- Immediate functionality

Perfect for businesses that need a working system NOW, not after months of development and debugging.

