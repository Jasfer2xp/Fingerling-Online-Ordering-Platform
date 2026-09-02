# API Documentation

## 🌐 Overview

The Fingerling Trading Platform provides RESTful API endpoints for various operations. All API endpoints return JSON responses and require proper authentication.

## 🔐 Authentication

Most API endpoints require user authentication. Include session cookies or authentication headers with your requests.

### Authentication Methods
- **Session-based**: Use existing login session
- **Token-based**: API tokens (future implementation)

## 📋 Response Format

All API responses follow this standard format:

```json
{
    "success": true|false,
    "message": "Response message",
    "data": {}, // Response data (if applicable)
    "error": "Error details" // Only present on errors
}
```

## 🛒 Cart API

### Add to Cart
**Endpoint**: `POST /customer/add-to-cart.php`

**Request Body**:
```json
{
    "product_id": 123,
    "quantity": 2
}
```

**Response**:
```json
{
    "success": true,
    "message": "Product added to cart successfully",
    "action": "added",
    "quantity": 2
}
```

### Update Cart Item
**Endpoint**: `POST /api/cart/update.php`

**Request Body**:
```json
{
    "product_id": 123,
    "quantity": 3
}
```

**Response**:
```json
{
    "success": true,
    "message": "Cart updated successfully",
    "new_quantity": 3
}
```

### Remove from Cart
**Endpoint**: `POST /api/cart/remove.php`

**Request Body**:
```json
{
    "product_id": 123
}
```

**Response**:
```json
{
    "success": true,
    "message": "Item removed from cart"
}
```

### Clear Cart
**Endpoint**: `POST /api/cart/clear.php`

**Response**:
```json
{
    "success": true,
    "message": "Cart cleared successfully"
}
```

## 📦 Product API

### Search Products
**Endpoint**: `GET /api/products/search.php`

**Parameters**:
- `q` (string): Search query
- `category` (string): Product category
- `min_price` (float): Minimum price
- `max_price` (float): Maximum price
- `supplier_id` (int): Specific supplier
- `limit` (int): Results limit (default: 20)
- `offset` (int): Results offset (default: 0)

**Example**:
```
GET /api/products/search.php?q=tilapia&min_price=10&max_price=50&limit=10
```

**Response**:
```json
{
    "success": true,
    "data": {
        "products": [
            {
                "id": 123,
                "species_name": "Nile Tilapia",
                "scientific_name": "Oreochromis niloticus",
                "price_per_piece": 25.00,
                "stock_quantity": 1000,
                "supplier": {
                    "id": 45,
                    "business_name": "Aqua Farm Inc.",
                    "location": "Laguna, Philippines"
                }
            }
        ],
        "total": 1,
        "limit": 10,
        "offset": 0
    }
}
```

### Get Product Details
**Endpoint**: `GET /api/products/details.php`

**Parameters**:
- `id` (int): Product ID

**Response**:
```json
{
    "success": true,
    "data": {
        "id": 123,
        "species_name": "Nile Tilapia",
        "scientific_name": "Oreochromis niloticus",
        "category": "Freshwater",
        "size_category": "Fingerling",
        "price_per_piece": 25.00,
        "stock_quantity": 1000,
        "minimum_order": 100,
        "specifications": "High-quality fingerlings...",
        "image_url": "/uploads/products/tilapia.jpg",
        "supplier": {
            "id": 45,
            "business_name": "Aqua Farm Inc.",
            "owner_name": "John Doe",
            "contact_number": "+63912345678",
            "location": "Laguna, Philippines",
            "rating": 4.5
        }
    }
}
```

## 📋 Order API

### Create Order
**Endpoint**: `POST /api/orders/create.php`

**Request Body**:
```json
{
    "delivery_address": {
        "barangay": "Barangay 1",
        "city": "Los Baños",
        "province": "Laguna",
        "postal_code": "4030"
    },
    "delivery_notes": "Please call before delivery",
    "payment_method": "cash_on_delivery"
}
```

**Response**:
```json
{
    "success": true,
    "message": "Order placed successfully",
    "data": {
        "order_id": 789,
        "order_number": "ORD-2025-001",
        "total_amount": 2500.00,
        "estimated_delivery": "2025-01-30"
    }
}
```

### Update Order Status
**Endpoint**: `POST /api/orders/update.php`

**Request Body**:
```json
{
    "order_id": 789,
    "status": "confirmed",
    "notes": "Order confirmed and being prepared"
}
```

**Response**:
```json
{
    "success": true,
    "message": "Order status updated successfully"
}
```

### Cancel Order
**Endpoint**: `POST /api/orders/cancel.php`

**Request Body**:
```json
{
    "order_id": 789,
    "reason": "Customer requested cancellation"
}
```

**Response**:
```json
{
    "success": true,
    "message": "Order cancelled successfully"
}
```

## 👥 User API

### Get User Profile
**Endpoint**: `GET /api/user/profile.php`

**Response**:
```json
{
    "success": true,
    "data": {
        "id": 123,
        "email": "customer@example.com",
        "first_name": "Jane",
        "last_name": "Doe",
        "phone": "+63912345678",
        "user_type": "customer",
        "profile": {
            "barangay": "Barangay 1",
            "city": "Los Baños",
            "province": "Laguna",
            "postal_code": "4030"
        }
    }
}
```

### Update Profile
**Endpoint**: `POST /api/user/profile.php`

**Request Body**:
```json
{
    "first_name": "Jane",
    "last_name": "Smith",
    "phone": "+63987654321",
    "barangay": "Barangay 2",
    "city": "Los Baños",
    "province": "Laguna",
    "postal_code": "4030"
}
```

**Response**:
```json
{
    "success": true,
    "message": "Profile updated successfully"
}
```

## 🏪 Supplier API

### Get Suppliers
**Endpoint**: `GET /api/suppliers/list.php`

**Parameters**:
- `location` (string): Filter by location
- `rating` (float): Minimum rating
- `limit` (int): Results limit
- `offset` (int): Results offset

**Response**:
```json
{
    "success": true,
    "data": {
        "suppliers": [
            {
                "id": 45,
                "business_name": "Aqua Farm Inc.",
                "owner_name": "John Doe",
                "location": "Laguna, Philippines",
                "rating": 4.5,
                "total_reviews": 25,
                "product_count": 15,
                "coordinates": {
                    "latitude": 14.1693,
                    "longitude": 121.2416
                }
            }
        ],
        "total": 1
    }
}
```

### Get Supplier Details
**Endpoint**: `GET /api/suppliers/details.php`

**Parameters**:
- `id` (int): Supplier ID

**Response**:
```json
{
    "success": true,
    "data": {
        "id": 45,
        "business_name": "Aqua Farm Inc.",
        "owner_name": "John Doe",
        "business_type": "Fish Farm",
        "contact_number": "+63912345678",
        "location": "Laguna, Philippines",
        "description": "Premium quality fingerlings...",
        "rating": 4.5,
        "total_reviews": 25,
        "product_count": 15,
        "recent_reviews": [
            {
                "customer_name": "Jane Doe",
                "rating": 5,
                "comment": "Excellent quality fish!",
                "date": "2025-01-20"
            }
        ]
    }
}
```

## 📊 Analytics API

### Get Dashboard Stats
**Endpoint**: `GET /api/analytics/dashboard.php`

**Response**:
```json
{
    "success": true,
    "data": {
        "total_orders": 150,
        "total_revenue": 75000.00,
        "active_customers": 45,
        "pending_orders": 8,
        "recent_activity": [
            {
                "type": "order",
                "message": "New order from Jane Doe",
                "timestamp": "2025-01-25 10:30:00"
            }
        ]
    }
}
```

## ❌ Error Codes

### HTTP Status Codes
- `200` - Success
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `500` - Internal Server Error

### Application Error Codes
- `AUTH_REQUIRED` - Authentication required
- `INVALID_INPUT` - Invalid input data
- `NOT_FOUND` - Resource not found
- `INSUFFICIENT_STOCK` - Not enough stock
- `ORDER_CANCELLED` - Order already cancelled
- `PERMISSION_DENIED` - Insufficient permissions

## 🔧 Rate Limiting

API endpoints are rate-limited to prevent abuse:
- **General**: 100 requests per minute
- **Search**: 50 requests per minute
- **Cart Operations**: 30 requests per minute

## 📝 Examples

### JavaScript (Fetch API)
```javascript
// Add to cart
fetch('/customer/add-to-cart.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({
        product_id: 123,
        quantity: 2
    })
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        console.log('Added to cart:', data.message);
    } else {
        console.error('Error:', data.message);
    }
});
```

### PHP (cURL)
```php
$data = json_encode([
    'product_id' => 123,
    'quantity' => 2
]);

$ch = curl_init('/customer/add-to-cart.php');
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$result = json_decode($response, true);

if ($result['success']) {
    echo "Success: " . $result['message'];
} else {
    echo "Error: " . $result['message'];
}
```

---

**Need help?** Contact the development team for additional API support or custom endpoints.
