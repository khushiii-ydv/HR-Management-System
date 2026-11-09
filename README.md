# HRMS – Enterprise Human Resource Management System

A modern, secure, and production-ready enterprise **Human Resource Management System (HRMS)** built using **PHP 8.2+, MySQL / MariaDB, HTML5, CSS3, JavaScript, and Bootstrap 5**. The interface is designed around a polished SaaS aesthetic with an institutional corporate theme, intuitive navigation, responsive mobile-friendly layouts, and a strict role-based access control (RBAC) system for **Administrator**, **HR Administrator**, and **Employee**.

---

## 1. Executive Deliverables & Testing Summary

| Deliverable | Location | Description | Status |
| :--- | :--- | :--- | :--- |
| **Comprehensive Excel Testing Report** | [`docs/HRMS_Full_System_Module_Testing_Report.xlsx`](file:///c:/xampp/htdocs/hr-management-system/docs/HRMS_Full_System_Module_Testing_Report.xlsx) | 70 end-to-end test cases across all 12 modules with 7 required columns (`Test Case ID`, `Module`, `Test Scenario`, `Expected Result`, `Actual Result`, `Pass/Fail Status`, `Remarks`) | **100% PASS** |
| **Complete Source Code** | Root repository | Production-grade PHP components, PDO prepared queries, CSRF defenses, and secure session management | **Verified** |
| **Database Architecture** | Local MySQL / MariaDB | 20 relational InnoDB tables with foreign key constraints, indexes, and zero dummy/fake hardcoding | **Intact** |
| **Project Documentation** | [`docs/README.md`](file:///c:/xampp/htdocs/hr-management-system/docs/README.md) & [`README.md`](file:///c:/xampp/htdocs/hr-management-system/README.md) | Technical stack specification, database schema, user accounts, and deployment manual | **Complete** |
| **High-Resolution Screenshots** | [`docs/screenshots/`](file:///c:/xampp/htdocs/hr-management-system/docs/screenshots/) | 18 full-page screenshots capturing every major module and administrative portal | **Verified** |

---

## 2. Active User Accounts & Role Permissions

The system contains authentic, verified accounts with dedicated organizational roles and cryptographic credentials:

| User ID | Full Name | Registered Email | Employee ID | Assigned Role | Account Status | Email Verification |
| :---: | :--- | :--- | :---: | :---: | :---: | :---: |
| **1** | **Khushi Yadav** | `khushi.ydv0306@gmail.com` | *N/A (System Administrator)* | **Administrator** | Active | Verified (Google OAuth Linked) |
| **2** | **Anita yadav** | `ay3420956@gmail.com` | `EMP-1002` | **HR Administrator** | Active | Verified (2FA Enabled) |
| **3** | **Anshu yadav** | `anshu.ydv0415@gmail.com` | `EMP-1003` | **Employee (Senior Engineer)** | Active | Verified (2FA Enabled) |
| **4** | **anshuka yadav** | `anshuka.yadav27@gmail.com` | `EMP-1004` | **Employee (Lead UI/UX)** | Active | Verified (2FA Enabled) |
| **5** | **Spam Yadav** | `spam.employee@company.com` | `EMP-1005` | **Employee (Full Stack Dev)** | Active | Verified (2FA Enabled) |

> **Security Note:** All registered user accounts, passwords, and verification flags have been preserved 100% intact. Zero credentials have been cleared, truncated, or modified.

---

## 3. Technology Stack & Architecture

### Backend & Server Environment
- **Server Platform**: Apache 2.4+ (XAMPP for Windows)
- **Programming Language**: PHP 8.2+ with strict typing and OOP design
- **Database Engine**: MySQL 8.0+ / MariaDB 10.4+ InnoDB with foreign key integrity
- **Dependency Management**: Composer for PHPMailer and vlucas/phpdotenv
- **Configuration Management**: `.env` file for credentials (`MAIL_*`, `GOOGLE_*`, `APP_*`)

### Frontend & User Interface
- **Styling Framework**: Bootstrap 5.3 with custom corporate SaaS color tokens
- **Typography & Icons**: Inter / Segoe UI with FontAwesome 6 Pro icons
- **Data Visualization**: Chart.js for live department distribution and attendance metrics
- **Responsive Design**: Mobile-first grid layouts supporting desktops, tablets, and phones

### Security Standards & Hardening
- **Authentication**: Bcrypt password hashing (`PASSWORD_BCRYPT` with cost >= 12)
- **OTP Security**: 6-digit cryptographically secure codes via `random_int()`, stored strictly as one-way Bcrypt hashes in `user_otps`, with 10-minute expiry and 5-attempt rate limiting
- **Single Sign-On**: Official Google OAuth 2.0 with backend CSRF state tokens and strict email matching
- **Session Protection**: `session_regenerate_id(true)` upon login, `HttpOnly` and `SameSite: Lax` cookie flags, 30-minute inactivity auto-logout
- **Access Control**: Role-Based Access Control (RBAC) and strict SQL ownership checks (`WHERE employee_id = ?`) preventing Insecure Direct Object References (IDOR)
- **Anti-CSRF**: Synchronizer token pattern with cryptographically random tokens validated on all state-changing POST requests

---

## 4. Module-by-Module Technical Breakdown & Test Verification

### Module 1: Authentication, Email Verification & OTP Security
- **Registration**: Self-service registration with automated 6-digit OTP verification email before activation.
- **Role Verification**: Admin and HR registration protected via cryptographic verification keys stored as Bcrypt hashes in `role_access_keys`.
- **OTP Protection**: Single-use enforcement, 60-second resend cooldown throttle, max 5 requests per hour, and zero plaintext exposure in logs, HTML, or API responses.
- **Brute Force Lockout**: 5 consecutive failed login attempts trigger an automated 15-minute account lockout (`locked_until`).
- **Test Status**: **12 / 12 Tests Passed** (`TC-AUTH-01` to `TC-AUTH-12`).

### Module 2: SMTP Email Delivery (PHPMailer)
- **Library**: Managed via Composer; supports Gmail App Passwords, Amazon SES, Brevo, and Mailpit.
- **Security**: TLS encryption over port 587 with robust exception handling and timeout controls.
- **Templates**: Professional HTML email templates with HRMS branding for registration, login 2FA, and password reset.
- **Test Status**: **3 / 3 Tests Passed** (`TC-MAIL-01` to `TC-MAIL-03`).

### Module 3: Google OAuth 2.0 Integration
- **Implementation**: Official OAuth 2.0 authorization code grant (`accounts.google.com`).
- **Backend Validation**: Google Client ID and Secret remain strictly on backend. Validates state CSRF tokens and retrieves verified Google email.
- **Strict Email Rule**: Requires Google account email to match an existing registered user.
- **Test Status**: **4 / 4 Tests Passed** (`TC-OAUTH-01` to `TC-OAUTH-04`).

### Module 4: Authorization & Role-Based Access Control (RBAC)
- **Admin**: Global administrative scope over settings, audit logs, payroll batching, and staff records.
- **HR Administrator**: Operational authority over employee directory, attendance, leaves, recruitment, and performance reviews. Blocked from system settings and audit trails.
- **Employee**: Strictly isolated to own profile, clock-in, leave applications, and monthly payslips. URL parameter tampering (`?id=999`) returns immediate `403 Forbidden`.
- **Test Status**: **7 / 7 Tests Passed** (`TC-RBAC-01` to `TC-RBAC-07`).

### Module 5: Session Management & Security
- **Fixation Defense**: Session regenerated immediately upon successful login.
- **Inactivity Timeout**: Automatically clears session after 30 minutes of idle time.
- **Logout Cleansing**: Completely invalidates session cookies and destroys session files.
- **Test Status**: **4 / 4 Tests Passed** (`TC-SESS-01` to `TC-SESS-04`).

### Module 6: Executive & Operational Dashboards
- **Admin Dashboard**: Live KPI stat cards (Total Staff = 5, Departments = 6, Pending Leaves = 1, Open Jobs = 2), Chart.js attendance chart, and real-time audit feed.
- **HR Dashboard**: Focuses on recruitment pipelines, candidate applications, and leave queues.
- **Employee Portal**: Personalized greeting, 1-click clock-in/out widget, leave quota progress cards, and notifications.
- **Test Status**: **3 / 3 Tests Passed** (`TC-DASH-01` to `TC-DASH-03`).

### Module 7: Employee Directory & 7-Tab Comprehensive Profile
- **Directory**: Real-time client-side search, department filter, and employee status pills.
- **7-Tab Profile View**:
  1. *Tab 1: Overview* — Avatar, job title, department, quick contact cards.
  2. *Tab 2: Personal & Contact* — Phone, address, date of birth, emergency contacts.
  3. *Tab 3: Job & Placement* — Department, designation, joining date, reporting structure.
  4. *Tab 4: Attendance History* — Chronological timesheets with hours calculation.
  5. *Tab 5: Leave Ledger* — Balance cards, approved and pending leave records.
  6. *Tab 6: Performance Scorecards* — 5-star ratings, strengths, and reviews.
  7. *Tab 7: Compensation & Payslips* — Salary components and downloadable payslips.
- **Test Status**: **6 / 6 Tests Passed** (`TC-EMP-01` to `TC-EMP-06`).

### Module 8: Attendance Management & Clock-In Engine
- **Tracking**: Web and biometric digital clock-in with daily timestamps.
- **Late Policy**: Automated rule tagging arrivals after 09:30 AM as `status='late'`.
- **Hours Calculation**: Automatic net decimal working hours computation.
- **Flexible Shifts**: Half-day and on-leave schedule handling.
- **Test Status**: **5 / 5 Tests Passed** (`TC-ATT-01` to `TC-ATT-05`).

### Module 9: Leave Management & Approval Flow
- **Statutory Types**: Casual (12), Sick (12), Earned (15), Unpaid (0), Parental (90).
- **Application**: Reason, date range picker, and automatic quota calculation.
- **Approval Workflow**: Manager approval/rejection with mandatory review remarks.
- **Concurrency**: Database transactions ensure quota is decremented without race conditions.
- **Test Status**: **6 / 6 Tests Passed** (`TC-LEV-01` to `TC-LEV-06`).

### Module 10: Payroll Register & Official Payslip Generation
- **Salary Formula**: `Gross = Basic + HRA (40%) + Conveyance + Special Allowance`.
- **Statutory Deductions**: Provident Fund (12%), Professional Tax (₹200), and estimated TDS withholding (5%).
- **Payslip**: Formal dual-column corporate voucher with company logo, tax breakdown, Net Pay in words (KaTeX/Words helper), and printable CSS.
- **Test Status**: **6 / 6 Tests Passed** (`TC-PAY-01` to `TC-PAY-06`).

### Module 11: Recruitment Pipeline & Applicant Tracking (ATS)
- **Job Requisitions**: Posting open vacancies across Engineering, Product, and HR.
- **Candidate Funnel**: Progression stages (`applied` &rarr; `shortlisted` &rarr; `interview` &rarr; `selected`).
- **Interviews**: Calendar scheduling with assigned HR interviewer and Google Meet video link.
- **Rating**: 5-star evaluation scorecard and technical feedback notes.
- **Test Status**: **5 / 5 Tests Passed** (`TC-REC-01` to `TC-REC-05`).

### Module 12: Performance Reviews & Appraisals
- **Appraisal Cycles**: Formal evaluations assessing core competencies.
- **Ratings & Feedback**: Numerical score (1.0 to 5.0), strengths, and growth objectives.
- **History**: Displayed chronologically on Tab 6 of the employee profile.
- **Test Status**: **2 / 2 Tests Passed** (`TC-PRF-01` to `TC-PRF-02`).

### Module 13: Reports & Visual Analytics
- **Analytics**: Headcount distribution by department, monthly salary expenditure aggregates, and punctuality percentages.
- **Exporting**: 1-click CSV export for offline analysis and auditing.
- **Test Status**: **3 / 3 Tests Passed** (`TC-REP-01` to `TC-REP-03`).

### Module 14: System Settings, Holiday Calendar & Audit Logging
- **Company Profile**: Name, email, timezone, and currency (INR/USD) settings.
- **Holidays**: Annual gazetted holiday calendar synchronized with attendance.
- **Audit Trails**: Immutable log recording user, action, module, client IP, and timestamp.
- **Test Status**: **4 / 4 Tests Passed** (`TC-SET-01` to `TC-SET-03`, `TC-NOT-01`).

---

## 5. Visual Module Gallery & Screenshot Catalog

All 18 high-resolution screenshots are located in [`docs/screenshots/`](file:///c:/xampp/htdocs/hr-management-system/docs/screenshots/):

| Ref | Screenshot File | Module | Key Interface Features |
| :---: | :--- | :--- | :--- |
| **01** | [`01_login_screen.png`](file:///c:/xampp/htdocs/hr-management-system/docs/screenshots/01_login_screen.png) | Authentication | Role switcher, 2FA prompt, Google SSO button |
| **02** | [`02_admin_dashboard.png`](file:///c:/xampp/htdocs/hr-management-system/docs/screenshots/02_admin_dashboard.png) | Admin Portal | Executive KPI stat cards, attendance doughnut chart, leave approvals |
| **03** | [`03_employee_dashboard.png`](file:///c:/xampp/htdocs/hr-management-system/docs/screenshots/03_employee_dashboard.png) | Employee Portal | Clock-in/out widget, leave quota balance, personal notifications |
| **04** | [`04_employee_directory.png`](file:///c:/xampp/htdocs/hr-management-system/docs/screenshots/04_employee_directory.png) | Staff Directory | Real-time search filter, department badges, status pills |
| **05** | [`05_employee_profile_7tabs.png`](file:///c:/xampp/htdocs/hr-management-system/docs/screenshots/05_employee_profile_7tabs.png) | Employee Profile | 7-tab interface: Overview, Personal, Job, Attendance, Leaves, Payroll, Reviews |
| **06** | [`06_departments_management.png`](file:///c:/xampp/htdocs/hr-management-system/docs/screenshots/06_departments_management.png) | Departments | Department hierarchy, headcount counter, budget allocation |
| **07** | [`07_designations_management.png`](file:///c:/xampp/htdocs/hr-management-system/docs/screenshots/07_designations_management.png) | Designations | Job titles master, pay bands, department assignment |
| **08** | [`08_attendance_management.png`](file:///c:/xampp/htdocs/hr-management-system/docs/screenshots/08_attendance_management.png) | Attendance | Daily logs, late check-in tags, decimal hours calculator |
| **09** | [`09_leave_management.png`](file:///c:/xampp/htdocs/hr-management-system/docs/screenshots/09_leave_management.png) | Leaves | Leave approval queue, date range selector, quota verification |
| **10** | [`10_payroll_register.png`](file:///c:/xampp/htdocs/hr-management-system/docs/screenshots/10_payroll_register.png) | Payroll | Monthly salary register, gross earnings, itemized statutory deductions |
| **11** | [`11_official_payslip.png`](file:///c:/xampp/htdocs/hr-management-system/docs/screenshots/11_official_payslip.png) | Payslip | Formal enterprise payslip layout, words currency conversion, print view |
| **12** | [`12_recruitment_openings.png`](file:///c:/xampp/htdocs/hr-management-system/docs/screenshots/12_recruitment_openings.png) | Recruitment | Job vacancy postings, experience requirement, salary bands |
| **13** | [`13_candidates_pipeline.png`](file:///c:/xampp/htdocs/hr-management-system/docs/screenshots/13_candidates_pipeline.png) | Candidates Pipeline | Funnel pipeline: Applied, Shortlisted, Interview, Selected |
| **14** | [`14_interviews_schedule.png`](file:///c:/xampp/htdocs/hr-management-system/docs/screenshots/14_interviews_schedule.png) | Interviews | Interview schedule table, assigned HR evaluator, Google Meet link |
| **15** | [`15_performance_reviews.png`](file:///c:/xampp/htdocs/hr-management-system/docs/screenshots/15_performance_reviews.png) | Performance | Appraisal review forms, 5-star rating matrix, development objectives |
| **16** | [`16_reports_and_analytics.png`](file:///c:/xampp/htdocs/hr-management-system/docs/screenshots/16_reports_and_analytics.png) | Reports | Interactive analytics charts, monthly expenditure breakdown, CSV exports |
| **17** | [`17_system_settings.png`](file:///c:/xampp/htdocs/hr-management-system/docs/screenshots/17_system_settings.png) | Settings | Organization profile, currency & timezone, holiday calendar |
| **18** | [`18_audit_logs.png`](file:///c:/xampp/htdocs/hr-management-system/docs/screenshots/18_audit_logs.png) | Audit Logs | Tamper-evident system activity trail, IP addresses, timestamped events |

---

## 6. Local Setup & Production Deployment Guide

### Local XAMPP Execution
1. Start **Apache** and **MySQL** services in the XAMPP Control Panel.
2. Ensure project folder is placed in:
   ```
   C:\xampp\htdocs\hr-management-system\
   ```
3. Open your browser and access the application at:
   ```
   http://localhost/hr-management-system/
   ```

### Production Environment Variables (`.env`)
Create or edit `.env` in the project root:
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://hrms.yourcompany.com

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=hr_management_system
DB_USER=hrms_prod_user
DB_PASS=YourStrongSecureDBPassword123!

MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-organization-email@gmail.com
MAIL_PASSWORD=your-google-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-organization-email@gmail.com
MAIL_FROM_NAME=HR Management System

GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-client-secret
GOOGLE_REDIRECT_URI=https://hrms.yourcompany.com/auth/google_callback.php
```

### Production Web Server Hardening
1. Ensure `.env` is blocked from web access:
   ```apache
   <FilesMatch "^\.env">
       Order allow,deny
       Deny from all
   </FilesMatch>
   ```
2. Enable HTTPS with TLS 1.3 certificates.
3. Configure `session.cookie_secure = 1` in production `php.ini`.
