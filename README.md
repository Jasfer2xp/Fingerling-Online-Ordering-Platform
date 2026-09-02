# 🐟 Fingerling Online Ordering Platform

A comprehensive web-based platform connecting fish farmers with quality fingerling suppliers across the Philippines. Built with modern web technologies and featuring intelligent location services, real-time mapping, and advanced user management.

![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?style=flat&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=flat&logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3.0-7952B3?style=flat&logo=bootstrap&logoColor=white)
![Leaflet](https://img.shields.io/badge/Leaflet-1.9.4-199900?style=flat&logo=leaflet&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green.svg)

## 🌟 Key Features

### 🤖 **Intelligent Location System**
- **Smart Auto-Fill**: Automatically fills address fields when location is set on map
- **Auto-Pinpointing**: Automatically pinpoints location when address is typed
- **Bidirectional Sync**: Real-time synchronization between map coordinates and address fields
- **Multiple Map Layers**: Street view, satellite view, and detailed street maps
- **Street View Integration**: Google Street View access for location verification
- **GPS Location**: Precise GPS coordinate detection and mapping

### 👥 **Multi-User System**
- **Admin Dashboard**: Complete platform management and analytics
- **Supplier Portal**: Inventory management, order processing, location registration
- **Customer Interface**: Product browsing, shopping cart, order tracking
- **Role-Based Access**: Secure authentication with proper permission levels

### 🗺️ **Advanced Mapping Features**
- **Interactive Maps**: Leaflet.js integration with custom markers
- **Color-Coded Markers**: Different colors for GPS, manual, search, and auto-pinpointed locations
- **Geocoding Services**: Forward and reverse geocoding using Nominatim API
- **Philippine-Optimized**: Specialized for Philippine address formats
- **Real-Time Updates**: Live coordinate and address synchronization

### 🛒 **E-Commerce Functionality**
- **Product Catalog**: Comprehensive fingerling species database
- **Shopping Cart**: Advanced cart management with quantity controls
- **Order Management**: Complete order lifecycle from placement to delivery
- **Payment Integration**: Ready for GCash, PayPal, and other payment gateways
- **Inventory Tracking**: Real-time stock management and availability

### 📊 **Analytics & Reporting**
- **Dashboard Analytics**: Real-time statistics and performance metrics
- **Sales Reports**: Comprehensive sales and revenue tracking
- **User Analytics**: Customer and supplier activity monitoring
- **Order Insights**: Detailed order analysis and trends

## 🚀 Quick Start

### Prerequisites
- PHP 8.0 or higher
- MySQL 8.0 or higher
- Apache/Nginx web server
- Composer (optional, for dependencies)

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/fingerling-marketplace.git
   cd fingerling-marketplace
   ```

2. **Database Setup**
   ```bash
   # Create database
   mysql -u root -p
   CREATE DATABASE fingerling_marketplace;
   
   # Import schema
   mysql -u root -p fingerling_marketplace < database/schema.sql
   
   # Import sample data (optional)
   mysql -u root -p fingerling_marketplace < database/sample_data.sql
   ```

3. **Configuration**
   ```bash
   # Copy environment file
   cp .env.example .env
   
   # Edit configuration
   nano config/config.php
   ```

4. **Web Server Setup**
   - Place files in your web server directory (e.g., `/var/www/html/PLATFORM/`)
   - Ensure proper file permissions
   - Configure virtual host if needed

5. **Access the Application**
   - Open browser and navigate to `http://localhost/PLATFORM/`
   - Use provided credentials from `CREDENTIALS.md`

## 🔐 Default Login Credentials

### Admin Account
- **Email**: `admin@gmail.com`
- **Password**: `12345678`
- **Dashboard**: `/admin/dashboard.php`

### Supplier Account
- **Email**: `supplier@gmail.com`
- **Password**: `12345678`
- **Dashboard**: `/supplier/dashboard.php`

### Customer Account
- **Email**: `customer@gmail.com`
- **Password**: `12345678`
- **Dashboard**: `/customer/dashboard.php`

## 🏗️ Project Structure

```
fingerling-marketplace/
├── admin/                  # Admin dashboard and management
├── api/                    # REST API endpoints
├── assets/                 # CSS, JS, images, and other static files
├── auth/                   # Authentication system
├── classes/                # PHP classes and models
├── config/                 # Configuration files
├── customer/               # Customer portal
├── database/               # Database schemas and migrations
├── includes/               # Shared PHP includes
├── supplier/               # Supplier portal
├── uploads/                # User uploaded files
├── views/                  # Shared view templates
├── index.php               # Main entry point
└── README.md               # This file
```

## 🛠️ Technology Stack

### Backend
- **PHP 8.0+**: Server-side scripting
- **MySQL 8.0+**: Relational database
- **PDO**: Database abstraction layer
- **Session Management**: Secure user sessions
- **CSRF Protection**: Cross-site request forgery protection

### Frontend
- **Bootstrap 5.3.0**: Responsive CSS framework
- **Font Awesome 6.0**: Icon library
- **Leaflet.js 1.9.4**: Interactive mapping
- **Chart.js**: Data visualization
- **AOS**: Animate on scroll library
- **Custom CSS/JS**: Enhanced user experience

### APIs & Services
- **Nominatim API**: Geocoding and reverse geocoding
- **Google Street View**: Street-level imagery
- **Geolocation API**: Browser GPS access
- **OpenStreetMap**: Map tile services

## 📱 Responsive Design

The platform is fully responsive and optimized for:
- **Desktop**: Full-featured experience with advanced mapping
- **Tablet**: Touch-optimized interface with gesture support
- **Mobile**: Mobile-first design with simplified navigation
- **Cross-Browser**: Compatible with Chrome, Firefox, Safari, Edge

## 🔒 Security Features

- **Password Hashing**: Bcrypt encryption for user passwords
- **CSRF Protection**: Token-based request validation
- **SQL Injection Prevention**: Prepared statements and parameterized queries
- **XSS Protection**: Input sanitization and output encoding
- **Session Security**: Secure session management with timeout
- **Role-Based Access**: Proper authorization checks

## 🌐 API Documentation

### Authentication Endpoints
- `POST /api/auth/login.php` - User login
- `POST /api/auth/register.php` - User registration
- `POST /api/auth/logout.php` - User logout

### Product Endpoints
- `GET /api/products/list.php` - Get product list
- `GET /api/products/details.php?id={id}` - Get product details
- `GET /api/products/search.php?q={query}` - Search products

### Cart Endpoints
- `POST /api/cart/add.php` - Add item to cart
- `PUT /api/cart/update.php` - Update cart item
- `DELETE /api/cart/remove.php` - Remove cart item

### Location Endpoints
- `GET /api/location/geocode.php` - Forward geocoding
- `GET /api/location/reverse.php` - Reverse geocoding

## 🎯 Core Functionality

### For Suppliers
- **Registration with Location**: Intelligent location registration during signup
- **Inventory Management**: Add, update, and manage fingerling inventory
- **Order Processing**: Receive and process customer orders
- **Location Services**: Set business location with GPS or map interaction
- **Analytics Dashboard**: Track sales, orders, and performance metrics

### For Customers
- **Product Discovery**: Browse fingerlings by species, location, and price
- **Interactive Maps**: View supplier locations with street view integration
- **Shopping Experience**: Add to cart, checkout, and order tracking
- **Location-Based Search**: Find suppliers near your location
- **Feedback System**: Rate and review suppliers and products

### For Administrators
- **User Management**: Approve suppliers, manage customers and admins
- **Platform Analytics**: Comprehensive dashboard with key metrics
- **Content Management**: Manage species, categories, and announcements
- **System Settings**: Configure platform settings and preferences
- **Location Monitoring**: View all supplier locations on interactive map

## 🗺️ Advanced Location Features

### Intelligent Address System
```javascript
// Auto-fill address when location is set
function reverseGeocode(lat, lng) {
    // Converts coordinates to readable address
    // Auto-fills: Business Address, Barangay, City, Province
    // Provides visual feedback with green highlighting
}

// Auto-pinpoint location when address is typed
function forwardGeocode() {
    // Converts address to coordinates
    // Places violet marker on map
    // Updates coordinate fields automatically
}
```

### Map Integration Features
- **Multiple Tile Layers**: Street, Satellite, Detailed Street views
- **Custom Markers**: Color-coded by action type (GPS=Green, Click=Blue, Search=Orange, Auto=Violet)
- **Street View Access**: Direct integration with Google Street View
- **Real-time Sync**: Bidirectional updates between map and form fields
- **Philippine-Optimized**: Specialized geocoding for Philippine addresses

## 🧪 Testing

### Manual Testing Scenarios
1. **Supplier Registration Flow**
   - Register new supplier account
   - Test location setting via GPS
   - Test location setting via map click
   - Test location setting via address search
   - Verify address auto-fill functionality

2. **Customer Shopping Experience**
   - Browse products with map view
   - Use location-based filtering
   - Test shopping cart functionality
   - Complete order placement process

3. **Admin Management Tasks**
   - Approve pending supplier accounts
   - Monitor platform analytics
   - Manage system settings
   - View supplier location map

### Test Data
- **Users**: 3 admin, 5 supplier, 10 customer accounts
- **Products**: 50+ fingerling species with varying prices
- **Locations**: Test coordinates across major Philippine cities
- **Orders**: Sample transaction history for analytics testing

## 🚀 Deployment

### Production Deployment
1. **Server Requirements**
   ```bash
   # Minimum server specifications
   - CPU: 2 cores
   - RAM: 4GB
   - Storage: 20GB SSD
   - PHP 8.0+ with extensions: pdo, pdo_mysql, gd, curl, mbstring
   - MySQL 8.0+
   - Apache/Nginx with mod_rewrite
   ```

2. **Environment Setup**
   ```bash
   # Clone repository
   git clone https://github.com/yourusername/fingerling-marketplace.git
   cd fingerling-marketplace

   # Set permissions
   chmod -R 755 .
   chmod -R 777 uploads/

   # Configure environment
   cp .env.example .env
   nano .env
   ```

3. **Database Migration**
   ```sql
   -- Create production database
   CREATE DATABASE fingerling_marketplace_prod;

   -- Import schema
   mysql -u username -p fingerling_marketplace_prod < database/schema.sql

   -- Import initial data
   mysql -u username -p fingerling_marketplace_prod < database/migrate_admin_tables.sql
   ```

4. **Web Server Configuration**
   ```apache
   # Apache .htaccess example
   RewriteEngine On
   RewriteCond %{REQUEST_FILENAME} !-f
   RewriteCond %{REQUEST_FILENAME} !-d
   RewriteRule ^(.*)$ index.php [QSA,L]
   ```

### Docker Deployment (Optional)
```dockerfile
# Dockerfile example
FROM php:8.1-apache
RUN docker-php-ext-install pdo pdo_mysql gd
COPY . /var/www/html/
EXPOSE 80
```

## 🔧 Troubleshooting

### Common Issues

#### Database Connection Issues
```php
// Check database connection in config/config.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'fingerling_marketplace');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

#### Map Not Loading
1. Check internet connection for tile loading
2. Verify Leaflet.js CDN is accessible
3. Check browser console for JavaScript errors
4. Ensure proper HTTPS for geolocation features

#### Location Features Not Working
1. **GPS Issues**: Ensure HTTPS for geolocation API
2. **Geocoding Issues**: Check Nominatim API accessibility
3. **Address Auto-fill**: Verify reverse geocoding function
4. **Map Markers**: Check marker icon URLs

#### File Upload Issues
```php
// Check upload directory permissions
chmod 777 uploads/
// Verify PHP upload settings
upload_max_filesize = 10M
post_max_size = 10M
```

### Debug Mode
```php
// Enable debug mode in config/config.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

### Performance Issues
1. **Database Optimization**: Add indexes to frequently queried columns
2. **Image Optimization**: Compress uploaded images
3. **Caching**: Implement Redis or Memcached
4. **CDN**: Use CDN for static assets

## 📈 Performance Optimization

### Database Optimization
- **Indexes**: Proper indexing on user_id, supplier_id, order_id columns
- **Query Optimization**: Use EXPLAIN to analyze slow queries
- **Connection Pooling**: Implement connection pooling for high traffic
- **Caching**: Cache frequently accessed data

### Frontend Optimization
- **Image Compression**: Automatic image compression on upload
- **Lazy Loading**: Implement lazy loading for product images
- **Minification**: Minify CSS and JavaScript files
- **CDN Integration**: Load external libraries from CDN
- **Browser Caching**: Set proper cache headers for static assets

### Server Optimization
- **PHP OPcache**: Enable PHP OPcache for better performance
- **Gzip Compression**: Enable gzip compression on web server
- **HTTP/2**: Use HTTP/2 for improved loading speeds
- **SSL/TLS**: Implement HTTPS for security and performance

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 👨‍💻 Author

**Your Name**
- GitHub: [@yourusername](https://github.com/yourusername)
- Email: your.email@example.com

## 🙏 Acknowledgments

- OpenStreetMap for mapping services
- Nominatim for geocoding services
- Bootstrap team for the CSS framework
- Leaflet.js team for the mapping library
- Font Awesome for the icon library

---

**🐟 Built with ❤️ for the Philippine aquaculture industry**
#   F i n g e r l i n g - O n l i n e - O r d e r i n g - P l a t f o r m  
 #   F i n g e r l i n g - O n l i n e - O r d e r i n g - P l a t f o r m  
 #   F i n g e r l i n g - O n l i n e - O r d e r i n g - P l a t f o r m  
 