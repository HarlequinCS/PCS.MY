# Projek Cuti Sem (PCS) - Analisis Projek Lengkap
# Complete Project Analysis Documentation

**Versi Dokumentasi:** 1.0  
**Tarikh Dicipta:** 24 Disember 2025  
**Status Projek:** Production Ready

---

## 📋 Jadual Kandungan / Table of Contents

1. [Ringkasan Projek / Project Overview](#ringkasan-projek)
2. [Teknologi yang Digunakan / Technology Stack](#teknologi-yang-digunakan)
3. [Struktur Projek / Project Structure](#struktur-projek)
4. [Skema Pangkalan Data / Database Schema](#skema-pangkalan-data)
5. [API Endpoints](#api-endpoints)
6. [Fungsi-fungsi Utama / Key Features](#fungsi-fungsi-utama)
7. [Panduan Pemasangan / Installation Guide](#panduan-pemasangan)
8. [Konfigurasi / Configuration](#konfigurasi)
9. [Panduan Deployment / Deployment Guide](#panduan-deployment)
10. [Keselamatan / Security](#keselamatan)
11. [Panduan Penggunaan / Usage Guide](#panduan-penggunaan)
12. [Penyelesaian Masalah / Troubleshooting](#penyelesaian-masalah)
13. [Cadangan Pembangunan Masa Depan / Future Development](#cadangan-pembangunan-masa-depan)

---

## 📖 Ringkasan Projek / Project Overview

### Bahasa Melayu

**Projek Cuti Sem (PCS)** adalah sebuah laman web "Coming Soon" untuk jenama fesyen premium Malaysia. Projek ini dibangunkan menggunakan teknologi web moden dengan fokus pada:

- **Design & User Experience**: Antara muka yang menarik dengan animasi yang lancar
- **SEO Optimization**: Optimasi penuh untuk enjin carian dengan struktur data yang betul
- **Comment System**: Sistem komen dengan modulasi admin untuk mengawal kandungan
- **Visitor Tracking**: Sistem penjejakan pelawat unik
- **Responsive Design**: Reka bentuk yang responsif untuk semua peranti

### English

**Projek Cuti Sem (PCS)** is a "Coming Soon" website for a premium Malaysian fashion brand. This project is built using modern web technologies with focus on:

- **Design & User Experience**: Beautiful interface with smooth animations
- **SEO Optimization**: Full optimization for search engines with proper structured data
- **Comment System**: Comment system with admin moderation to control content
- **Visitor Tracking**: Unique visitor tracking system
- **Responsive Design**: Responsive design for all devices

### Domain & URL
- **Production URL**: https://projectcutisem.com
- **Local Development**: http://localhost/pcs.my/

---

## 🛠 Teknologi yang Digunakan / Technology Stack

### Frontend
- **HTML5**: Struktur laman web
- **CSS3**: Styling dengan animasi dan gradient
- **JavaScript (Vanilla)**: Interaktiviti dan API calls
- **Modernizr.js**: Feature detection library

### Backend
- **PHP 7.4+**: Server-side scripting
- **MySQL/MariaDB**: Pangkalan data relasional
- **PDO**: Database abstraction layer

### Server Environment
- **Apache**: Web server
- **mod_rewrite**: URL rewriting untuk clean URLs
- **Laragon** (Development): Local development environment

### Libraries & Tools
- **Modernizr**: Browser feature detection
- **Structured Data (JSON-LD)**: SEO structured data

---

## 📁 Struktur Projek / Project Structure

```
pcs.my/
│
├── 📄 index.html                    # Halaman utama "Coming Soon"
├── 📄 behind-the-product.html       # Halaman cerita di sebalik produk
├── 📄 workshirt.html                # Halaman produk workshirt
│
├── 📁 admin/                        # Panel pentadbir
│   ├── index.php                    # Halaman log masuk admin
│   ├── dashboard.php                # Dashboard modulasi komen
│   ├── config.php                   # Konfigurasi admin (credentials)
│   ├── css/
│   │   └── admin.css                # Styling panel admin
│   ├── js/
│   │   └── admin.js                 # JavaScript untuk admin panel
│   └── api/
│       ├── get_comments.php         # API untuk mendapatkan komen
│       └── approve_comment.php      # API untuk meluluskan/menolak komen
│
├── 📁 api/                          # API endpoints
│   ├── comments.php                 # API komen utama (POST/GET)
│   ├── visitor.php                  # API penjejakan pelawat
│   ├── comments_simple.php          # API komen alternatif
│   └── [utility files]              # Fail-fail utiliti dan debug
│
├── 📁 config/                       # Konfigurasi
│   └── database.php                 # Konfigurasi sambungan pangkalan data
│
├── 📁 includes/                     # Helper files
│   └── db.php                       # Database connection helper
│
├── 📁 css/                          # Stylesheets
│   ├── behind-the-product.css       # Styling untuk behind-the-product
│   ├── workshirt.css                # Styling untuk workshirt
│   └── ajax-loader.gif              # Loading animation
│
├── 📁 js/                           # JavaScript files
│   ├── script.min.js                # Main JavaScript (minified)
│   └── modernizr.js                 # Feature detection
│
├── 📁 images/                       # Images & Media
│   ├── PCS logo.svg                 # Logo utama
│   ├── 1.svg, 2.svg, ... 6.svg     # Product images
│   └── [favicon files]              # Favicon files
│
├── 📄 sitemap.xml                   # XML sitemap untuk SEO
├── 📄 robots.txt                    # Robots exclusion rules
├── 📄 site.webmanifest              # Web app manifest
├── 📄 favicon.ico                   # Favicon
│
├── 📄 .htaccess                     # Apache configuration (URL rewriting)
│
└── 📁 documentation/                # Dokumentasi
    ├── CANONICAL_VALIDATION_RULES.md
    ├── LARAGON_SETUP.md
    ├── SEO_SUBMISSION_GUIDE.md
    ├── ADMIN_README.md
    └── PROJECT_ANALYSIS.md          # Dokumen ini
```

---

## 🗄 Skema Pangkalan Data / Database Schema

### Database: `u284911514_pcsmy`

### Table 1: `comment`
Struktur jadual untuk menyimpan komen pengguna.

```sql
CREATE TABLE `comment` (
    `comment_id` INT AUTO_INCREMENT PRIMARY KEY,
    `comment` TEXT NOT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `status` VARCHAR(20) DEFAULT 'pending',
    INDEX `idx_status` (`status`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Kolom / Columns:**
- `comment_id`: ID unik komen (auto-increment)
- `comment`: Kandungan komen pengguna
- `ip_address`: Alamat IP pengguna (untuk tracking)
- `created_at`: Tarikh dan masa komen dibuat
- `status`: Status komen (`pending`, `approved`, `rejected`)

**Status Values:**
- `pending`: Komen baru, menunggu kelulusan admin
- `approved`: Komen telah diluluskan, akan dipaparkan di laman web
- `rejected`: Komen ditolak, tidak akan dipaparkan

### Table 2: `visitors`
Struktur jadual untuk menjejak pelawat unik.

```sql
CREATE TABLE `visitors` (
    `visitor_id` INT AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL,
    `visit_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `user_agent` TEXT,
    UNIQUE KEY `unique_visit` (`ip_address`, `visit_date`),
    INDEX `idx_ip` (`ip_address`),
    INDEX `idx_date` (`visit_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Kolom / Columns:**
- `visitor_id`: ID unik rekod pelawat
- `ip_address`: Alamat IP pelawat
- `visit_date`: Tarikh dan masa lawatan
- `user_agent`: Browser user agent string

---

## 🔌 API Endpoints

### 1. Comments API

#### **POST** `/api/comments.php`
Menghantar komen baru.

**Request:**
```json
{
    "comment": "Komen pengguna di sini"
}
```

**Response (Success):**
```json
{
    "success": true,
    "message": "Thank you. Your comment will be reviewed."
}
```

**Response (Error):**
```json
{
    "error": "Error message"
}
```

**Features:**
- Input sanitization
- Auto-create table if not exists
- Rate limiting protection
- IP address tracking
- Status set to `pending` by default

#### **GET** `/api/comments.php`
Mendapatkan semua komen yang diluluskan.

**Response:**
```json
{
    "success": true,
    "comments": [
        {
            "comment_id": 1,
            "comment": "Great product!",
            "created_at": "2024-12-24 10:30:00"
        }
    ],
    "count": 1
}
```

### 2. Visitor Tracking API

#### **POST** `/api/visitor.php`
Merekod lawatan pelawat baru.

**Response:**
```json
{
    "success": true,
    "total_visitors": 150,
    "message": "Visit recorded"
}
```

#### **GET** `/api/visitor.php`
Mendapatkan jumlah pelawat unik.

**Response:**
```json
{
    "success": true,
    "total_visitors": 150
}
```

### 3. Admin APIs

#### **GET** `/admin/api/get_comments.php?filter={filter}`
Mendapatkan komen untuk admin panel.

**Query Parameters:**
- `filter`: `pending`, `approved`, atau `all`

**Response:**
```json
{
    "success": true,
    "comments": [...],
    "counts": {
        "pending": 5,
        "approved": 20,
        "total": 25
    }
}
```

#### **POST** `/admin/api/approve_comment.php`
Meluluskan atau menolak komen.

**Request:**
```json
{
    "comment_id": 1,
    "action": "approve"  // atau "reject"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Comment approved"
}
```

---

## ✨ Fungsi-fungsi Utama / Key Features

### 1. Landing Page (index.html)
- **Animated Background**: Gradient background dengan animasi
- **Logo Animation**: Logo dengan hover effects dan floating animation
- **Visitor Counter**: Paparan jumlah pelawat unik
- **Comment System**: Sistem komen interaktif dengan card design
- **Responsive Design**: Sesuai untuk desktop, tablet, dan mobile
- **SEO Optimized**: Structured data, meta tags lengkap

### 2. Behind The Product Page
- **Timeline Design**: Paparan fasa pembangunan produk
- **Phase Cards**: Setiap fasa ditunjukkan dalam card yang menarik
- **SVG Images**: Penggunaan SVG untuk grafik berkualiti tinggi
- **Smooth Animations**: Animasi lancar untuk scrolling

### 3. Workshirt Product Page
- **Product Gallery**: Galeri produk dengan pelbagai warna
- **Color Switcher**: Tukar warna produk secara interaktif
- **Countdown Timer**: Timer untuk pelancaran produk
- **Feature Cards**: Paparan ciri-ciri produk
- **Theme Switching**: Tukar tema berdasarkan warna pilihan

### 4. Admin Panel
- **Login System**: Sistem log masuk yang selamat
- **Comment Moderation**: Modulasi komen dengan filter
- **Real-time Stats**: Statistik masa nyata (pending, approved, total)
- **Responsive Dashboard**: Dashboard yang responsif
- **Auto-refresh**: Auto-refresh setiap 30 saat

### 5. SEO Features
- **Structured Data**: JSON-LD structured data
- **Open Graph Tags**: Tags untuk social media sharing
- **Twitter Cards**: Twitter card metadata
- **Canonical URLs**: URL kanonik untuk setiap halaman
- **Sitemap**: XML sitemap untuk search engines
- **Robots.txt**: Robots exclusion rules

---

## 📦 Panduan Pemasangan / Installation Guide

### Prerequisites
- PHP 7.4 atau lebih tinggi
- MySQL/MariaDB 5.7 atau lebih tinggi
- Apache dengan mod_rewrite enabled
- Composer (optional, untuk dependency management)

### Langkah-langkah Pemasangan

#### 1. Clone atau Download Projek
```bash
# Jika menggunakan Git
git clone [repository-url]
cd pcs.my

# Atau extract ZIP file ke direktori web server
```

#### 2. Setup Pangkalan Data

##### a. Buat Database
```sql
CREATE DATABASE u284911514_pcsmy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

##### b. Buat User (atau gunakan existing)
```sql
CREATE USER 'u284911514_pcsadmin'@'localhost' IDENTIFIED BY 'PCSadmin25';
GRANT ALL PRIVILEGES ON u284911514_pcsmy.* TO 'u284911514_pcsadmin'@'localhost';
FLUSH PRIVILEGES;
```

##### c. Import Schema
```bash
mysql -u u284911514_pcsadmin -p u284911514_pcsmy < database_setup.sql
```

Atau jalankan SQL secara manual:
```sql
-- Comments table
CREATE TABLE IF NOT EXISTS `comment` (
    `comment_id` INT AUTO_INCREMENT PRIMARY KEY,
    `comment` TEXT NOT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `status` VARCHAR(20) DEFAULT 'pending',
    INDEX `idx_status` (`status`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Visitors table
CREATE TABLE IF NOT EXISTS `visitors` (
    `visitor_id` INT AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL,
    `visit_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `user_agent` TEXT,
    UNIQUE KEY `unique_visit` (`ip_address`, `visit_date`),
    INDEX `idx_ip` (`ip_address`),
    INDEX `idx_date` (`visit_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 3. Konfigurasi Database

Edit fail `config/database.php`:
```php
return [
    'host' => 'localhost',
    'database' => 'u284911514_pcsmy',
    'username' => 'u284911514_pcsadmin',
    'password' => 'PCSadmin25',  // Tukar password ini!
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];
```

#### 4. Konfigurasi Admin

Edit fail `admin/config.php`:
```php
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'PCSadmin2024!');  // Tukar password ini!
```

#### 5. Setup Apache (untuk Clean URLs)

Pastikan `.htaccess` ada di root directory dan mod_rewrite enabled.

**Untuk Laragon:**
- Menu → Apache → mod_rewrite → Enable
- Restart Laragon

**Untuk Apache biasa:**
```apache
# Pastikan AllowOverride All dalam httpd.conf
AllowOverride All
```

#### 6. Set File Permissions (Linux/Mac)
```bash
chmod 755 -R .
chmod 644 *.html *.php
chmod 644 css/* js/*
```

#### 7. Test Installation

1. Buka browser: `http://localhost/pcs.my/`
2. Test visitor tracking
3. Test comment submission
4. Test admin login: `http://localhost/pcs.my/admin/`
5. Check database - pastikan jadual wujud dan boleh diakses

---

## ⚙️ Konfigurasi / Configuration

### Environment Variables
Projek ini tidak menggunakan `.env` file. Semua konfigurasi dalam fail PHP:

- **Database**: `config/database.php`
- **Admin**: `admin/config.php`

### URL Configuration

#### Production
- Base URL: `https://projectcutisem.com`
- Admin URL: `https://projectcutisem.com/admin/`
- API Base: `https://projectcutisem.com/api/`

#### Development
- Base URL: `http://localhost/pcs.my`
- Admin URL: `http://localhost/pcs.my/admin/`
- API Base: `http://localhost/pcs.my/api/`

### Clean URLs
Projek menggunakan `.htaccess` untuk clean URLs:
- `/behind-the-product.html` → `/behind-the-product`
- `/workshirt.html` → `/workshirt`
- `/something/` → `/something` (trailing slash removal)

---

## 🚀 Panduan Deployment / Deployment Guide

### Pre-Deployment Checklist

- [ ] Update database credentials in `config/database.php`
- [ ] Change admin password in `admin/config.php`
- [ ] Enable HTTPS in `admin/config.php` (set `cookie_secure => 1`)
- [ ] Update domain URLs in HTML files (canonical, Open Graph, etc.)
- [ ] Update `sitemap.xml` with correct dates
- [ ] Test all API endpoints
- [ ] Test admin panel functionality
- [ ] Test responsive design on multiple devices
- [ ] Backup database

### Deployment Steps

#### 1. Upload Files
```bash
# Upload semua fail ke server melalui FTP/SFTP
# Pastikan struktur direktori sama
```

#### 2. Database Setup on Server
- Create database di hosting panel
- Import schema menggunakan phpMyAdmin atau command line
- Update credentials dalam `config/database.php`

#### 3. Configure Web Server
- Enable mod_rewrite di Apache
- Ensure `.htaccess` is readable
- Set proper file permissions

#### 4. SSL Certificate
- Install SSL certificate (Let's Encrypt recommended)
- Update `admin/config.php` untuk HTTPS
- Update canonical URLs untuk HTTPS

#### 5. Test Production
- Test homepage
- Test comment submission
- Test admin login
- Test API endpoints
- Check browser console untuk errors

### Post-Deployment

1. **Submit Sitemap**:
   - Google Search Console
   - Bing Webmaster Tools
   - Yandex Webmaster

2. **Monitor**:
   - Check error logs
   - Monitor visitor tracking
   - Review comment submissions

---

## 🔒 Keselamatan / Security

### Current Security Measures

1. **SQL Injection Protection**
   - Menggunakan PDO prepared statements
   - Input sanitization dengan `sanitizeInput()`

2. **XSS Protection**
   - HTML escaping dengan `htmlspecialchars()`
   - JSON encoding untuk API responses

3. **CSRF Protection**
   - Session-based authentication untuk admin
   - HttpOnly cookies

4. **Password Security**
   - Admin password dalam config file (CHANGE DEFAULT!)
   - Database password dalam config file

### Security Recommendations

1. **Change Default Passwords**
   - Admin password: Edit `admin/config.php`
   - Database password: Edit `config/database.php`

2. **Enable HTTPS**
   - Update `admin/config.php`: `cookie_secure => 1`
   - Redirect HTTP to HTTPS via `.htaccess`

3. **File Permissions**
   - Config files: 600 (read/write owner only)
   - PHP files: 644
   - Directories: 755

4. **Rate Limiting**
   - Consider adding rate limiting untuk comment API
   - Implement CAPTCHA untuk comment submission

5. **Input Validation**
   - Add more validation rules
   - Implement maximum length limits (already done)

6. **Error Handling**
   - Disable error display in production
   - Log errors to file, not display to users

7. **Backup**
   - Regular database backups
   - File backups

### Security Files to Protect

- `config/database.php` - Contains database credentials
- `admin/config.php` - Contains admin credentials
- `.htaccess` - Server configuration

**DO NOT** commit these files with production credentials to public repositories!

---

## 📖 Panduan Penggunaan / Usage Guide

### Untuk Pengguna Biasa

#### Menambah Komen
1. Buka laman web
2. Scroll ke bahagian "Leave Your Encouragement"
3. Taip komen dalam textarea
4. Klik "Submit Comment"
5. Komen akan menunggu kelulusan admin

#### Melihat Komen
- Komen yang diluluskan akan dipaparkan secara automatik
- Komen ditunjukkan dalam format card yang menarik

### Untuk Admin

#### Log Masuk Admin Panel
1. Pergi ke `/admin/`
2. Masukkan username dan password
3. Klik "Login"

**Default Credentials:**
- Username: `admin`
- Password: `PCSadmin2024!`

⚠️ **PENTING**: Tukar password segera selepas log masuk pertama!

#### Modulasi Komen
1. Setelah log masuk, anda akan melihat dashboard
2. Tab "Pending Review" menunjukkan komen yang menunggu kelulusan
3. Untuk meluluskan komen:
   - Klik butang "Approve" pada komen
   - Komen akan dipindahkan ke "Approved" tab
4. Untuk menolak komen:
   - Klik butang "Reject"
   - Komen tidak akan dipaparkan di laman web

#### Statistik
- Dashboard menunjukkan jumlah komen pending, approved, dan total
- Statistik dikemaskini secara automatik

---

## 🔧 Penyelesaian Masalah / Troubleshooting

### Masalah: Komen tidak dapat dihantar

**Penyelesaian:**
1. Check database connection dalam `config/database.php`
2. Verify jadual `comment` wujud dalam database
3. Check PHP error logs
4. Verify file permissions pada `api/comments.php`
5. Check browser console (F12) untuk JavaScript errors

### Masalah: Visitor counter tidak berfungsi

**Penyelesaian:**
1. Check `api/visitor.php` boleh diakses
2. Verify jadual `visitors` wujud
3. Check database connection
4. Verify JavaScript dalam `index.html` memanggil API dengan betul

### Masalah: Admin tidak dapat log masuk

**Penyelesaian:**
1. Verify credentials dalam `admin/config.php`
2. Check session settings dalam PHP
3. Clear browser cookies
4. Check file permissions pada `admin/config.php`
5. Verify session directory writable

### Masalah: Clean URLs tidak berfungsi

**Penyelesaian:**
1. Enable mod_rewrite di Apache
2. Check `.htaccess` file exists dan readable
3. Verify `AllowOverride All` dalam Apache config
4. Check Apache error logs

### Masalah: Database connection error

**Penyelesaian:**
1. Verify database credentials dalam `config/database.php`
2. Check database server running
3. Verify database user mempunyai permissions yang betul
4. Check firewall settings
5. Test connection dengan `api/test_connection.php`

### Masalah: CSS/JS tidak dimuatkan

**Penyelesaian:**
1. Check file paths dalam HTML
2. Verify files exist dalam directories
3. Check file permissions
4. Clear browser cache
5. Check server logs untuk 404 errors

---

## 🔮 Cadangan Pembangunan Masa Depan / Future Development

### Short-term Improvements

1. **Email Notifications**
   - Email admin apabila ada komen baru
   - Email confirmation untuk comment submission

2. **Enhanced Admin Features**
   - Edit comments functionality
   - Delete comments permanently
   - Export comments to CSV
   - Filter by date range

3. **Comment Features**
   - Reply to comments
   - Like/Dislike system
   - Comment sorting options

4. **Analytics Integration**
   - Google Analytics integration
   - Visitor analytics dashboard
   - Popular pages tracking

### Medium-term Enhancements

1. **User Authentication**
   - User registration system
   - Social login (Google, Facebook)
   - User profiles

2. **E-commerce Integration**
   - Product catalog
   - Shopping cart
   - Payment gateway integration

3. **Content Management**
   - CMS untuk mengurus kandungan
   - Blog system
   - News/Updates section

4. **Multi-language Support**
   - Bahasa Melayu dan English
   - Language switcher
   - Translated content

### Long-term Vision

1. **Mobile App**
   - iOS app
   - Android app
   - Push notifications

2. **Advanced Features**
   - AI-powered comment moderation
   - Personalized recommendations
   - Advanced analytics

3. **Scalability**
   - Move to cloud hosting
   - CDN integration
   - Database optimization
   - Caching system

---

## 📝 Nota Penting / Important Notes

### Development vs Production

**Development:**
- Error display enabled untuk debugging
- Local database credentials
- HTTP connections

**Production:**
- Error display disabled
- Production database credentials
- HTTPS connections
- Error logging enabled

### Database Maintenance

**Regular Tasks:**
- Backup database weekly
- Monitor table sizes
- Optimize tables jika perlu
- Review dan delete old pending comments

**Cleanup Scripts:**
- Consider creating script untuk delete old rejected comments
- Archive old approved comments jika perlu

### Code Maintenance

1. **Keep Dependencies Updated**
   - Monitor security updates untuk PHP
   - Update libraries jika ada

2. **Code Reviews**
   - Review code sebelum deployment
   - Test thoroughly

3. **Documentation**
   - Update dokumentasi apabila ada perubahan
   - Document new features

---

## 📞 Maklumat Hubungan / Contact Information

### Support Resources

1. **Dokumentasi**
   - `ADMIN_README.md` - Panduan admin panel
   - `CANONICAL_VALIDATION_RULES.md` - SEO rules
   - `SEO_SUBMISSION_GUIDE.md` - Panduan SEO submission
   - `LARAGON_SETUP.md` - Setup untuk Laragon

2. **Testing Files**
   - `api/test_connection.php` - Test database connection
   - `api/test_db.php` - Test database functionality
   - `api/test_insert.php` - Test insert operations

3. **Utility Files**
   - `api/fix_database_permanent.php` - Fix database issues
   - `api/fix_autoincrement.php` - Fix AUTO_INCREMENT issues

---

## ✅ Checklist untuk Developer Baru / Checklist for New Developer

### Setup Checklist
- [ ] Clone/download project
- [ ] Setup local environment (Laragon/XAMPP/etc)
- [ ] Create database
- [ ] Import schema
- [ ] Configure database credentials
- [ ] Configure admin credentials
- [ ] Enable mod_rewrite
- [ ] Test homepage
- [ ] Test comment submission
- [ ] Test admin panel
- [ ] Test all API endpoints

### Understanding Checklist
- [ ] Understand project structure
- [ ] Understand database schema
- [ ] Understand API endpoints
- [ ] Understand admin workflow
- [ ] Understand SEO structure
- [ ] Read all documentation files

### Before Making Changes
- [ ] Backup database
- [ ] Backup files
- [ ] Create branch (if using Git)
- [ ] Test changes locally
- [ ] Review code
- [ ] Update documentation if needed

---

## 📄 License

Lihat fail `LICENSE` untuk maklumat license.

---

## 🙏 Acknowledgments

Terima kasih kepada semua yang terlibat dalam pembangunan projek ini.

Thank you to everyone involved in the development of this project.

---

**Dokumen ini dikemaskini pada**: 24 Disember 2025  
**Versi**: 1.0  
**Penulis**: Project Development Team

---

*Dokumen ini menyediakan gambaran menyeluruh tentang Projek Cuti Sem. Jika anda mempunyai soalan atau memerlukan bantuan tambahan, sila rujuk bahagian Troubleshooting atau dokumentasi lain dalam projek ini.*

*This document provides a comprehensive overview of Projek Cuti Sem. If you have questions or need additional assistance, please refer to the Troubleshooting section or other documentation in this project.*

