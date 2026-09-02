# Supplier Withdrawal System

## Overview

The withdrawal system allows suppliers to request payouts for their earnings and provides administrators with tools to process these requests. It also includes an appeals mechanism for suppliers who do not receive their payouts.

## Database Structure

### withdrawals Table

| Column | Type | Description |
|--------|------|-------------|
| id | int(11) AUTO_INCREMENT | Primary key |
| supplier_id | int(11) | Foreign key to suppliers table |
| amount | decimal(10,2) | Withdrawal amount |
| method | enum('gcash','paypal') | Withdrawal method |
| account_info | varchar(255) | Account information (GCash number or PayPal email) |
| status | enum('pending','completed','cancelled') | Withdrawal status |
| created_at | timestamp | Creation timestamp |
| updated_at | timestamp | Last update timestamp |

### withdrawal_appeals Table

| Column | Type | Description |
|--------|------|-------------|
| id | int(11) AUTO_INCREMENT | Primary key |
| withdrawal_id | int(11) | Foreign key to withdrawals table |
| supplier_id | int(11) | Foreign key to suppliers table |
| reason | text | Reason for appeal |
| proof_file | varchar(255) | Optional proof file path |
| status | enum('pending','approved','rejected') | Appeal status |
| created_at | timestamp | Creation timestamp |
| updated_at | timestamp | Last update timestamp |

## Workflow

### 1. Supplier Withdrawal Request

1. Supplier navigates to "Withdraw Earnings" page
2. Views available earnings from completed orders
3. Submits withdrawal request with:
   - Amount
   - Method (GCash or PayPal)
4. System validates request and creates pending withdrawal

### 2. Admin Processing

1. Admin views pending withdrawals in "Pending Withdrawals" page
2. Verifies payout details manually:
   - For valid details: Sends payout manually and marks as completed
   - For invalid details: Marks as cancelled with reason

### 3. Supplier Appeal

1. If supplier doesn't receive payout but withdrawal is marked completed:
   - Supplier submits appeal with reason
   - Appeal appears in admin "Withdrawal Appeals" page

### 4. Admin Appeal Review

1. Admin reviews appeal:
   - If valid: Approves appeal and processes payout manually
   - If invalid: Rejects appeal

## Files Created

### Supplier Side
- `supplier/withdraw.php` - Withdrawal request and history page

### Admin Side
- `admin/withdrawals.php` - Pending withdrawals management
- `admin/withdrawal-appeals.php` - Withdrawal appeals management

### Database
- `database/migrations/create_withdrawal_tables.sql` - SQL migration for new tables

### UI Components
- Added "Withdraw Earnings" link to supplier sidebar
- Added "Pending Withdrawals" and "Withdrawal Appeals" links to admin sidebar

## Implementation Notes

1. All withdrawal amounts are validated against supplier's actual earnings
2. GCash numbers are taken from supplier's contact_number field
3. PayPal emails are taken from supplier's associated user email
4. Withdrawal processing is manual - admins must send payouts outside the system
5. All actions are logged through updated_at timestamps
6. Proper foreign key constraints ensure data integrity