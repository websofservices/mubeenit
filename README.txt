================================================================================
  MTNMANGOES – Online Mango Sales and Management System
  FINAL YEAR PROJECT – USB SUBMISSION PACKAGE
================================================================================

Title       : MTNMANGOES – Online Mango Sales and Management System
Student     : Muhammad Mubeen (2022-GCBM-559)
Program     : BS Information Technology
College     : Govt. Graduate College Muzaffargarh
Department  : Department of Information Technology
Supervisor  : Arslan Munir
Year        : 2026

--------------------------------------------------------------------------------
FOLDER STRUCTURE (as per final submission requirements)
--------------------------------------------------------------------------------

01_Documentation/
   All_Chapters_Combined/
      MTNMANGOES_FYP_Report.docx          → Complete project report
   Chapters_Separately/
      Chapter1 ... Chapter6               → Individual chapter files

02_Source_Code/
   Working web application source code
   README.md                              → Setup & run instructions

03_Presentation/
   MTNMANGOES_FYP_Presentation.pptx       → Final project presentation

04_Synopsis/
   MTNMANGOES_Synopsis.pdf                → Official approved project synopsis

--------------------------------------------------------------------------------
TECHNOLOGY STACK (as per Approved Synopsis)
--------------------------------------------------------------------------------

  SDLC Model     : Waterfall Model
  Frontend       : HTML5, CSS3, JavaScript
  Backend        : PHP (Laravel Framework – planned / pure PHP demo)
  Database       : MySQL (production) / SQLite (portable demo)
  Payments       : Stripe, PayPal (+ COD)
  Tools          : XAMPP, VS Code, phpMyAdmin, GitHub, Postman

--------------------------------------------------------------------------------
HOW TO RUN THE SOURCE CODE
--------------------------------------------------------------------------------

Option A – PHP built-in server (quick demo):
  1. Open terminal inside 02_Source_Code
  2. php database/init.php
  3. cd public && php -S localhost:8000
  4. Browser: http://localhost:8000

Option B – XAMPP (recommended for evaluation):
  1. Copy 02_Source_Code into htdocs as "mtnmangoes"
  2. Create MySQL database "mtnmangoes" (optional – SQLite works by default)
  3. php database/init.php
  4. Open: http://localhost/mtnmangoes/public/

Demo Login (password for all: password123)
  Admin    : admin@mtnmangoes.com
  Farmer   : ali@farmer.com
  Customer : ahmed@customer.com

--------------------------------------------------------------------------------
FUNCTIONAL COVERAGE (Synopsis Requirements)
--------------------------------------------------------------------------------

  [x] Customer registration, login, ordering, and payment
  [x] Farmer product listing and stock management
  [x] Order processing workflow for farmers and admins
  [x] Admin dashboard for platform control
  [x] Inventory and order tracking
  [x] Report generation (order history / dashboard stats)
  [x] Role-based access control

--------------------------------------------------------------------------------
SUBMISSION CHECKLIST
--------------------------------------------------------------------------------

  [x] Complete Project Documentation
  [x] Complete Project Source Code
  [x] Final Project Presentation
  [x] Official Project Synopsis
  [x] All required files properly organized

================================================================================
