# ⚡ QUICK START - 3 SIMPLE STEPS

## 🎯 Step-by-Step (Just Follow!)

---

## 📥 DOWNLOAD & SETUP (5 minutes)

```
1️⃣  Download All Files:
    ✓ liberty_email_system.php
    ✓ database.sql
    ✓ NAMECHEAP_DEPLOYMENT_GUIDE.md
    ✓ FREE_SOLUTION.md

2️⃣  Install Software (One-time):
    Windows:
    ├─ VS Code: https://code.visualstudio.com/
    ├─ Composer: https://getcomposer.org/download/
    └─ PHP (optional for local testing)
    
    Mac/Linux:
    ├─ brew install vscode
    ├─ brew install composer
    └─ Already have PHP usually

3️⃣  Create Project Folder:
    C:\Projects\liberty-emails
    (or any location you choose)

4️⃣  Copy files into that folder:
    liberty-emails/
    ├─ liberty_email_system.php
    ├─ database.sql
    ├─ composer.json (create new)
    └─ README.md (create new)
```

---

## 🔧 VS CODE SETUP (5 minutes)

```
1️⃣  Open VS Code

2️⃣  File → Open Folder → Select liberty-emails

3️⃣  Install Extensions:
    Left sidebar → Extensions (icon: 🔲🔲)
    Search & Install:
    ├─ PHP Intelephense (for PHP help)
    ├─ SQL (for SQL help)
    ├─ FTP-Deploy-Manager (for upload)
    └─ Thunder Client (for testing APIs)

4️⃣  Create composer.json:
    Right-click in Explorer → New File → composer.json
    
    Paste:
    ───────────────────────────────────────
    {
        "require": {
            "phpmailer/phpmailer": "^6.9",
            "phpoffice/phpspreadsheet": "^1.29",
            "mpdf/mpdf": "^8.1"
        }
    }
    ───────────────────────────────────────

5️⃣  Open Terminal (Ctrl+`)
    
    Type:
    composer install
    
    Wait... (will download ~50MB libraries)

6️⃣  Done! ✅
    vendor folder will be created
```

---

## 🌐 NAMECHEAP SETUP (10 minutes)

```
1️⃣  Buy Hosting:
    https://www.namecheap.com/hosting
    Plan: Starter (₹100-150/month)
    Add to cart → Pay → Done

2️⃣  Receive Email:
    Save these details:
    ├─ FTP Hostname: ftp.yoursite.com
    ├─ FTP Username: (something)
    ├─ FTP Password: (save securely!)
    ├─ cPanel URL: https://...
    ├─ cPanel Username: (something)
    └─ cPanel Password: (save securely!)

3️⃣  Create Database:
    Open cPanel (URL from email)
    → MySQL Databases
    → Create New Database
    
    Fill:
    ├─ Name: yourname_email_system
    ├─ User: yourname_user
    ├─ Password: (strong!)
    └─ Create!
    
    Note down credentials!

4️⃣  Create Tables:
    cPanel → phpMyAdmin
    → Select: yourname_email_system
    → Import tab
    → Upload: database.sql
    → Go
    
    Tables created ✅

5️⃣  Configure FTP in VS Code:
    In your project folder create:
    .vscode/settings.json
    
    Paste (UPDATE YOUR VALUES):
    ───────────────────────────────────────
    {
      "ftp-deploy.host": "ftp.yoursite.com",
      "ftp-deploy.username": "your_ftp_user",
      "ftp-deploy.password": "your_ftp_pass",
      "ftp-deploy.port": 21,
      "ftp-deploy.localPath": "${workspaceFolder}",
      "ftp-deploy.remotePath": "/public_html/"
    }
    ───────────────────────────────────────

6️⃣  Create Folders on Server:
    cPanel → File Manager
    /public_html/ → Create Folders:
    ├─ pdfs (chmod 777)
    ├─ uploads (chmod 777)
    └─ vendor (for libraries)
```

---

## 📤 UPLOAD & CONFIG (5 minutes)

```
1️⃣  Upload Files:
    VS Code Explorer → Right-click → Upload
    (Waits 2-5 minutes for upload)

2️⃣  Update Config:
    cPanel → File Manager → Edit liberty_email_system.php
    
    Find & Replace (IMPORTANT!):
    
    define('DB_USER', 'yourname_user');        ← Your DB user
    define('DB_PASS', 'your_db_password');     ← Your DB password
    define('DB_NAME', 'yourname_email_system');← Your DB name
    
    define('SMTP_USER', 'your@email.com');     ← BREVO email
    define('SMTP_PASS', 'xsmetp...');          ← BREVO key
    
    Save!

3️⃣  Get BREVO Key (if not done):
    https://www.brevo.com → Sign up (FREE)
    Dashboard → Integrations → SMTP/API
    Copy SMTP Key → Paste in config above

4️⃣  Test System:
    Browser: https://yoursite.com/liberty_email_system.php
    
    Should see dashboard! ✅
```

---

## ✉️ SEND EMAILS (Easy!)

```
1️⃣  Manual Test:
    Dashboard → Tab: "Manual Send"
    Fill:
    ├─ College Name: Test College
    ├─ Email: your@email.com
    ├─ Ref #: LF/SKILL/2026/TEST
    └─ Date: 15/01/2024
    
    Click: Send
    Check inbox ✅

2️⃣  Batch Upload:
    Dashboard → Tab: "Upload Excel"
    
    Excel format:
    ┌──────────────┬─────────────────┬──────────────────┬────────────┐
    │ College Name │ Email           │ Reference Number │ Date       │
    ├──────────────┼─────────────────┼──────────────────┼────────────┤
    │ ABC College  │ abc@college.com │ LF/SKILL/2026/01 │ 2024-01-15 │
    │ XYZ Institute│ xyz@college.com │ LF/SKILL/2026/02 │ 2024-01-16 │
    │ ... 4000 rows
    └──────────────┴─────────────────┴──────────────────┴────────────┘
    
    Upload file
    System imports all ✅

3️⃣  Send All:
    Dashboard → Tab: "Send Emails"
    Click: "Send All Pending Emails"
    
    System auto-sends:
    ├─ 300/day (BREVO limit)
    ├─ 1 second delay between
    ├─ Generates PDF each time
    ├─ Tracks status
    └─ Takes 14 days for 4000

4️⃣  Monitor:
    Watch dashboard stats:
    ├─ Total: 4000
    ├─ Sent: Growing
    ├─ Pending: Decreasing
    └─ Failed: 0 (hopefully!)
```

---

## 🚨 TROUBLESHOOTING

```
"SMTP Connection Failed"
→ Check BREVO credentials
→ Verify email/key correct
→ Re-copy key from BREVO

"Database Connection Error"
→ Check DB username/password in config
→ Verify database created in cPanel
→ Try using phpMyAdmin to test

"PDF Not Generating"
→ Check /pdfs folder permissions
→ chmod 777 /pdfs (in cPanel)
→ Verify vendor/mpdf exists

"File Upload Error"
→ Check /uploads folder permissions
→ chmod 777 /uploads (in cPanel)
→ Verify file is .xlsx format

"Can't see dashboard"
→ Check URL: https://yoursite.com/liberty_email_system.php
→ Wait 5 minutes for server to start
→ Check cPanel Error Log

Still stuck?
→ Read NAMECHEAP_DEPLOYMENT_GUIDE.md (detailed)
→ Google the error message
→ Namecheap support chat
→ Send me message!
```

---

## 📋 FINAL CHECKLIST

```
✅ Software Installed:
   □ VS Code
   □ Composer
   □ FTP Plugin for VS Code

✅ Project Setup:
   □ Folder created
   □ Files copied
   □ composer.json created
   □ composer install done

✅ Namecheap:
   □ Hosting purchased
   □ cPanel database created
   □ database.sql imported
   □ /pdfs & /uploads folders created
   □ Permissions set (chmod 777)

✅ Configuration:
   □ DB credentials updated
   □ SMTP credentials updated
   □ .vscode/settings.json created with FTP details
   □ Files uploaded to server

✅ Testing:
   □ Dashboard loads (https://yoursite.com/...)
   □ Manual email sent successfully
   □ PDF received

✅ Ready to Send 4000:
   □ Excel file prepared with 4000 colleges
   □ Upload via dashboard
   □ Send all emails
   □ Monitor progress
   □ Done! 🎉
```

---

## 📞 SUPPORT

```
Problems?

1. Re-read the DEPLOYMENT guide
2. Google the error message
3. Check Namecheap support:
   → Chat: cPanel Settings
   → Email: Support@namecheap.com
4. Message me!
```

---

**Good luck! Everything is ready! 🚀**

Just follow steps → Upload → Send!

**That's it! 💪**
