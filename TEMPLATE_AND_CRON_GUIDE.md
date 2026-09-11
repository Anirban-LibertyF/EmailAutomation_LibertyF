# Template Design + Daily Auto-Send (Cron) — Setup Guide

## 1. Custom Letter Template (Tab 5: "Template Design")

You no longer need to edit PHP/CSS to change the letter's look.

1. Export your letter design as a **PNG or JPG** (in Canva: Share → Download → PNG).
   - PDF upload also works, but only if the hosting server has the **Imagick** PHP
     extension with Ghostscript installed. Most shared hosting (including
     Namecheap Stellar/Stellar Plus) does **not** have this, so PNG/JPG is the
     safe choice. If a PDF upload fails, export it as an image instead.
2. Go to the **"5. Template Design"** tab in the dashboard, choose the file, click
   **Upload Design**.
3. The image appears below with 3 colored markers: **College Name** (red),
   **Ref No** (gold), **Date** (blue). Click the button for the field you want to
   place, then click on the image where that text should appear.
4. Click **💾 Save Positions**.
5. Every PDF generated from then on (Manual Send, Excel bulk send, daily cron)
   uses this exact image as the background with only those 3 fields filled in
   dynamically. Everything else (logo, text, colors) is whatever is in your image
   — pixel-identical every time.
6. **Reset to Default Design** reverts to the built-in Liberty Foundation letter
   that ships with this system.

The Ref No field only fills in the **last 3 digits** after `LF/SKILL/2026/` —
if your uploaded image already has "LF/SKILL/2026/" printed on it, place the Ref
No marker right after that text so only the number appears there.

## 2. Daily Auto-Send (300/day, no manual clicking)

`cron_send_daily.php` sends up to 300 pending colleges once a day and updates
their status — the same as clicking "Send All Pending Emails" in Tab 4, just
automatic.

### Setup on Namecheap (cPanel)

1. Log into **cPanel** → find **"Cron Jobs"**.
2. Under **Add New Cron Job**:
   - **Common Settings**: choose "Once Per Day (0 0 * * *)" or set a custom time
     (e.g. `0 6 * * *` = every day at 6 AM).
   - **Command**:
     ```
     /usr/local/bin/php /home/YOUR_CPANEL_USERNAME/public_html/cron_send_daily.php
     ```
     (Replace the path with wherever you uploaded the project. If unsure of the
     PHP path, cPanel's Cron Jobs page usually shows the correct `php` binary
     path, or ask your host's support.)
3. Save. That's it — no code changes needed.

### Notes

- If you upload **4000 colleges** at once (via Excel), the cron job will pick
  300 pending ones each day automatically until the list is exhausted (~14 days
  at Brevo's free 300/day limit).
- You can still use the **"Send All Pending Emails"** button manually any time
  in between — it won't conflict with the cron job, they both just send
  whatever is `pending` up to their own limits.
- Check progress any time from the dashboard's stat cards (Total / Sent /
  Pending / Failed), or the `email_logs` table in phpMyAdmin.
- `cron_send_daily.php` refuses to run over HTTP (browser) — it only works from
  the command line / cron, for security.
