# Fingerling Online Ordering Platform

Fingerling Online Ordering Platform is a web application for the Philippine aquaculture sector. It helps fish farmers find and order fingerlings from suppliers, while giving suppliers and administrators the tools to manage the marketplace.

## What the platform is for

The platform brings the fingerling buying process into one place. Customers can discover available fingerlings, compare suppliers, place orders, and follow their order status. Suppliers can publish their stock, manage orders, and maintain their business location. Administrators oversee users, inventory data, reports, and platform activity.

## How it works

```text
Supplier registers and lists inventory
        |
        v
Customer browses species and supplier information
        |
        v
Customer adds items to the cart and places an order
        |
        v
Supplier confirms and prepares the order
        |
        v
Delivery and order status updates are recorded
        |
        v
Customer reviews the completed order
```

## User roles

### Customers

- Create an account and browse available fingerlings.
- Search products and view supplier information.
- Add products to a cart, place orders, and track delivery progress.
- View order history and leave supplier feedback.

### Suppliers

- Register a supplier account and provide business-location details.
- Add and update fingerling inventory, stock, pricing, and product images.
- Review incoming orders, confirm fulfillment, and record delivery proof.
- View sales, revenue, inventory, and customer-related insights.

### Administrators

- Review and manage customer, supplier, and administrator accounts.
- Maintain species, categories, announcements, and platform content.
- Monitor orders, payments, feedback, reports, and system activity.
- Review supplier locations and approval requests.

## Main capabilities

- Role-based access for customers, suppliers, and administrators.
- Fingerling catalog, product search, cart, checkout, and order tracking.
- Supplier inventory and order-management tools.
- Location-aware supplier information and map-based discovery.
- Payment workflow support and order-payment status tracking.
- Notifications, feedback, reporting, analytics, and audit features.
- Responsive interface for desktop and mobile browsers.

## Technology

- PHP and MySQL
- Bootstrap, JavaScript, and CSS
- Leaflet/OpenStreetMap-based map features
- PHPMailer for application email notifications

## Local setup

1. Install a PHP-compatible web server and MySQL.
2. Copy `.env.example` to `.env`.
3. Set the database and optional service credentials in `.env` only.
4. Import the required database schema/migrations for your environment.
5. Configure your web server to serve this project, then open the application in a browser.

## Configuration and security

All credentials, API keys, OAuth values, database passwords, and payment-service settings belong in the local `.env` file. The file is intentionally excluded from Git.

- Never commit `.env`.
- Use `.env.example` only as a list of required setting names and safe placeholders.
- Create separate credentials for development and production.
- Rotate a credential immediately if it is ever committed or shared publicly.

## Project directories

```text
admin/       Administrator portal
api/         Application endpoints
auth/        Authentication and account flows
classes/     Domain classes and business logic
config/      Configuration loaders (no credentials)
customer/    Customer portal
database/    Database-related files and migrations
includes/    Shared application components
supplier/    Supplier portal
uploads/     Runtime user-upload directory (not version-controlled)
```

## License

This project is licensed under the MIT License. See [LICENSE](LICENSE) when available.
