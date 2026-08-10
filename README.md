# Shree Label File Server — Enterprise File Management System

> **Phase Complete | Localhost (XAMPP) & Live Server Ready | Fully Integrated with Google Drive, Hostinger S3 & FTP**

Centralized, enterprise-grade file server for managing **Artwork, Plate Files, Customer Documents, Job Documents, Production Documents, PDF, Excel, Word, Images, ZIP** and other documents. Built with a premium, modern UI inspired by Google Drive + Dropbox, fully responsive and optimized for official company use.

---

## Project Information

- **Project Name:** Shree Label File Server
- **Version:** Enterprise Production Release
- **Tech Stack:** PHP 8+, MySQL, HTML5, CSS3, JavaScript, Bootstrap 5, Tailwind CSS, Chart.js
- **Environment:** XAMPP / Apache / MySQL (Windows) or Live Server (cPanel / Hostinger)
- **Architecture:** Modular PHP, StorageAdapter pattern, pure cURL (No Composer dependencies required)

---

## 🚀 How to Connect Google Drive / গুগল ড্রাইভ কীভাবে কানেক্ট করবেন

This system supports direct upload to your Google Drive via OAuth 2.0 without any heavy third-party packages.
এই সিস্টেমে কোনো থার্ড-পার্টি প্যাকেজ ছাড়াই সরাসরি আপনার গুগল ড্রাইভে ফাইল আপলোড করা যায়।

### 🇬🇧 English Guide

**Step 1: Create Google Cloud Project**
1. Go to [Google Cloud Console](https://console.cloud.google.com/).
2. Create a new project and go to **APIs & Services > Library**.
3. Search for **Google Drive API** and click **Enable**.

**Step 2: Setup OAuth Consent Screen**
1. Go to **APIs & Services > OAuth consent screen**.
2. Select **External** and click Create.
3. Fill in the App Name and Email. Save and continue.
4. (Crucial) Under **Test users**, click **+ ADD USERS** and add your own Gmail address (the one you will use to login).
5. Alternatively, you can click **PUBLISH APP** to move it to production.

**Step 3: Get Credentials (Client ID & Secret)**
1. Go to **APIs & Services > Credentials**.
2. Click **+ CREATE CREDENTIALS** > **OAuth client ID**.
3. Choose **Web application**.
4. Under **Authorized redirect URIs**, enter exactly:
   - For Localhost: `http://localhost/file-server/api/oauth/google/callback.php`
   - For Live Server: `https://yourdomain.com/api/oauth/google/callback.php` (e.g., `https://shreelabel.com/drive/api/oauth/google/callback.php`)
5. Click **Create** and copy your **Client ID** and **Client Secret**.

**Step 4: Connect from the App**
1. Open the File Server and go to **Settings > Storage**.
2. Under Google Drive, paste your Client ID and Secret.
3. (Optional) **Drive Folder ID:** Leave this EMPTY to upload to your "My Drive" root. If you want to upload to a specific folder, enter the folder's unique string ID (e.g., `1aB2cD3e...`), NOT the folder name.
4. Click **Save & Connect**. Authenticate with Google.
5. Finally, click **Set Active: Google Drive** to start uploading!

---

### 🇧🇩 বাংলা গাইড (Bengali)

**ধাপ ১: গুগল ক্লাউড প্রোজেক্ট তৈরি করা**
১. [Google Cloud Console](https://console.cloud.google.com/)-এ যান।
২. নতুন একটি প্রোজেক্ট তৈরি করে **APIs & Services > Library**-তে যান।
৩. **Google Drive API** লিখে সার্চ করুন এবং **Enable** করুন।

**ধাপ ২: OAuth Consent Screen সেটআপ**
১. **APIs & Services > OAuth consent screen**-এ যান।
২. **External** সিলেক্ট করে Create-এ ক্লিক করুন। অ্যাপের নাম ও ইমেইল দিন।
৩. (সবচেয়ে গুরুত্বপূর্ণ) **Test users** সেকশনে গিয়ে **+ ADD USERS**-এ ক্লিক করে আপনার নিজের জিমেইল অ্যাড্রেসটি অ্যাড করুন।
৪. অথবা, আপনি **PUBLISH APP**-এ ক্লিক করে এটিকে প্রোডাকশনে পাঠাতে পারেন।

**ধাপ ৩: ক্রেডেনশিয়ালস তৈরি (Client ID ও Secret)**
১. **APIs & Services > Credentials**-এ যান।
২. **+ CREATE CREDENTIALS > OAuth client ID**-তে ক্লিক করে **Web application** সিলেক্ট করুন।
৩. **Authorized redirect URIs**-এর ঘরে হুবহু এই লিংকটি দিন:
   - লোকালহোস্টের জন্য: `http://localhost/file-server/api/oauth/google/callback.php`
   - লাইভ সার্ভারের জন্য: `https://shreelabel.com/drive/api/oauth/google/callback.php`
৪. Create-এ ক্লিক করে আপনার **Client ID** এবং **Client Secret** কপি করে নিন।

**ধাপ ৪: ফাইল সার্ভার থেকে কানেক্ট করা**
১. ফাইল সার্ভারের **Settings > Storage** পেজে যান।
২. Google Drive সেকশনে কপি করা Client ID এবং Secret বসান।
৩. (ঐচ্ছিক) **Drive Folder ID:** আপনি যদি সরাসরি গুগল ড্রাইভের হোম পেজে ফাইল রাখতে চান, তবে এটি সম্পূর্ণ ফাঁকা (Empty) রাখুন। নির্দিষ্ট কোনো ফোল্ডারে রাখতে চাইলে সেই ফোল্ডারের আসল ID কোডটি দিন (যেমন: `1aB2cD3e...`), ফোল্ডারের নাম নয়।
৪. **Save & Connect**-এ ক্লিক করে গুগলের পারমিশন দিন।
৫. সবশেষে **Set Active: Google Drive** বাটনে ক্লিক করলেই আপনার গুগল ড্রাইভ ডিফল্ট স্টোরেজ হিসেবে কাজ শুরু করবে!

---

## Features

### Storage Abstraction
- Instantly switch between **Local Disk**, **Google Drive**, **Hostinger / S3**, and **FTP / FTPS**.
- Frontend never knows the physical storage path.
- All file operations go through secure PHP endpoints: `api/download.php?id=123`.

### Dashboard & File Manager
- **Total Files / Folders / Storage Used** with Interactive Charts (Doughnut, Bar, Line).
- **Grid View** and **List View** toggle. Sort by Name, Size, Date, Type.
- Global search and category filters (Images, PDF, Documents, Excel, Video, ZIP).

### File Upload & 2GB Large Files
- Drag & Drop, Multiple File Upload.
- Support for **2GB+ files via Chunked Upload** (5MB chunks).
- Quota enforcement per user.
- *Note: For 2GB uploads on Local, configure php.ini (`upload_max_filesize=2048M`).*

### Sharing & Multi-User System
- Share files/folders via email or public link with Viewer/Editor permissions.
- Admin can create users with **quota assignment** (e.g., 5GB, 10GB).
- Per-user permissions: `can_upload`, `can_download`, `can_delete`, etc.

### Security
- Passwords hashed via `password_hash()`.
- PDO prepared statements (SQL injection prevention).
- CSRF protection and secure token exchange for OAuth.

---

## Database (MySQL)

- Database `file_server` is auto-created on first load on localhost.
- For live servers, manual import available via `database/mysql_schema.sql`.

## Project Name

**Shree Label File Server**
Official file management system for company documents. Designed to seamlessly switch storage backends without rebuilding the frontend, maintaining a premium user experience across all platforms.
