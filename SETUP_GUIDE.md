# 🚗 VrakeIT — New Device Setup Guide

This guide walks you through setting up the **VrakeIT** Road Incident Reporting System on a brand-new Windows machine from scratch.

---

## 📋 Table of Contents

1. [Prerequisites & Downloads](#1-prerequisites--downloads)
2. [Install XAMPP](#2-install-xampp)
3. [Install Composer](#3-install-composer)
4. [Install ImageMagick](#4-install-imagemagick)
5. [Install Tesseract OCR](#5-install-tesseract-ocr)
6. [Clone / Copy the Project](#6-clone--copy-the-project)
7. [Install PHP Dependencies](#7-install-php-dependencies)
8. [Create the `.env` File](#8-create-the-env-file)
9. [Import the Database](#9-import-the-database)
10. [Run the App](#10-run-the-app)
11. [Optional: Cloudflare Tunnel (Mobile & Push Notifications)](#11-optional-cloudflare-tunnel-mobile--push-notifications)
12. [Troubleshooting](#12-troubleshooting)

---

## 1. Prerequisites & Downloads

Download all of these **before** you start:

| Tool | Purpose | Download Link |
|---|---|---|
| **XAMPP** (PHP 8.x) | Local web server (Apache + MySQL + PHP) | https://www.apachefriends.org |
| **Composer** | PHP dependency manager (PHPMailer, etc.) | https://getcomposer.org/Composer-Setup.exe |
| **ImageMagick** | *(Optional)* ID image sharpening if using local Tesseract | https://imagemagick.org/script/download.php#windows |
| **Tesseract OCR** | *(Optional)* Text extraction local fallback | https://github.com/UB-Mannheim/tesseract/wiki |
| **Git** *(optional)* | Clone the repo from GitHub | https://git-scm.com/download/win |
| **VS Code** *(optional)* | Recommended code editor | https://code.visualstudio.com |

---

## 2. Install XAMPP

1. Download **XAMPP for Windows** (PHP 8.x version) from [apachefriends.org](https://www.apachefriends.org).
2. Run the installer with **default settings** (installs to `C:\xampp`).
3. Open the **XAMPP Control Panel** and start both:
   - ✅ **Apache**
   - ✅ **MySQL**
4. Verify it's working: open your browser and go to `http://localhost/` — you should see the XAMPP welcome page.

> **Port conflicts?** If Apache won't start, something is already using port 80 (e.g. Skype, IIS). In the XAMPP Control Panel, click **Config → Apache (httpd.conf)** and change `Listen 80` to `Listen 8080`.

---

## 3. Install Composer

1. Download and run [Composer-Setup.exe](https://getcomposer.org/Composer-Setup.exe).
2. When the installer asks for the **PHP executable**, point it to:
   ```
   C:\xampp\php\php.exe
   ```
3. Complete the installation.
4. Verify by opening a new **PowerShell** window and running:
   ```bash
   composer --version
   ```
   You should see something like `Composer version 2.x.x`.

---

## 4. Install ImageMagick (Optional)

> **Note:** Since the system now uses Gemini AI for OCR by default, you only need ImageMagick if you plan to switch back to local Tesseract OCR in the `config.php` file.

VrakeIT uses ImageMagick to clean and enhance uploaded ID images before local OCR scanning.

1. Go to [imagemagick.org/script/download.php#windows](https://imagemagick.org/script/download.php#windows).
2. Download the file named something like:
   ```
   ImageMagick-7.1.x-x-Q16-HDRI-x64-dll.exe
   ```
3. Run the installer. During installation, make sure to check **both** of these boxes:
   - ☑️ **Add application directory to your system PATH**
   - ☑️ **Install legacy utilities (e.g. convert)**
4. After installation, verify in PowerShell:
   ```bash
   magick --version
   ```
   You should see `ImageMagick 7.x.x`.

---

## 5. Install Tesseract OCR (Optional)

> **Note:** VrakeIT now uses Gemini AI for identity verification. Tesseract is only required if you want a local, offline fallback.

VrakeIT uses Tesseract to read text from uploaded ID cards locally.

1. Go to [UB-Mannheim's Tesseract Wiki](https://github.com/UB-Mannheim/tesseract/wiki).
2. Download the 64-bit installer:
   ```
   tesseract-ocr-w64-setup-5.x.x.exe
   ```
3. Run the installer. **Leave the default install directory** as:
   ```
   C:\Program Files\Tesseract-OCR
   ```
4. *(Recommended)* During installation, expand the **"Additional language data"** section and check **Filipino / Tagalog** for better Philippine ID recognition.
5. Verify in PowerShell:
   ```bash
   tesseract --version
   ```

---

## 6. Clone / Copy the Project

### Option A — Clone from GitHub (Recommended)
```bash
cd C:\xampp\htdocs
git clone https://github.com/lawrencefrancisco/vrakeIT.git vrakeit
```

### Option B — Copy from a ZIP/USB
1. Extract the project folder.
2. Place it inside `C:\xampp\htdocs\` so the folder becomes:
   ```
   C:\xampp\htdocs\vrakeit\
   ```

---

## 7. Install PHP Dependencies

VrakeIT uses **PHPMailer** for sending emails (OTPs, notifications). Install it with Composer:

1. Open **PowerShell** and navigate to the project folder:
   ```bash
   cd C:\xampp\htdocs\vrakeit
   ```
2. Run:
   ```bash
   composer install
   ```
   This reads `composer.json` and downloads the `vendor/` folder automatically.

---

## 8. Create the `.env` File

The `.env` file holds your **secret API key** for the Gemini AI (used for ID OCR verification). This file is **not included in Git** for security reasons — you must create it manually.

1. In the project root (`C:\xampp\htdocs\vrakeit\`), create a new file named **`.env`**
2. Paste the following content (replace the value with the real key):

```env
GEMINI_API_KEY="YOUR_GEMINI_API_KEY_HERE"
```

> **Where do I get the Gemini API key?** Go to [Google AI Studio](https://aistudio.google.com/apikey) and generate a free API key. Ask the project owner for the key used in production.

---

## 9. Import the Database

1. Open your browser and go to `http://localhost/phpmyadmin/`
2. In the left sidebar, click **New**.
3. Enter `vrakeit` as the database name and click **Create**.
4. Click the new `vrakeit` database in the sidebar.
5. Click the **Import** tab at the top.
6. Click **Choose File** and select:
   ```
   C:\xampp\htdocs\vrakeit\database\vrakeit.sql
   ```
7. Scroll down and click **Import**.
8. *(Optional — run migrations if needed)* Open your browser and visit:
   ```
   http://localhost/vrakeit/database/run_migration.php
   ```
   This applies any pending table changes (OCR verification, push subscriptions, etc.).

> **Database credentials** are already pre-configured in `config/config.php` for standard XAMPP:
> - **Host:** `localhost`
> - **User:** `root`
> - **Password:** *(empty)*
> - **Database:** `vrakeit`

---

## 10. Run the App

1. Make sure **Apache** and **MySQL** are running in the XAMPP Control Panel.
2. Open your browser and go to:
   ```
   http://localhost/vrakeit/
   ```
3. You should see the **VrakeIT landing page** 🎉

### Default Admin Access
Navigate to `http://localhost/vrakeit/admin/` to access the admin panel. Use the credentials stored in the `vrakeit` database (`users` table with `role = 'admin'`).

---

## 11. Optional: Cloudflare Tunnel (Mobile & Push Notifications)

> **Why?** Web Push Notifications require HTTPS. On your local network, they will NOT work with a raw IP like `192.168.1.x`. Cloudflare Tunnel gives you a free HTTPS URL instantly.

### Install `cloudflared`
1. Download the Windows binary from:
   https://github.com/cloudflare/cloudflared/releases/latest
   *(Download `cloudflared-windows-amd64.exe`)*
2. Rename it to `cloudflared.exe` and place it anywhere (or add to PATH).

### Start the Tunnel
Open PowerShell and run:
```bash
cloudflared tunnel --url http://localhost
```
Look for a line in the output like:
```
https://random-words-here.trycloudflare.com
```
Use that HTTPS URL on your phone or any other device to access the system!

> **Note:** The tunnel URL changes every time you restart it. For a permanent URL, sign up for a free [Cloudflare account](https://www.cloudflare.com/) and create a named tunnel.

---

## 12. Troubleshooting

### ❌ Apache won't start
- Port 80 is in use. Check if Skype, IIS, or another web server is running.
- In XAMPP → Apache Config → change port to `8080`.

### ❌ `composer install` fails
- Make sure Composer is installed and PHP path is correct.
- Try running `C:\xampp\php\php.exe -v` to confirm PHP is accessible.

### ❌ Database import fails (file too large)
- Open `C:\xampp\php\php.ini` and increase:
  ```ini
  upload_max_filesize = 64M
  post_max_size = 64M
  ```
  Then restart Apache in XAMPP.

### ❌ Tesseract not found / OCR errors
- Confirm Tesseract is installed at `C:\Program Files\Tesseract-OCR\tesseract.exe`.
- Restart XAMPP after installing Tesseract (it needs to read the updated PATH).
- Check `includes/TesseractProvider.php` to see if the path is hardcoded correctly.

### ❌ ImageMagick `convert` not found
- Make sure you checked **"Install legacy utilities"** during installation.
- Restart XAMPP after installing ImageMagick.
- Verify: open PowerShell and run `convert --version`.

### ❌ Emails (OTP) not sending
- The system uses Gmail SMTP. Make sure the Gmail account has **2-Step Verification** enabled and the password in `config/config.php` is a valid **App Password** (not the regular Gmail password).
- Go to https://myaccount.google.com/apppasswords to generate one.

### ❌ Push Notifications not working
- Push notifications **only work over HTTPS** or exactly `http://localhost`.
- They will NOT work on `http://192.168.x.x`. Use Cloudflare Tunnel instead.

---

## 📁 Project Structure (Quick Reference)

```
vrakeit/
├── admin/              # Admin panel pages
├── api/                # Public-facing API endpoints
├── assets/             # CSS, JS, images, uploads
│   └── uploads/        # Public media uploads
├── config/
│   └── config.php      # ⚙️ All app settings (DB, SMTP, APIs)
├── database/
│   ├── vrakeit.sql     # 🗄️ Main database dump (import this!)
│   └── *.sql           # Migration files
├── includes/           # PHP helper files (auth, mailer, OCR, etc.)
├── merchant/           # Merchant portal pages
├── private/
│   └── id_uploads/     # 🔒 Private ID photos (not web-accessible)
├── vendor/             # Composer packages (auto-generated)
├── .env                # 🔑 Secret keys (CREATE THIS MANUALLY)
├── .gitignore
├── composer.json       # PHP dependencies
└── index.php           # App entry point
```

---

*Last updated: September 2026 · VrakeIT v1.0*
