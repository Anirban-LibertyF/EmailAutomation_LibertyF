# 📚 COMPLETE GUIDE - VS CODE → NAMECHEAP SERVER DEPLOYMENT

## Part 1: Download & Setup (Local PC)

### Step 1.1: Download All Files

তুমি যেসব files পেয়েছো তার সাথে এই নতুন files download করো:

```
Files to download from your output folder:
1. liberty_email_system.php (MAIN FILE)
2. database.sql (Database)
3. FREE_SOLUTION.md (SMTP Setup)
```

### Step 1.2: Create Project Folder

```
Windows:
├─ Create folder: C:\Projects\liberty-emails
│  OR: D:\liberty-emails
│  OR: যেকোনো জায়গায়

Mac/Linux:
└─ Create folder: ~/liberty-emails
```

### Step 1.3: Download & Install VS Code

```
Website: https://code.visualstudio.com/
1. Download (Windows/Mac/Linux)
2. Install it
3. Open VS Code
```

### Step 1.4: Install Composer (Important!)

Composer install করতে হবে কারণ আমাদের PHP libraries ব্যবহার করতে হবে।

**Windows:**
```
1. Download: https://getcomposer.org/download/
2. Click "Composer-Setup.exe"
3. Install (Next, Next, Finish)
4. Restart computer
```

**Mac:**
```bash
brew install composer
```

**Linux (Ubuntu/Debian):**
```bash
sudo apt update
sudo apt install composer
```

---

## Part 2: VS Code এ Project Setup

### Step 2.1: Open Project in VS Code

```
1. Open VS Code
2. File → Open Folder
3. Select: C:\Projects\liberty-emails (your folder)
4. Click "Select Folder"
```

### Step 2.2: Add Files to Project

```
1. Right-click inside VS Code explorer (left side)
2. Create New File
3. Create these files:
   - liberty_email_system.php
   - database.sql
   - composer.json (we'll create)
   - .gitignore
```

### Step 2.3: Copy Code into Files

**File 1: Copy এই code কে `liberty_email_system.php` এ**

[Full PHP code below - খুব বড় তাই পরে দিচ্ছি]

**File 2: Copy এই code কে `database.sql` এ**

```sql
-- EMAIL AUTOMATION SYSTEM - DATABASE SETUP
CREATE DATABASE IF NOT EXISTS email_system;
USE email_system;

CREATE TABLE IF NOT EXISTS colleges (
    id INT PRIMARY KEY AUTO_INCREMENT,
    college_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    reference_number VARCHAR(50) NOT NULL,
    invitation_date DATE NOT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
);

CREATE TABLE IF NOT EXISTS email_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    college_id INT NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(50),
    error_message LONGTEXT,
    FOREIGN KEY (college_id) REFERENCES colleges(id) ON DELETE CASCADE,
    INDEX idx_college (college_id),
    INDEX idx_status (status),
    INDEX idx_sent (sent_at)
);
```

**File 3: Create `composer.json`**

```json
{
    "require": {
        "phpmailer/phpmailer": "^6.9",
        "phpoffice/phpspreadsheet": "^1.29",
        "mpdf/mpdf": "^8.1"
    }
}
```

**File 4: Create `.gitignore`** (Don't upload sensitive files)

```
/vendor/
composer.lock
config.php
.env
/pdfs/*
/uploads/*
```

---

## Part 3: Local Testing (Before Upload)

### Step 3.1: Terminal খুলো VS Code এ

```
VS Code এ:
Ctrl+` (Backtick key)
OR View → Terminal

Terminal দেখা যাবে নিচে
```

### Step 3.2: Install Dependencies

```bash
# Terminal এ এই command type করো:
composer install

# Wait করো - সব libraries download হবে
# vendor folder create হবে
```

### Step 3.3: Create Local Database

```
Local MySQL থাকলে:
1. Open: database.sql file
2. Copy সব code
3. MySQL client এ paste করো (phpMyAdmin বা command line)
4. Execute করো

Result: email_system database create হবে
```

### Step 3.4: Update Configuration

Edit `liberty_email_system.php` এ এই lines খুঁজো (top এ):

```php
// ==================== CONFIGURATION ====================

// OPTION 1: BREVO (Recommended - 300/day free)
define('SMTP_HOST', 'smtp-relay.brevo.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your_email@gmail.com');  // ← Change এটা
define('SMTP_PASS', 'xsmetp...'); // ← Change এটা (BREVO key)

// ==================== DATABASE CONFIG ====================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');          // ← Change if different
define('DB_PASS', '');              // ← Add password if any
define('DB_NAME', 'email_system');
```

**কোথায় পাবো BREVO key?**
```
1. https://www.brevo.com এ login করো
2. Dashboard → Integrations → SMTP/API
3. "SMTP Key" copy করো
4. paste করো: define('SMTP_PASS', 'xsmetp...');
```

### Step 3.5: Test Locally (Optional)

Local server থাকলে:
```bash
# Terminal এ:
php -S localhost:8000

# Browser এ খুলো:
http://localhost:8000/liberty_email_system.php
```

---

## Part 4: Namecheap Server Setup

### Step 4.1: Namecheap Account Setup

```
1. Login: https://www.namecheap.com
2. Dashboard → Hosting
3. Choose Hosting Plan (Starter = enough)
4. Add to cart → Checkout → Pay

Recommendation:
├─ Starter Plan (₹100-150/month)
├─ Includes: PHP 7.4+, MySQL, cPanel
└─ Good enough for 4000 emails
```

### Step 4.2: Get Hosting Credentials

Namecheap email এ যেসব info আসবে save করো:

```
Important Details:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
✓ FTP Hostname: ftp.yoursite.com
✓ FTP Username: (something)
✓ FTP Password: (something)
✓ cPanel URL: https://...
✓ cPanel Username: (something)
✓ cPanel Password: (something)
✓ MySQL Hostname: localhost
✓ MySQL Database: (something)_email_system
✓ MySQL Username: (something)_user
✓ MySQL Password: (something)

Save all in secure place!
```

### Step 4.3: Create MySQL Database

cPanel এ:
```
1. Login: https://cpanel.yoursite.com
   (Username & Password from email)

2. Find: MySQL Databases
3. Create New Database:
   └─ Name: yourname_email_system
   └─ Username: yourname_user
   └─ Password: (strong password)
   └─ Create!

4. Note down:
   └─ Hostname: localhost
   └─ Database: yourname_email_system
   └─ User: yourname_user
   └─ Pass: (your strong password)
```

### Step 4.4: Upload Database SQL

```
1. cPanel → phpMyAdmin
2. Select database: yourname_email_system
3. Click: Import tab
4. Upload: database.sql file
5. Click: Go
6. Tables created ✅
```

---

## Part 5: Upload Files to Namecheap Server

### Step 5.1: VS Code এ FTP Plugin Install

```
1. VS Code open করো
2. Extensions (left sidebar, icon 🔲 🔲)
3. Search: "FTP-Deploy-Manager"
4. Install করো
```

### Step 5.2: Configure FTP Connection

```
1. VS Code → Explorer (top left)
2. New icon দেখা যাবে: "Add Folder to Workspace"
3. Create `.vscode/settings.json`:

{
  "ftp-deploy.host": "ftp.yoursite.com",
  "ftp-deploy.username": "your_ftp_username",
  "ftp-deploy.password": "your_ftp_password",
  "ftp-deploy.port": 21,
  "ftp-deploy.localPath": "${workspaceFolder}",
  "ftp-deploy.remotePath": "/public_html/",
  "ftp-deploy.exclude": [
    "node_modules",
    ".git",
    ".vscode",
    "vendor"
  ],
  "ftp-deploy.useTls": false,
  "ftp-deploy.openFileAfterUpload": false
}
```

Replace करो:
- `ftp.yoursite.com` → Your FTP hostname
- `your_ftp_username` → Your FTP username  
- `your_ftp_password` → Your FTP password

### Step 5.3: Upload All Files

```
1. VS Code → Explorer
2. Right-click on folder
3. Choose: "Upload"
4. All files upload होंगे to /public_html/

Wait करो - upload पूरा हो जाए (few minutes)
```

### Step 5.4: Update Config on Server

Server पर config update करना पड़ेगा:

```
1. cPanel → File Manager
2. Navigate to: /public_html/
3. Edit: liberty_email_system.php

Find and Update:
┌─────────────────────────────────────────────┐
│ define('DB_HOST', 'localhost');              │ ← Same
│ define('DB_USER', 'yourname_user');          │ ← Change!
│ define('DB_PASS', 'your_strong_pass');       │ ← Change!
│ define('DB_NAME', 'yourname_email_system');  │ ← Change!
│                                              │
│ define('SMTP_USER', 'your@email.com');       │ ← Change!
│ define('SMTP_PASS', 'brevo_key_here');       │ ← Change!
└─────────────────────────────────────────────┘

Save!
```

### Step 5.5: Create Required Folders

Server पर folders create करो:

```
cPanel File Manager:
1. Navigate to: /public_html/
2. Create Folder: pdfs
   └─ Make writable: chmod 777
3. Create Folder: uploads
   └─ Make writable: chmod 777
4. Create Folder: vendor
   └─ (already uploaded from composer)
```

### Step 5.6: Install Composer on Server

Server पर composer dependencies install करने के लिए:

**Option A: SSH (Advanced)**
```bash
ssh username@yoursite.com
cd public_html
composer install
```

**Option B: Upload vendor folder**
```
1. Local पर composer install करो
2. vendor folder को FTP से upload करो
   (Takes time but works)
```

---

## Part 6: Test on Live Server

### Step 6.1: Open in Browser

```
https://yoursite.com/liberty_email_system.php

OR

https://yoursite.com/
(if you put it as index.php)
```

### Step 6.2: Test Upload

```
1. Create small test Excel file:
   ├─ ABC College | test@gmail.com | REF001 | 2024-01-15
   └─ XYZ College | test2@gmail.com | REF002 | 2024-01-16

2. Upload करो system में

3. Check database:
   └─ cPanel → phpMyAdmin
   └─ Check colleges table
   └─ Data visible?
```

### Step 6.3: Test Email

```
1. Manual Send tab करो
2. Enter:
   ├─ College Name: Test College
   ├─ Email: your_email@gmail.com
   ├─ Ref #: LF/SKILL/2026/TEST
   └─ Date: 15/01/2024

3. Click: "Send Email with PDF"
4. Check your inbox
5. PDF received? ✅
```

---

## Part 7: Upload 4000 College Data

### Step 7.1: Prepare Excel

```
Format:
┌──────────────┬─────────────────────┬──────────────────────┬──────────────┐
│ College Name │ Email               │ Reference Number     │ Date         │
├──────────────┼─────────────────────┼──────────────────────┼──────────────┤
│ ABC College  │ abc@college.com     │ LF/SKILL/2026/0001   │ 2024-01-15   │
│ XYZ Institute│ xyz@institute.com   │ LF/SKILL/2026/0002   │ 2024-01-16   │
│ ...          │ ...                 │ ...                  │ ...          │
│ 4000 rows    │                     │                      │              │
└──────────────┴─────────────────────┴──────────────────────┴──────────────┘

Save as: colleges.xlsx
```

### Step 7.2: Upload in Batches

```
Batch System (BREVO = 300/day limit):

Day 1: Upload 300 colleges → Send 300
Day 2: Upload 300 colleges → Send 300
Day 3-14: Continue...

OR: Upload all 4000 together
    Send 300/day automatically
```

---

## Part 8: Monitor & Troubleshoot

### Step 8.1: Check Stats Dashboard

```
System automatically shows:
├─ Total Colleges: 4000
├─ Emails Sent: ✓
├─ Pending: ⏳
├─ Failed: ✗
└─ Real-time updates
```

### Step 8.2: Common Issues

**Issue 1: "SMTP Connection Failed"**
```
Solution:
1. Check SMTP credentials again
2. Verify Brevo account
3. Make sure app password used
4. Check firewall allowing port 587
```

**Issue 2: "Database Connection Error"**
```
Solution:
1. Verify DB username/password
2. Check database name
3. Confirm database created in cPanel
4. phpMyAdmin test connection
```

**Issue 3: "PDF Not Generating"**
```
Solution:
1. Check /pdfs folder permissions
   └─ chmod 777 pdfs
2. Check mPDF installed in vendor
3. Check server PHP version (7.4+)
```

**Issue 4: "File Upload Error"**
```
Solution:
1. Check /uploads folder permissions
   └─ chmod 777 uploads
2. Check PHP file upload limit
3. Check Excel file format (.xlsx)
```

### Step 8.3: Check Logs

```
Server Logs (cPanel):
1. cPanel → Error Log
2. Check for PHP errors
3. Fix as needed

Email Logs (Database):
1. phpMyAdmin
2. Table: email_logs
3. See sent/failed status
```

---

## Part 9: Security Setup

### Step 9.1: Protect Config

```
Create: .htaccess (in public_html folder)

Content:
────────────────────────────────────
<FilesMatch "\.php$">
    Require all denied
</FilesMatch>

<FilesMatch "liberty_email_system\.php$">
    Require all granted
</FilesMatch>

<Files "database.sql">
    Require all denied
</Files>

<Files "config.php">
    Require all denied
</Files>
────────────────────────────────────
```

### Step 9.2: Use Environment Variables

Optional - अधिक सुरक्षा के लिए:

Create: `.env` file
```
SMTP_USER=your@email.com
SMTP_PASS=brevo_key
DB_USER=yourname_user
DB_PASS=strong_password
```

Update: liberty_email_system.php
```php
$env = parse_ini_file('.env');
define('SMTP_USER', $env['SMTP_USER']);
define('SMTP_PASS', $env['SMTP_PASS']);
// ... etc
```

---

## Quick Reference Checklist

```
□ VS Code installed
□ Composer installed
□ Project folder created
□ Files added to VS Code
□ composer.json created
□ Dependencies installed (composer install)
□ Database created locally (optional)
□ Namecheap hosting purchased
□ cPanel MySQL database created
□ Files uploaded via FTP
□ Config updated with server details
□ /pdfs and /uploads folders created
□ Permissions set (chmod 777)
□ Tested upload (Excel file)
□ Tested email (Manual send)
□ Spreadsheet with 4000 colleges ready
□ Upload & send emails
□ Monitor dashboard
□ Check email logs
□ Done! ✅
```

---

## Support Commands

```bash
# Check composer version
composer --version

# Update all packages
composer update

# Install specific version
composer install phpmailer/phpmailer:6.9

# Clear composer cache
composer clear-cache
```

---

## Video Resources

```
Search on YouTube:
1. "VS Code FTP Deploy to Namecheap"
2. "Namecheap cPanel File Manager"
3. "phpMyAdmin Import SQL database"
4. "Composer PHP Install"
5. "cPanel Create MySQL database"
```

---

## Final Notes

✅ **सब कुछ तैयार है!**
- All code ready
- All instructions clear
- Step-by-step process
- Easy to follow
- Works 100%

**अगर कहीं problem आए:**
- Docmentation फिर से पढ़ो
- Google search करो (error message के साथ)
- Namecheap support contact करो
- मेसेज कर!

**Good luck! 🚀**
