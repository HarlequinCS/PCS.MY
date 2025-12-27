# 🚀 Quick Start Guide - Projek Cuti Sem

**Versi:** 1.0 | **Tarikh:** 24 Disember 2025

---

## 📚 Dokumentasi Utama

**Untuk analisis lengkap, baca:** [`PROJECT_ANALYSIS.md`](./PROJECT_ANALYSIS.md)

Fail tersebut mengandungi:
- ✅ Analisis lengkap semua komponen
- ✅ Panduan setup terperinci
- ✅ Dokumentasi API
- ✅ Struktur database
- ✅ Troubleshooting guide

---

## ⚡ Quick Setup (5 Minit)

### 1. Requirements
- PHP 7.4+
- MySQL 5.7+
- Apache dengan mod_rewrite

### 2. Setup Database
```sql
CREATE DATABASE u284911514_pcsmy;
CREATE USER 'u284911514_pcsadmin'@'localhost' IDENTIFIED BY 'PCSadmin25';
GRANT ALL PRIVILEGES ON u284911514_pcsmy.* TO 'u284911514_pcsadmin'@'localhost';
```

### 3. Import Schema
```bash
mysql -u u284911514_pcsadmin -p u284911514_pcsmy < database_setup.sql
```

### 4. Configure Files
- Edit `config/database.php` - Database credentials
- Edit `admin/config.php` - Admin credentials (CHANGE PASSWORD!)

### 5. Test
- Buka: `http://localhost/pcs.my/`
- Admin: `http://localhost/pcs.my/admin/`
  - Username: `admin`
  - Password: `PCSadmin2024!`

---

## 📁 Struktur Utama

```
pcs.my/
├── index.html              # Homepage
├── behind-the-product.html # Product story
├── workshirt.html          # Product page
├── admin/                  # Admin panel
├── api/                    # API endpoints
├── config/                 # Configuration
└── PROJECT_ANALYSIS.md     # 📖 Dokumentasi lengkap
```

---

## 🔑 Credentials (DEFAULT - TUKAR SEGERA!)

### Admin Panel
- **URL:** `/admin/`
- **Username:** `admin`
- **Password:** `PCSadmin2024!`

### Database
- **Host:** `localhost`
- **Database:** `u284911514_pcsmy`
- **User:** `u284911514_pcsadmin`
- **Password:** `PCSadmin25`

⚠️ **PENTING:** Tukar semua password sebelum production!

---

## 🔗 Links Penting

| Dokumentasi | Penerangan |
|------------|------------|
| [`PROJECT_ANALYSIS.md`](./PROJECT_ANALYSIS.md) | 📖 Analisis lengkap projek |
| [`ADMIN_README.md`](./ADMIN_README.md) | 👨‍💼 Panduan admin panel |
| [`SEO_SUBMISSION_GUIDE.md`](./SEO_SUBMISSION_GUIDE.md) | 🔍 Panduan SEO |
| [`CANONICAL_VALIDATION_RULES.md`](./CANONICAL_VALIDATION_RULES.md) | ✅ SEO validation rules |
| [`LARAGON_SETUP.md`](./LARAGON_SETUP.md) | 🛠️ Setup untuk Laragon |

---

## 📋 Checklist Developer Baru

- [ ] Baca [`PROJECT_ANALYSIS.md`](./PROJECT_ANALYSIS.md)
- [ ] Setup local environment
- [ ] Test homepage
- [ ] Test comment system
- [ ] Test admin panel
- [ ] Fahami struktur database
- [ ] Fahami API endpoints
- [ ] Review security settings

---

## ❓ Butuh Bantuan?

1. **Setup Issues?** → Lihat [`PROJECT_ANALYSIS.md`](./PROJECT_ANALYSIS.md) - Bahagian "Penyelesaian Masalah"
2. **Admin Panel?** → Lihat [`ADMIN_README.md`](./ADMIN_README.md)
3. **Database?** → Lihat [`PROJECT_ANALYSIS.md`](./PROJECT_ANALYSIS.md) - Bahagian "Skema Pangkalan Data"
4. **API?** → Lihat [`PROJECT_ANALYSIS.md`](./PROJECT_ANALYSIS.md) - Bahagian "API Endpoints"

---

## 🎯 Next Steps

1. ✅ Setup project locally
2. ✅ Test semua fungsi
3. ✅ Baca dokumentasi lengkap
4. ✅ Fahami codebase
5. ✅ Buat perubahan/kemaskini

---

**Selamat Membangun! 🚀**

*Untuk maklumat lanjut, rujuk [`PROJECT_ANALYSIS.md`](./PROJECT_ANALYSIS.md)*

