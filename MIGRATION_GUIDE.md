# VrakeIT Complete Setup Guide

This guide will walk you through setting up the VrakeIT project on a new Windows device from scratch.

## Prerequisites
Before you start, make sure you have the exported `vrakeit` project folder (which should include your `vrakeit_backup.sql` database dump).

---

## Step 1: Install XAMPP (Web Server & Database)
1. Download **XAMPP for Windows** from [apachefriends.org](https://www.apachefriends.org/index.html).
2. Install it with the default settings (usually installs to `C:\xampp`).
3. Open the **XAMPP Control Panel** and click **Start** next to both **Apache** and **MySQL**.
4. Copy your `vrakeit` project folder into `C:\xampp\htdocs\`. 
   *(Your project should now live at `C:\xampp\htdocs\vrakeit`)*.

---

## Step 2: Install ImageMagick (Optional, For Local Tesseract OCR)
> **Note:** VrakeIT now uses Gemini AI for OCR by default. ImageMagick is only required if you want a local, offline fallback.

VrakeIT uses ImageMagick to clean and sharpen ID uploads before scanning them for text using Tesseract.
1. Download the **ImageMagick Windows Installer** from [imagemagick.org/script/download.php#windows](https://imagemagick.org/script/download.php#windows).
   *(Look for a file like `ImageMagick-7.1.x-x-Q16-HDRI-x64-dll.exe`)*
2. Run the installer.
3. **CRITICAL STEP:** During installation, make sure you check the box that says **"Install legacy utilities (e.g. convert)"** and **"Add application directory to your system path"**.

---

## Step 3: Install Tesseract OCR (Optional, For Local Fallback)
> **Note:** VrakeIT now uses Gemini AI for OCR by default. Tesseract is only required if you want a local, offline fallback.

VrakeIT uses Tesseract to actually read the text from the uploaded IDs locally.
1. Download the **Tesseract OCR Windows Installer** from [UB-Mannheim's GitHub](https://github.com/UB-Mannheim/tesseract/wiki).
   *(Download the 64-bit installer `tesseract-ocr-w64-setup-5.3.x.exe`)*
2. Run the installer. When asked where to install, leave the default directory as `C:\Program Files\Tesseract-OCR`.
3. **Language Data:** During installation, expand the "Additional language data" dropdown and check **Tagalog** if you want better Philippine ID reading (optional but recommended).

---

## Step 4: Import the Database
1. Open a web browser and go to: `http://localhost/phpmyadmin/`
2. Look at the left sidebar and click **New**.
3. Under "Database name", type `vrakeit` and click **Create**.
4. Click on the new `vrakeit` database on the left sidebar.
5. At the top of the screen, click the **Import** tab.
6. Click **Choose File** and select the `vrakeit_backup.sql` file located inside your `C:\xampp\htdocs\vrakeit` folder.
7. Scroll down and click **Import**.

---

## Step 5: Test the System!
1. Open your browser and go to `http://localhost/vrakeit/`
2. You should see the landing page! 
3. The project is fully functional.

## Step 6: Set up Cloudflare Tunnel (For Mobile & Push Notifications)
To access the system from phones and enable real OS push notifications, you need to expose your local server securely.
1. Open your terminal or powershell.
2. Run your Cloudflare tunnel command:
   ```cmd
   cloudflared tunnel --url http://localhost
   ```
3. Look for the output line that says `https://xxxxxxx.trycloudflare.com` and use that link on your mobile devices!

*Note: Web Push Notifications ONLY work over HTTPS (which Cloudflare provides) or on exactly "localhost". They will not work if you type your raw local IP address (e.g., 192.168.1.10) into your phone.*
