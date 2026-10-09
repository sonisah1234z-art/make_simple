# Admin Health Management Module - Complete Guide

## 📋 Overview

This comprehensive admin module enables administrators to manage health offers and doctor profile photos throughout the entire hospital system. All updates are automatically reflected on the patient dashboard and throughout the application.

---

## 🏥 Features Implemented

### 1. **Health Offers Management**
- ✅ Complete CRUD operations (Create, Read, Update, Delete)
- ✅ Dynamic image uploads for each offer
- ✅ Automatic display on patient dashboard
- ✅ Price tracking (original & discounted)
- ✅ Discount percentage calculation
- ✅ Validity period management
- ✅ Badge customization (emoji + text)
- ✅ Display order control
- ✅ Active/Inactive status
- ✅ Popular offer marking

### 2. **Doctor Profile Photo Management**
- ✅ Photo upload for each doctor
- ✅ Automatic display in doctor cards/profiles
- ✅ Photo deletion functionality
- ✅ Support for JPG, PNG, WEBP formats
- ✅ 5MB file size limit
- ✅ Professional image storage

---

## 🚀 How to Use

### **Access Admin Modules**

1. Log in to Admin Dashboard
2. Look for these quick action links:
   - **💊 Health Offers** - Manage all health offer cards
   - **👨‍⚕️ Doctor Profile Photos** - Upload/manage doctor photos

### **Managing Health Offers**

#### **Add New Offer**
1. Click "➕ Add New Offer" tab
2. Fill in the following fields:
   - **Offer Title** (required) - e.g., "Full Body Checkup"
   - **Badge Text** - e.g., "30% OFF"
   - **Badge Emoji** - e.g., "⭐"
   - **Description** (required) - Detailed offer description
   - **Original Price** (required) - e.g., 7500
   - **Discounted Price** (required) - e.g., 5500
   - **Validity Start** - When offer becomes available
   - **Validity End** - When offer expires
   - **Display Order** - 0 for first, 1 for second, etc.
   - **Offer Image** (required) - Upload image file
   - **Booking Link** - Leave as "book-appointment.php" or customize
   - **Active** - Check to display on dashboard
   - **Mark as Popular** - Check if this is a featured offer

3. Click "➕ Create Offer" to save

#### **Edit Offer**
1. Go to "📋 Manage Offers" tab
2. Find the offer in the table
3. Click "✏️ Edit" button
4. Update any fields
5. Click "✏️ Update Offer" to save

#### **Delete Offer**
1. Go to "📋 Manage Offers" tab
2. Click "🗑️ Delete" button next to the offer
3. Confirm deletion

### **Managing Doctor Profile Photos**

#### **Upload Doctor Photo**
1. Go to "👨‍⚕️ Doctor Profile Photos" page
2. Select a doctor from the dropdown
3. Choose an image file (JPG, PNG, or WEBP)
4. Click "📤 Upload Photo"

#### **Delete Doctor Photo**
1. Go to "👨‍⚕️ Doctor Profile Photos" page
2. Find the doctor in the grid
3. Click "🗑️ Delete" button
4. Confirm deletion

---

## 🗄️ Database Structure

### **health_offers Table**
```sql
CREATE TABLE health_offers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    image_path VARCHAR(255),
    original_price DECIMAL(10,2),
    discounted_price DECIMAL(10,2),
    discount_percentage INT,
    validity_start DATE,
    validity_end DATE,
    badge_text VARCHAR(100),
    badge_emoji VARCHAR(10),
    booking_link VARCHAR(255),
    is_active BOOLEAN,
    is_popular BOOLEAN,
    sort_order INT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### **doctors Table (Updated)**
```sql
-- Existing table with added column:
profile_photo VARCHAR(255) NULL
```

---

## 📁 File Locations

### **Admin Pages**
- `admin-health-offers-manage.php` - Health offers CRUD interface
- `admin-doctor-photos.php` - Doctor photo management
- `admin-health-offers.php` - Simple upload tool (legacy)

### **Database Functions**
- `db.php` - Contains `ensure_health_offers_table()` function

### **Uploaded Files**
- **Health Offer Images**: `assets/images/offers/`
- **Doctor Profile Photos**: `uploads/doctors/`

### **Display/Frontend**
- `dashboard.php` - Patient dashboard (displays dynamic offers)

---

## 🔐 Security Features

1. **File Upload Validation**
   - Only JPG, PNG, WEBP formats allowed
   - Maximum 5MB file size limit
   - Files stored outside web root

2. **Database Security**
   - Prepared statements for all queries
   - Input validation and sanitization
   - SQL injection prevention

3. **Access Control**
   - Admin-only access (authentication required)
   - Session validation on all pages

4. **Image Fallbacks**
   - Fallback Unsplash URLs for broken images
   - Error handling for missing files

---

## 📸 Image Requirements

### **Health Offer Images**
- **Recommended Size**: 900×220px
- **Aspect Ratio**: 16:2 (widescreen)
- **Format**: JPG, PNG, or WEBP
- **Max Size**: 5MB
- **Quality**: High-resolution for professional appearance

### **Doctor Profile Photos**
- **Recommended Size**: 300×400px (portrait) or square
- **Format**: JPG, PNG, or WEBP
- **Max Size**: 5MB
- **Quality**: Professional headshot

---

## 🎨 Display Settings

### **Patient Dashboard**
Health offers are displayed in two sections:
1. **"My Health Offers"** - Full offer cards with details
2. **"Health Checkup Packages"** - Grid view (max 4 offers)

Offers are:
- Sorted by `sort_order` (ascending)
- Filtered by validity dates (only active/valid offers shown)
- Displayed with automatic hover effects
- Fully responsive on mobile, tablet, and desktop

### **Responsive Breakpoints**
- **Desktop (1200px+)**: 3-column grid for offers
- **Tablet (768px-1199px)**: 2-column grid
- **Mobile (<768px)**: Single column

---

## ✅ Validation Rules

### **Health Offers**
- Title: Required, max 255 characters
- Description: Required
- Original Price: Must be > 0
- Discounted Price: Must be > 0
- Validity End: Must be after Validity Start
- Image: JPG/PNG/WEBP, max 5MB

### **Doctor Photos**
- Doctor ID: Must exist in database
- Image: JPG/PNG/WEBP, max 5MB
- File naming: Unique (doctor_id + timestamp)

---

## 📊 Best Practices

1. **Offer Management**
   - Set validity dates to avoid expired offers
   - Use clear, descriptive titles
   - Upload high-quality images
   - Update prices regularly
   - Mark popular offers to feature them

2. **Doctor Photos**
   - Use professional headshots
   - Ensure consistent image quality
   - Update photos regularly
   - Use recent, clear pictures

3. **Content**
   - Keep descriptions concise (50-100 words)
   - Use relevant emojis for better visibility
   - Set realistic validity periods
   - Organize offers by display order

---

## 🐛 Troubleshooting

### **Offers not showing on dashboard**
- Check if `is_active` is enabled
- Verify validity dates (start ≤ today ≤ end)
- Ensure image path is correct
- Check browser cache

### **Doctor photos not loading**
- Verify file was uploaded successfully
- Check file permissions on `uploads/doctors/` directory
- Ensure file format is JPG, PNG, or WEBP
- Check browser console for errors

### **Image upload fails**
- Check file size (max 5MB)
- Verify file format
- Ensure `assets/images/offers/` or `uploads/doctors/` directories exist and are writable
- Check server disk space

### **Database errors**
- Run `ensure_health_offers_table()` from `db.php`
- Verify database connection
- Check for duplicate entries
- Review error logs

---

## 📞 Support

For issues or questions:
1. Check the error messages displayed
2. Review the database structure
3. Verify file permissions
4. Check browser console for JavaScript errors
5. Review server logs

---

## 🔄 Integration Points

### **Patient Dashboard** (`dashboard.php`)
- Automatically fetches active health offers
- Displays in "My Health Offers" section
- Shows in "Health Checkup Packages" grid
- Updates in real-time with offer changes

### **Doctor Dashboard** (`doctor-dashboard.php`)
- Can display doctor profile photo
- Shown in doctor cards
- Used in appointment confirmations

### **Booking System** (`book-appointment.php`)
- Links from offers to booking page
- Maintains offer context in booking

---

## 📝 Change Log

### **Version 1.0**
- ✅ Health Offers CRUD system
- ✅ Doctor Profile Photo management
- ✅ Dynamic patient dashboard display
- ✅ Database schema creation
- ✅ Admin UI with professional styling
- ✅ Responsive design for all devices
- ✅ File upload validation and security
- ✅ Image fallback mechanisms

---

## 📄 License & Usage

This module is part of the Hospital Management System and is intended for administrative use only. All functionality is protected by authentication and authorization checks.

---

**Last Updated**: July 11, 2026
**Version**: 1.0
**Status**: Production Ready
