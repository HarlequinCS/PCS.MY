# E-Commerce Cart Flow Documentation

## Overview

This e-commerce system implements a shopping cart flow with ToyyibPay payment integration. Users can add multiple items to cart, review their order, and proceed to checkout.

## File Structure

```
order-baju.php    - Product selection page with cart functionality
checkout.php      - Checkout page with payment processing
return.php        - Payment return page (updated to clear cart)
callback.php      - Server-to-server payment callback (unchanged)
```

## Flow Diagram

```
┌─────────────────┐
│ order-baju.php  │
│  - Select items │
│  - Add to cart  │
│  - View cart    │
└────────┬────────┘
         │
         │ Click "Go to Checkout"
         ▼
┌─────────────────┐
│  checkout.php   │
│  - Review items │
│  - Enter info   │
│  - Process pay  │
└────────┬────────┘
         │
         │ Submit form
         │ → Save to DB
         │ → Call ToyyibPay API
         │
         ▼
┌─────────────────┐
│  ToyyibPay API  │
│  Payment page   │
└────────┬────────┘
         │
         ├─────────────────┐
         │                 │
         ▼                 ▼
┌─────────────────┐  ┌─────────────────┐
│  return.php     │  │  callback.php   │
│  - Show status  │  │  - Update DB    │
│  - Clear cart   │  │  - Log data     │
└─────────────────┘  └─────────────────┘
```

## Session Cart Structure

Cart items are stored in `$_SESSION['cart']` as an array:

```php
$_SESSION['cart'] = [
    [
        'id' => 'item_1234567890',
        'variant' => 'hitamdepan',
        'variant_name' => 'Hitam Depan',
        'variant_image' => 'images/hitamdepan.svg',
        'size' => 'M',
        'quantity' => 2,
        'price' => 50.00,
        'subtotal' => 100.00
    ],
    // ... more items
];
```

## Key Features

### order-baju.php

1. **Product Selection**
   - Select variant (radio buttons)
   - Select size (dropdown)
   - Enter quantity
   - Real-time price calculation

2. **Add to Cart**
   - Validates all fields
   - Creates unique item ID
   - Adds to session cart
   - Shows success message

3. **Floating Cart Button**
   - Shows item count badge
   - Fixed position (bottom right)
   - Click to open popup

4. **Cart Popup**
   - Lists all cart items
   - Shows item details (variant, size, quantity)
   - Shows subtotal per item
   - Remove item functionality (AJAX)
   - Shows total price
   - "Go to Checkout" button

5. **Remove Item**
   - AJAX request to remove item
   - Updates cart badge
   - Refreshes cart display

### checkout.php

1. **Order Summary**
   - Displays all cart items
   - Shows item count
   - Shows total price

2. **Customer Information Form**
   - Full Name (required)
   - Email (required, validated)
   - Phone (required)
   - Address (required, textarea)

3. **Payment Processing**
   - Validates all fields
   - Generates unique `billExternalReferenceNo`
   - Inserts each cart item to database
   - Calls ToyyibPay API
   - Redirects to payment page

4. **Database Insertion**
   - Each cart item = one order record
   - All items share same `billExternalReferenceNo`
   - Status set to 'pending'
   - User info stored with each item

### return.php

1. **Payment Status Display**
   - Shows success/failure message
   - Displays order details (if available)
   - Animated success button

2. **Cart Clearing**
   - Clears cart session on success
   - Prevents duplicate orders

## Database Structure

Each cart item becomes a separate order record:

```sql
INSERT INTO orders (
    name, email, phone, variant, size, quantity,
    amount, billExternalReferenceNo, status, created_at
) VALUES (
    'John Doe', 'john@example.com', '60123456789',
    'hitamdepan', 'M', 2, 100.00,
    'BILL-1234567890-abc', 'pending', NOW()
);
```

**Note:** Multiple items share the same `billExternalReferenceNo` but are stored as separate records.

## API Integration

### ToyyibPay Bill Creation

**Total Amount:** Sum of all cart item subtotals

**Bill Description:** 
```
"Baju Premium - Hitam Depan (Size: M, Qty: 2), Hijau Front (Size: L, Qty: 1)"
```

**Bill Data:**
- Single bill for entire cart
- Total amount in sen (RM × 100)
- All items linked via `billExternalReferenceNo`

## JavaScript Features

### order-baju.php

- Variant image preview
- Real-time price calculation
- Cart popup open/close
- Remove item via AJAX
- Cart badge update

### checkout.php

- Form validation
- Email format validation
- Loading state on submit

## Session Management

### Starting Session
```php
session_start();
```

### Initializing Cart
```php
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
```

### Adding to Cart
```php
$_SESSION['cart'][] = $cartItem;
```

### Removing from Cart
```php
$_SESSION['cart'] = array_filter($_SESSION['cart'], function($item) use ($itemId) {
    return $item['id'] !== $itemId;
});
$_SESSION['cart'] = array_values($_SESSION['cart']); // Re-index
```

### Clearing Cart
```php
unset($_SESSION['cart']);
$_SESSION['cart'] = [];
```

## Error Handling

### order-baju.php
- Validation errors displayed to user
- Success messages for cart operations
- AJAX error handling for remove item

### checkout.php
- Form validation errors
- Database errors (logged)
- API errors (logged and displayed)
- User-friendly error messages

## Security Considerations

1. **Input Validation**
   - All inputs validated
   - Email format checked
   - Quantity minimum enforced

2. **SQL Injection Prevention**
   - Prepared statements used
   - Parameter binding

3. **XSS Prevention**
   - All output escaped with `htmlspecialchars()`

4. **Session Security**
   - Session started on each page
   - Cart cleared after successful payment

## Testing Checklist

- [ ] Add single item to cart
- [ ] Add multiple items to cart
- [ ] View cart popup
- [ ] Remove item from cart
- [ ] Go to checkout with items
- [ ] Submit checkout form
- [ ] Verify database insertion
- [ ] Verify ToyyibPay redirect
- [ ] Complete payment
- [ ] Verify cart cleared on return
- [ ] Verify callback received

## Troubleshooting

### Cart not persisting
- Check session is started
- Verify session configuration
- Check browser cookies enabled

### Items not adding to cart
- Check form validation
- Verify POST action
- Check error messages

### Checkout not working
- Verify cart not empty
- Check form validation
- Verify database connection
- Check API credentials

### Cart not clearing
- Verify return.php session start
- Check status_id = 1 condition
- Verify cart unset code

## Future Enhancements

1. **Cart Persistence**
   - Save cart to database
   - Restore cart on login

2. **Cart Quantity Update**
   - Edit quantity in cart popup
   - Update subtotal

3. **Order History**
   - View past orders
   - Track order status

4. **Address Management**
   - Save multiple addresses
   - Address selection dropdown

5. **Coupon Codes**
   - Apply discount codes
   - Calculate discounted total

