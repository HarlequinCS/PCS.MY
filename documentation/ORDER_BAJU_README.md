# Order Baju Premium - ToyyibPay Staging Integration

## 📋 Overview
Halaman order untuk produk **Baju Premium** dengan integrasi pembayaran ToyyibPay staging. Produk ini mempunyai 3 variant warna/design dan 5 pilihan saiz (S, M, L, XL, XXL).

## 🗄️ Database Setup

### 1. Create Orders Table
Jalankan SQL script untuk membuat table `orders`:

```bash
mysql -u username -p database_name < database_orders.sql
```

Atau import melalui phpMyAdmin:
- Buka phpMyAdmin
- Pilih database
- Klik "Import"
- Pilih file `database_orders.sql`
- Klik "Go"

### 2. Table Structure
Table `orders` mengandungi kolum berikut:
- `id` - Auto increment primary key
- `name` - Nama user
- `email` - Email user
- `phone` - Nombor telefon
- `variant` - Variant dipilih (hitamdepan, hijaufront, hijaudepan)
- `size` - Saiz dipilih (S, M, L, XL, XXL)
- `quantity` - Kuantiti
- `amount` - Jumlah dalam RM
- `billExternalReferenceNo` - Unique reference untuk ToyyibPay
- `status` - Status order (default: 'pending')
- `created_at` - Timestamp

## 📁 Files

### 1. `database_orders.sql`
SQL script untuk create table `orders`.

### 2. `order-baju.php`
Halaman utama untuk order form dengan integrasi ToyyibPay.

**Features:**
- ✅ Form validation untuk semua fields
- ✅ Image preview untuk variant
- ✅ Auto-calculate total amount
- ✅ Save order ke database
- ✅ Integrate dengan ToyyibPay API
- ✅ Redirect ke payment page
- ✅ Responsive design
- ✅ Error handling

## ⚙️ Configuration

### 1. Database Connection
File `order-baju.php` menggunakan database connection dari `includes/db.php`. Pastikan:
- File `config/database.php` sudah dikonfigurasi dengan betul
- Database credentials adalah betul
- Table `orders` sudah dibuat

### 2. ToyyibPay Credentials
Update credentials di `order-baju.php`:

```php
define('STAGING_SECRET_KEY', 'your-secret-key');
define('STAGING_CATEGORY_CODE', 'your-category-code');
```

### 3. Base URL
Update base URL untuk staging environment:

```php
$baseUrl = 'https://staging.yourdomain.com'; // GANTIKAN dengan domain staging sebenar
```

### 4. Product Price
Update harga per baju jika perlu:

```php
define('PRICE_PER_SHIRT', 50.00); // RM50 per baju
```

### 5. Variant Images
Pastikan image files wujud di folder `images/`:
- `hitamdepan.svg` ✅
- `hijaufront.svg` ✅
- `hijaudepan.svg` ⚠️ (currently using hijaufront.svg as fallback)

## 🚀 Usage

### 1. Setup Database
```bash
# Import SQL
mysql -u username -p database_name < database_orders.sql
```

### 2. Configure Settings
Edit `order-baju.php` dan update:
- Database credentials (via `config/database.php`)
- ToyyibPay credentials
- Base URL
- Product price (if needed)

### 3. Access Order Page
Buka browser dan navigate ke:
```
https://staging.yourdomain.com/order-baju.php
```

## 📝 Order Flow

1. **User fills form:**
   - Pilih variant (hitamdepan, hijaufront, hijaudepan)
   - Pilih saiz (S, M, L, XL, XXL)
   - Masukkan kuantiti
   - Masukkan nama, email, telefon

2. **Form validation:**
   - Semua fields required
   - Email format validation
   - Quantity minimum 1

3. **Calculate amount:**
   - Total = Price per shirt × Quantity
   - Convert RM ke sen untuk ToyyibPay

4. **Save to database:**
   - Insert order dengan status 'pending'
   - Generate unique `billExternalReferenceNo`

5. **Create ToyyibPay bill:**
   - Prepare bill data
   - Call ToyyibPay API
   - Get BillCode dari response

6. **Redirect to payment:**
   - Redirect user ke `https://dev.toyyibpay.com/{BillCode}`

7. **After payment:**
   - User redirected ke `return.php` (status display)
   - ToyyibPay POST ke `callback.php` (server-to-server)
   - Update order status di `callback.php` (if implemented)

## 🎨 Features

### Image Preview
- Pilih variant untuk melihat preview image
- Preview update secara automatik

### Auto Calculate Total
- Total amount update secara automatik bila quantity berubah
- Display dalam RM format

### Responsive Design
- Mobile-friendly
- Clean & minimal design
- Gradient background
- Modern UI

## 🔧 Error Handling

- ✅ Database connection errors
- ✅ API request errors
- ✅ Validation errors
- ✅ JSON decode errors
- ✅ All errors displayed to user dengan message yang jelas

## 📌 Notes

1. **Variant Image:**
   - `hijaudepan.svg` tidak wujud, menggunakan `hijaufront.svg` sebagai fallback
   - Update path di code jika image file ditambah kemudian

2. **Payment Status:**
   - Order disimpan dengan status 'pending'
   - Update status di `callback.php` selepas payment confirmed

3. **Staging Environment:**
   - Gunakan `https://dev.toyyibpay.com` untuk API dan payment page
   - Update ke production URLs untuk live environment

4. **Security:**
   - Input validation untuk semua fields
   - Prepared statements untuk database queries
   - Error messages tidak expose sensitive information

## 🐛 Troubleshooting

### Order tidak save ke database
- Check database connection
- Verify table `orders` sudah dibuat
- Check database credentials

### ToyyibPay API error
- Verify credentials (secret key & category code)
- Check API endpoint URL
- Check network connectivity
- Review API response untuk error message

### Image tidak muncul
- Verify image files wujud di folder `images/`
- Check file permissions
- Verify image paths dalam code

## 📞 Support

Untuk isu dengan ToyyibPay API, rujuk:
- ToyyibPay Documentation: https://toyyibpay.com/developers
- SANDBOX Environment: https://dev.toyyibpay.com

