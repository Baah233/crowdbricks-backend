# Wallet System Implementation

## Overview
Complete wallet system with deposit, withdrawal, balance tracking, and transaction history.

## Backend Implementation

### Database Tables

#### `wallets`
- `id` - Primary key
- `user_id` - Foreign key to users table
- `balance` - Decimal (15,2), default 0.00
- `currency` - VARCHAR, default 'GHS'
- `status` - Enum (active, suspended, closed)
- `created_at`, `updated_at`

#### `wallet_transactions`
- `id` - Primary key
- `wallet_id` - Foreign key to wallets
- `user_id` - Foreign key to users
- `type` - Enum (deposit, withdrawal, investment, return, refund)
- `amount` - Decimal (15,2)
- `balance_before` - Decimal (15,2)
- `balance_after` - Decimal (15,2)
- `reference` - Unique transaction reference
- `status` - Enum (pending, completed, failed, cancelled)
- `payment_method` - VARCHAR (momo, card, bank_transfer)
- `payment_reference` - External payment reference
- `metadata` - JSON (additional payment details)
- `notes` - Text field
- `created_at`, `updated_at`

### Models

#### `Wallet.php`
- **Relationships:**
  - `belongsTo(User::class)`
  - `hasMany(WalletTransaction::class)`

- **Methods:**
  - `credit($amount, $type, $metadata)` - Adds funds to wallet
  - `debit($amount, $type, $metadata)` - Removes funds (checks balance)

#### `WalletTransaction.php`
- **Relationships:**
  - `belongsTo(Wallet::class)`
  - `belongsTo(User::class)`

- **Casts:**
  - `metadata` → array
  - Amounts → decimal:2

#### `User.php`
- **Added Relationships:**
  - `wallet()` - hasOne relationship
  - `walletTransactions()` - hasMany relationship

### API Endpoints

All routes are under `auth:sanctum` middleware and prefixed with `/api/v1/wallet`

#### GET `/wallet`
**Response:**
```json
{
  "success": true,
  "wallet": {
    "id": 1,
    "user_id": 1,
    "balance": "500.00",
    "currency": "GHS",
    "status": "active"
  }
}
```

#### POST `/wallet/deposit`
**Request:**
```json
{
  "amount": 1000.00,
  "payment_method": "momo"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Deposit successful",
  "transaction": {
    "id": 1,
    "reference": "DEP-ABC123XYZ",
    "amount": "1000.00",
    "status": "completed"
  },
  "wallet": {
    "balance": "1500.00"
  }
}
```

#### POST `/wallet/withdraw`
**Request:**
```json
{
  "amount": 500.00,
  "payment_method": "momo",
  "account_details": "0241234567"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Withdrawal request submitted",
  "transaction": {
    "id": 2,
    "reference": "WTH-XYZ789ABC",
    "amount": "500.00",
    "status": "completed"
  },
  "wallet": {
    "balance": "1000.00"
  }
}
```

#### GET `/wallet/transactions?page=1`
**Response:**
```json
{
  "success": true,
  "transactions": {
    "current_page": 1,
    "data": [
      {
        "id": 2,
        "type": "withdrawal",
        "amount": "500.00",
        "balance_before": "1500.00",
        "balance_after": "1000.00",
        "reference": "WTH-XYZ789ABC",
        "status": "completed",
        "payment_method": "momo",
        "created_at": "2025-11-06T16:52:22.000000Z"
      },
      {
        "id": 1,
        "type": "deposit",
        "amount": "1000.00",
        "balance_before": "500.00",
        "balance_after": "1500.00",
        "reference": "DEP-ABC123XYZ",
        "status": "completed",
        "payment_method": "momo",
        "created_at": "2025-11-06T16:52:08.000000Z"
      }
    ],
    "per_page": 20,
    "total": 2
  }
}
```

## Frontend Implementation

### InvestorDashboard.jsx Changes

#### State Variables Added
```javascript
const [wallet, setWallet] = useState(null);
const [showDepositModal, setShowDepositModal] = useState(false);
const [showWithdrawModal, setShowWithdrawModal] = useState(false);
const [depositAmount, setDepositAmount] = useState("");
const [withdrawAmount, setWithdrawAmount] = useState("");
const [paymentMethod, setPaymentMethod] = useState("momo");
const [accountDetails, setAccountDetails] = useState("");
const [walletLoading, setWalletLoading] = useState(false);
```

#### Data Fetching
- Wallet is fetched in `refreshAll()` function alongside other dashboard data
- Auto-refreshes on window focus
- Created automatically on first API call if doesn't exist

#### Features Implemented

1. **Wallet Balance Display Card**
   - Shows current balance (₵XXX format)
   - Shows wallet status
   - Withdraw button (disabled if balance = 0)
   - Deposit button

2. **Deposit Modal**
   - Amount input (minimum ₵1)
   - Payment method selector (Mobile Money, Card, Bank Transfer)
   - Form validation
   - Loading state during processing
   - Success feedback with alert

3. **Withdraw Modal**
   - Shows available balance
   - Amount input with max validation
   - Payment method selector (Mobile Money, Bank Transfer)
   - Account details input (phone number for momo, account info for bank)
   - Insufficient balance check
   - Form validation
   - Loading state during processing
   - Success feedback with alert

#### Handler Functions

**handleDeposit():**
- Validates amount (min ₵1)
- Calls `/wallet/deposit` API
- Refreshes wallet balance
- Shows success/error alerts
- Resets form and closes modal

**handleWithdraw():**
- Validates amount (min ₵1, max = wallet balance)
- Validates account details provided
- Calls `/wallet/withdraw` API
- Refreshes wallet balance
- Shows success/error alerts
- Resets form and closes modal

## Payment Gateway Integration

⚠️ **IMPORTANT:** Currently using placeholder payment integration.

### Current Behavior
- Deposits are instantly approved
- Withdrawals are instantly processed
- No actual payment gateway integration

### TODO: Integrate Real Payment Gateway
Replace placeholder code in `WalletController.php`:

```php
// In deposit() method - around line 60
// TODO: Integrate with real payment gateway
// For now, we'll simulate instant approval
// In production, this should:
// 1. Initialize payment with gateway (Paystack, Flutterwave, etc.)
// 2. Return payment URL/reference to frontend
// 3. Wait for webhook confirmation before crediting wallet

// In withdraw() method - around line 120
// TODO: Integrate with real payout gateway
// For now, we'll process immediately
// In production, this should:
// 1. Submit payout request to gateway
// 2. Wait for confirmation
// 3. Update transaction status based on gateway response
```

### Recommended Payment Gateways for Ghana
- **Paystack** - Supports Mobile Money, Cards
- **Flutterwave** - Supports Mobile Money, Cards, Bank Transfer
- **Momo API** - Direct MTN/Vodafone/AirtelTigo integration

## Testing

### Backend Tests (via Tinker)
```php
// Test deposit
$user = \App\Models\User::first();
$wallet = $user->wallet;
$tx = $wallet->credit(1000, 'deposit', ['payment_method' => 'momo']);

// Test withdrawal
$tx = $wallet->debit(500, 'withdrawal', ['payment_method' => 'momo', 'account_details' => '0241234567']);

// Check transactions
$user->walletTransactions;
```

### Frontend Testing
1. Login as investor
2. Navigate to Investor Dashboard
3. Check wallet balance card displays correctly
4. Click "Deposit" button
5. Enter amount and select payment method
6. Submit - verify balance updates
7. Click "Withdraw" button
8. Enter amount, select method, provide account details
9. Submit - verify balance decreases
10. Check browser console for any errors

## Security Considerations

✅ **Implemented:**
- Database transactions for atomic operations
- Balance validation before withdrawal
- Amount validation (min ₵1)
- User authentication required (auth:sanctum)
- Balance tracking with before/after amounts for audit

⚠️ **TODO:**
- Rate limiting for deposit/withdraw endpoints
- Two-factor authentication for withdrawals
- Webhook signature verification for payment gateways
- Admin approval workflow for large withdrawals
- Fraud detection rules

## Database Verification

Check wallet exists:
```sql
SELECT * FROM wallets WHERE user_id = 1;
```

Check transactions:
```sql
SELECT * FROM wallet_transactions WHERE user_id = 1 ORDER BY created_at DESC;
```

Verify balance matches transactions:
```sql
SELECT 
    w.balance as current_balance,
    COALESCE(SUM(CASE WHEN wt.type = 'deposit' THEN wt.amount ELSE 0 END), 0) as total_deposits,
    COALESCE(SUM(CASE WHEN wt.type = 'withdrawal' THEN wt.amount ELSE 0 END), 0) as total_withdrawals
FROM wallets w
LEFT JOIN wallet_transactions wt ON w.id = wt.wallet_id
WHERE w.user_id = 1
GROUP BY w.id, w.balance;
```

## Future Enhancements

1. **Investment Integration**
   - Deduct from wallet when investing in projects
   - Credit wallet when receiving returns
   - Show investment transactions in wallet history

2. **Transaction History Tab**
   - Add "Transactions" tab in dashboard
   - Paginated transaction list
   - Filter by type (deposit/withdrawal/investment/return)
   - Export to CSV

3. **Notifications**
   - Email notification on successful deposit
   - SMS notification on withdrawal
   - Push notifications for transaction status

4. **Admin Features**
   - View all user wallets
   - Manual adjustments (with audit trail)
   - Withdrawal approval workflow
   - Fraud detection dashboard

5. **Analytics**
   - Total wallet balance across platform
   - Daily/Monthly transaction volumes
   - Average deposit/withdrawal amounts
   - Payment method usage statistics
