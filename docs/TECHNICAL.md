# Technical Documentation

## 🏗 Architecture Overview

The Fingerling Trading Platform follows a modular MVC-inspired architecture with clear separation of concerns.

### Directory Structure

```
PLATFORM/
├── admin/              # Admin panel pages
├── supplier/           # Supplier panel pages  
├── customer/           # Customer panel pages
├── auth/               # Authentication pages
├── api/                # API endpoints
├── assets/             # Static assets (CSS, JS, images)
├── classes/            # PHP classes and models
├── config/             # Configuration files
├── includes/           # Shared includes (headers, footers)
├── uploads/            # File upload directory
└── docs/               # Documentation
```

## 🗄 Database Schema

### Core Tables

#### Users
- `id` (Primary Key)
- `email` (Unique)
- `password_hash`
- `user_type` (admin, supplier, customer)
- `first_name`, `last_name`
- `phone`
- `status` (active, inactive, pending)
- `created_at`, `updated_at`

#### Suppliers
- `id` (Primary Key)
- `user_id` (Foreign Key → users.id)
- `business_name`
- `owner_name`
- `business_type`
- `registration_number`
- `contact_number`
- `address` (barangay, city, province)
- `coordinates` (latitude, longitude)
- `status` (pending, approved, rejected)
- `description`

#### Customers
- `id` (Primary Key)
- `user_id` (Foreign Key → users.id)
- `address` (barangay, city, province, postal_code)

#### Species
- `id` (Primary Key)
- `species_name`
- `scientific_name`
- `category`
- `description`
- `image_url`

#### Inventory
- `id` (Primary Key)
- `supplier_id` (Foreign Key → suppliers.id)
- `species_id` (Foreign Key → species.id)
- `size_category`
- `price_per_piece`
- `stock_quantity`
- `minimum_order`
- `availability_status`
- `specifications`

#### Orders
- `id` (Primary Key)
- `customer_id` (Foreign Key → customers.id)
- `supplier_id` (Foreign Key → suppliers.id)
- `order_number` (Unique)
- `status` (pending, confirmed, preparing, ready, out_for_delivery, delivered, cancelled)
- `total_amount`
- `delivery_address`
- `delivery_date`
- `delivery_notes`
- `payment_status`
- `created_at`, `updated_at`

#### Order Items
- `id` (Primary Key)
- `order_id` (Foreign Key → orders.id)
- `inventory_id` (Foreign Key → inventory.id)
- `quantity`
- `price_per_piece`
- `subtotal`

#### Cart
- `id` (Primary Key)
- `customer_id` (Foreign Key → customers.id)
- `product_id` (Foreign Key → inventory.id)
- `quantity`
- `price_per_piece`
- `added_at`, `updated_at`

## 🔧 PHP Classes

### Core Classes

#### User.php
- User authentication and management
- Profile operations
- Password handling
- Session management

#### Admin.php
- Administrative functions
- User approval/rejection
- System analytics
- Report generation

#### Supplier.php
- Supplier-specific operations
- Business profile management
- Inventory operations
- Order processing

#### Customer.php
- Customer-specific operations
- Profile management
- Order history
- Cart operations

#### Cart.php
- Shopping cart functionality
- Item management
- Price calculations
- Validation

#### Order.php
- Order processing
- Status management
- Payment tracking
- Delivery coordination

#### Product.php
- Product/inventory management
- Species information
- Stock tracking
- Price management

## 🎨 Frontend Architecture

### CSS Framework
- **Modern Framework**: Custom CSS framework
- **Role Themes**: Specific styling for each user role
- **Dark Mode**: Complete dark theme support
- **Responsive**: Mobile-first design approach

### JavaScript Components
- **Modern Sidebar**: Collapsible navigation
- **Dark Mode Toggle**: Theme switching
- **Form Validation**: Client-side validation
- **AJAX Operations**: Asynchronous requests
- **Map Integration**: Leaflet.js for location features

### Key CSS Files
- `modern-framework.css` - Core framework
- `role-themes.css` - Role-specific themes
- `modern-sidebar.css` - Navigation styling
- `modern-dashboard.css` - Dashboard layouts
- `dark-mode.css` - Dark theme styles

### Key JavaScript Files
- `modern-sidebar.js` - Navigation functionality
- `dark-mode.js` - Theme switching
- `form-validation.js` - Form handling
- `maps.js` - Location features

## 🔐 Security Implementation

### Authentication
- Password hashing using PHP's `password_hash()`
- Session-based authentication
- Role-based access control
- Automatic session timeout

### Data Protection
- Prepared statements for SQL queries
- Input validation and sanitization
- Output escaping to prevent XSS
- CSRF protection on forms

### File Upload Security
- File type validation
- Size restrictions
- Secure upload directory
- Filename sanitization

## 📊 API Endpoints

### Cart API (`/api/cart/`)
- `update.php` - Update cart items
- `remove.php` - Remove cart items
- `clear.php` - Clear entire cart

### Order API (`/api/orders/`)
- `create.php` - Create new order
- `update.php` - Update order status
- `cancel.php` - Cancel order

### Product API (`/api/products/`)
- `search.php` - Product search
- `filter.php` - Product filtering
- `details.php` - Product details

## 🚀 Performance Optimizations

### Database
- Indexed columns for fast queries
- Optimized JOIN operations
- Connection pooling
- Query caching

### Frontend
- Minified CSS and JavaScript
- Image optimization
- Lazy loading for images
- Efficient DOM manipulation

### Caching
- Browser caching headers
- Static asset versioning
- Database query optimization

## 🔄 Development Workflow

### Code Standards
- PSR-4 autoloading
- Consistent naming conventions
- Comprehensive commenting
- Error handling

### Testing
- Unit tests for core functions
- Integration testing
- Browser compatibility testing
- Performance testing

### Deployment
- Environment-specific configurations
- Database migration scripts
- Asset compilation
- Security hardening

## 📱 Mobile Optimization

### Responsive Design
- Mobile-first CSS approach
- Touch-friendly interfaces
- Optimized images for mobile
- Fast loading on slow connections

### Progressive Web App Features
- Offline functionality (planned)
- Push notifications (planned)
- App-like experience
- Home screen installation

## 🔧 Configuration Options

### Application Settings
- Database connection parameters
- File upload limits
- Email configuration
- Security settings

### Feature Toggles
- Dark mode availability
- Map integration
- Payment methods
- Notification systems

---

This technical documentation provides a comprehensive overview of the platform's architecture, implementation details, and development guidelines.
