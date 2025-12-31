# ToyyibPay Payment Integration Documentation

## Overview

This project integrates with **ToyyibPay SANDBOX** for payment processing. ToyyibPay is a Malaysian payment gateway that supports various payment methods including FPX, credit/debit cards, and e-wallets.

## Integration Type

- **Environment**: SANDBOX (Testing)
- **API Endpoint**: `https://dev.toyyibpay.com/index.php/api/createBill`
- **Payment Page**: `https://dev.toyyibpay.com/{BillCode}`

## File Structure

### 1. `sandbox-test.php`
**Purpose**: Test file to create a bill and redirect user to ToyyibPay payment page.

**Functionality**:
- Creates a bill via ToyyibPay API
- Generates unique bill reference number
- Sets bill amount to RM1.00 (100 sen)
- Redirects user to ToyyibPay payment page after receiving BillCode

**Configuration**:
```php
define('SANDBOX_SECRET_KEY', 'dh1kldj2-j8db-ofm0-qauv-e037egz4oiui');
define('CATEGORY_CODE', 'g3ii2ms2');
```

**Bill Parameters**:
- `billAmount`: 100 (RM1.00 in sen)
- `billPriceSetting`: 1 (Fixed amount)
- `billPayorInfo`: 1 (Collect payer information)
- `billExternalReferenceNo`: Auto-generated unique reference

**Flow**:
1. Generate unique bill reference
2. Prepare bill data array
3. Send POST request to ToyyibPay API
4. Parse JSON response
5. Extract BillCode
6. Redirect to payment page

### 2. `callback.php`
**Purpose**: Server-to-server callback handler for payment notifications.

**Functionality**:
- Receives POST data from ToyyibPay after payment status changes
- Logs all callback data to `toyyibpay_callback.log`
- Returns HTTP 200 to acknowledge receipt
- **Important**: This is the source of truth for payment confirmation

**Log Format**:
```
[YYYY-MM-DD HH:MM:SS] POST Data: {JSON payload}
```

**Key Points**:
- No HTML output
- No user redirects
- Always returns HTTP 200
- Logs stored in same directory as callback.php
- Timezone: Asia/Kuala_Lumpur

**Security Note**: 
- Callback should be treated as the authoritative source for payment status
- Do NOT rely on return URL parameters for payment validation
- Implement IP whitelisting in production

### 3. `return.php`
**Purpose**: User-facing return page after payment completion.

**Functionality**:
- Displays payment status message to user
- Reads `status_id` from GET parameter
- Simple text-only display

**Status Codes**:
- `1` → Payment Successful 🎉
- `2` → Payment Pending ⏳
- `3` → Payment Failed ❌

**Important Notes**:
- This page is for user experience only
- Do NOT update database here
- Do NOT validate payment here
- Status should be verified via callback.php

## Payment Flow

```
1. User initiates payment
   ↓
2. sandbox-test.php creates bill via API
   ↓
3. User redirected to ToyyibPay payment page
   ↓
4. User completes payment on ToyyibPay
   ↓
5a. User redirected to return.php (status display)
   ↓
5b. ToyyibPay sends POST to callback.php (server-to-server)
   ↓
6. callback.php logs payment data
```

## API Integration Details

### Create Bill Endpoint
- **URL**: `https://dev.toyyibpay.com/index.php/api/createBill`
- **Method**: POST
- **Content-Type**: `application/x-www-form-urlencoded`
- **Response Format**: JSON array

### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `userSecretKey` | string | Yes | ToyyibPay secret key |
| `categoryCode` | string | Yes | Payment category code |
| `billName` | string | Yes | Bill name |
| `billDescription` | string | Yes | Bill description |
| `billPriceSetting` | int | Yes | 1 = Fixed, 2 = Multiple |
| `billPayorInfo` | int | Yes | 1 = Collect, 0 = Don't collect |
| `billAmount` | int | Yes | Amount in sen (RM1 = 100) |
| `billExternalReferenceNo` | string | Yes | Unique reference number |
| `billTo` | string | No | Payer name |
| `billEmail` | string | No | Payer email |
| `billPhone` | string | No | Payer phone |
| `billReturnUrl` | string | No | Return URL after payment |
| `billCallbackUrl` | string | No | Callback URL for notifications |
| `billSplitPayment` | int | No | Split payment flag |
| `billPaymentChannel` | int | No | Payment channel restriction |

### Response Format

**Success Response**:
```json
[
  {
    "BillCode": "abc123xyz",
    "BillName": "Test Bill",
    "BillDescription": "Sandbox Test Payment",
    "BillAmount": "100",
    "BillExternalReferenceNo": "TEST-1234567890-abc",
    "BillStatus": "1"
  }
]
```

**Error Response**:
```json
[
  {
    "Status": "Error",
    "Message": "Error description"
  }
]
```

## Callback Data Structure

ToyyibPay sends POST data to `callback.php` with the following structure (example):

```json
{
  "billcode": "abc123xyz",
  "order_id": "TEST-1234567890-abc",
  "status_id": "1",
  "status": "1",
  "msg": "success",
  "transaction_id": "TXN123456",
  "amount": "100"
}
```

**Status IDs**:
- `1` = Success
- `2` = Pending
- `3` = Failed

## Testing

### Test Bill Creation
1. Open `sandbox-test.php` in browser
2. File automatically creates bill and redirects to payment page
3. Use ToyyibPay test credentials to complete payment

### Test Callback
1. Configure `billCallbackUrl` in bill creation
2. Complete test payment
3. Check `toyyibpay_callback.log` for callback data

### Test Return URL
1. Configure `billReturnUrl` in bill creation
2. Complete test payment
3. User will be redirected to return.php with status_id

## Production Checklist

### Before Going Live

- [ ] Replace SANDBOX credentials with PRODUCTION credentials
- [ ] Update API endpoint to production: `https://toyyibpay.com/index.php/api/createBill`
- [ ] Update payment page URL to production: `https://toyyibpay.com/{BillCode}`
- [ ] Configure proper `billReturnUrl` in bill creation
- [ ] Configure proper `billCallbackUrl` in bill creation
- [ ] Implement IP whitelisting for callback.php
- [ ] Add callback signature verification (if available)
- [ ] Implement database updates in callback.php
- [ ] Add error handling and retry logic
- [ ] Set up log rotation for callback.log
- [ ] Test with real payment methods
- [ ] Implement proper security measures
- [ ] Add monitoring and alerting

### Security Considerations

1. **Callback Security**:
   - Verify callback source (IP whitelist)
   - Validate callback signature (if provided by ToyyibPay)
   - Use HTTPS for callback URL
   - Implement rate limiting

2. **Data Protection**:
   - Never log sensitive payment data in production
   - Encrypt sensitive data in logs
   - Follow PCI DSS compliance guidelines

3. **Error Handling**:
   - Log all errors for debugging
   - Implement retry mechanism for failed callbacks
   - Monitor callback failures

## Configuration

### Environment Variables (Recommended)

For production, use environment variables instead of hardcoded values:

```php
define('SANDBOX_SECRET_KEY', getenv('TOYYIBPAY_SECRET_KEY'));
define('CATEGORY_CODE', getenv('TOYYIBPAY_CATEGORY_CODE'));
```

### Return URL Configuration

Update `sandbox-test.php` to include return URL:

```php
'billReturnUrl' => 'https://yourdomain.com/return.php',
'billCallbackUrl' => 'https://yourdomain.com/callback.php',
```

## Troubleshooting

### Common Issues

1. **BillCode not returned**:
   - Check API credentials
   - Verify category code is valid
   - Check API response for error messages

2. **Callback not received**:
   - Verify callback URL is accessible
   - Check server logs for incoming requests
   - Ensure callback.php returns HTTP 200
   - Verify URL is HTTPS in production

3. **Payment status mismatch**:
   - Always use callback.php as source of truth
   - Do not rely on return.php status_id
   - Check callback.log for actual payment status

## Log Files

- **toyyibpay_callback.log**: Contains all callback data from ToyyibPay
  - Location: Same directory as callback.php
  - Format: Timestamp + JSON payload
  - Rotation: Implement log rotation in production

## Support & Resources

- **ToyyibPay Documentation**: https://toyyibpay.com/developers
- **SANDBOX Environment**: https://dev.toyyibpay.com
- **Support**: Contact ToyyibPay support for API issues

## Version History

- **v1.0** (Current): Initial SANDBOX integration
  - Basic bill creation
  - Callback logging
  - Return URL handling

