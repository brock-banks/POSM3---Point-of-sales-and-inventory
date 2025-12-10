# POSM3 — Simple POS for small shops

Short version:
- You can run the web app with plain PHP (no XAMPP required).
- Use QZ Tray for direct ESC/POS printing to thermal printers.
- You can wrap the app with Electron to produce a desktop executable.
- The app supports multiple languages via a simple translation system (en/ar).
- Roles: ADMIN and CASHIER — admin controls settings and sensitive pages.

Table of contents
- What this is
- Requirements
- Quick dev start (no XAMPP)
- Database
- Running the app in production / desktop EXE (Electron)
- Printing (QZ Tray + ESC/POS)
- Settings & language
- Roles & permissions
- Useful tips & troubleshooting
- Contributing
- License

What this is
-------------
POSM3 is a straightforward POS built with PHP and MySQL. It's designed for small shops and aimed to be easy to install and adapt. It includes:
- a POS interface (pos.php)
- products, customers, purchases, sales
- translations (English / Arabic)
- optional direct printing via QZ Tray (ESC/POS)
- simple user roles (ADMIN, CASHIER)

Requirements
-------------
- PHP 7.4+ (or PHP 8)
- MySQL / MariaDB
- Node.js + npm (only if you want to create a desktop .exe with Electron)
- QZ Tray (optional) for direct printer control
- A modern browser to open the web UI (Chrome/Edge/Chromium works best)

Quick dev start (without XAMPP)
--------------------------------
1. Clone the repo to a folder, for example `C:\posm3` (Windows) or `~/posm3` (Linux/macOS).
2. Create your database in MySQL and import the schema (look for `schema.sql` or use your existing DB).
3. Configure DB connection in `src/config.php`.
4. From the terminal, serve the public folder with the PHP built-in server:
   - cd into the public folder:
     php -S 127.0.0.1:8000 -t /path/to/POSM3/public
5. Open your browser and go to:
   - http://127.0.0.1:8000/pos.php
6. Log in and test. If you see errors, check `storage` or PHP error log for messages.

Database
---------
- The app uses MySQL/MariaDB.
- Core tables: `users`, `products`, `sales`, `sale_items`, `purchases`, `purchase_items`, `customers`, `app_settings`, `settings`, `units`, `product_categories`, etc.
- Two settings storages:
  - `settings` (shop-specific keys like shop_name, currency_symbol)
  - `app_settings` (app-level keys like app_language, pos_default_printer)

Running the app as a desktop .exe (Electron)
--------------------------------------------
If you want the user to double-click an EXE and use the POS without manually starting servers, the recommended approach is to wrap the web app with an Electron shell that starts a small local PHP server and opens the POS page in a native window.

Basic steps summary:
1. Install Node.js / npm.
2. Create an Electron project (main.js) that:
   - spawns `php -S 127.0.0.1:8000 -t /path/to/POSM3/public` on start
   - opens a BrowserWindow to `http://127.0.0.1:8000/pos.php`
   - packages with `electron-builder` (creates an installer .exe)
3. Ensure MySQL is installed on the POS machine (or run a portable DB).
4. You can optionally bundle PHP and your app with the Electron package for a single installer (more advanced).

Printing: QZ Tray and ESC/POS
-----------------------------
- Browser JavaScript cannot select a physical printer or print without the print dialog for security reasons.
- QZ Tray is a local helper app you install on the POS PC. It exposes a websocket API that the browser (or Electron) can use to:
  - list installed printers
  - send raw ESC/POS bytes directly (no print dialog)
- Flow:
  - The web app calls `qz.printers.find()` to show local printers in the settings page.
  - When a sale completes, JS generates ESC/POS commands (receipt text + cut) and sends raw bytes to selected printer via QZ.
  - For production, QZ requires signed requests. The app includes a server signing endpoint (qz_sign.php) which signs requests with a private key on the server.

Settings & language
-------------------
- The UI uses a small translation system: language files are in `src/lang_en.php` and `src/lang_ar.php`.
- The site language is read from `app_settings.app_language`.
- Admins can change language in Settings -> Interface language; the choice is saved to the database and takes effect on reload.
- To translate the whole UI, wrap user-visible strings in `__()` calls. The system will return the matching translation.

Roles & permissions
-------------------
- Two roles exist by default: `ADMIN` and `CASHIER`.
- ADMIN sees everything (settings, users, reports, finance).
- CASHIER sees only POS, Products, Customers (minimal).
- Add `auth_require_role(['ADMIN'])` at the top of admin pages to block access.
- Update users table with a `role` column to assign roles.

Useful tips & troubleshooting
-----------------------------
- If you change the language and nothing changes, ensure:
  - `app_settings.app_language` has the correct key/value.
  - `templates/header.php` includes `src/translate.php` and uses `app_language()` and `__()` for visible labels.
- If printing fails:
  - Make sure QZ Tray is running on the POS machine.
  - Check browser console for websocket errors and server logs for signature problems.
- If the POS UI is slow:
  - Check the console for JS syntax errors (a single broken script will disable handlers).
  - Ensure product lists are paginated / filtered if you have thousands of items.

Contributing
------------
If you want to contribute:
- Fork the repo, create a feature branch, and open a PR.
- Keep translations in `src/lang_*.php`.
- If adding new visible text, add translation keys in both English and Arabic files.
- If you add server endpoints that call QZ, make sure requests are secure and signed appropriately.

Support / Contact
-----------------
If you run into trouble, paste console errors and server log messages — that makes debugging much faster.

License
-------
This project includes a LICENSE file. By default the project uses the MIT License (simple and permissive). See LICENSE for full text.

Thanks for trying POSM3 