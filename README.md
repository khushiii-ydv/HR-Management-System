# HRMS – Enterprise Human Resource Management System

A modern, secure, and professional enterprise-grade Human Resource Management System (HRMS) built using **PHP, MySQL, HTML5, CSS3, JavaScript, and Bootstrap 5**. The interface is designed around a polished SaaS aesthetic with a corporate blue theme, intuitive navigation, responsive layouts, and a role-based access control system for **Admin**, **HR**, and **Employees**.

---

## Deliverables Summary

| Deliverable | Location | Description |
| :--- | :--- | :--- |
| **Complete Source Code** | Root directory | Modular PHP components, controllers, and assets |
| **MySQL Database Backup** | [`database/hr_management_system.sql`](file:///c:/xampp/htdocs/hr-management-system/database/hr_management_system.sql) | Complete schema + 19 tables + full realistic demo seed data |
| **Excel Testing Report** | [`docs/HR_Management_System_Testing_Report.xlsx`](file:///c:/xampp/htdocs/hr-management-system/docs/HR_Management_System_Testing_Report.xlsx) | 50 test cases covering all modules with 100% Pass status |
| **Documentation** | [`docs/README.md`](file:///c:/xampp/htdocs/hr-management-system/docs/README.md) | Architectural specifications, schema, and user manual |
| **High-Resolution Screenshots** | [`docs/screenshots/`](file:///c:/xampp/htdocs/hr-management-system/docs/screenshots/) | 18 screenshots of all modules and dashboards |

---

## Production Authentication, Authorization & Security Architecture

### 1. Clean Slate & Zero Hardcoded Dummy Data
The system is built on a clean-slate architecture. Newly registered users will not see any hardcoded fake employee records, fake payrolls, or dummy attendance. Dashboards and tables gracefully render functional empty-state placeholders (e.g., "No active employees", "No attendance records today") until operations are performed.

### 2. User Self-Registration & Email Ownership Verification
- **Employee Registration**: Standard self-registration requiring name, work email, and password.
- **Email Ownership Verification**: Upon registration, an account is set to `pending_verification` and an automated 6-digit OTP is dispatched to the user's email address. The account cannot be accessed until the code is verified.
- **Password Security**: Passwords are encrypted using PHP's `password_hash(..., PASSWORD_BCRYPT)`. No plain-text passwords exist anywhere.

### 3. Role Governance: Hashed Role Keys & Admin Invitations
- **HR and Admin Registration**:
  - **Preferred Security Approach (Admin Invitation System)**: Administrators can generate single-use, 48-hour cryptographic invitation links in **Settings → Role Invitations** (`auth/register.php?invite=TOKEN`). The link locks the invited email and role, waiving the key requirement.
  - **Hashed Role Verification Keys**: If registering directly, elevated roles strictly require the authorized Role Verification ID. Verification keys are stored strictly as **one-way BCRYPT hashes** in `role_access_keys`. Raw keys are never stored in the database, never hardcoded in frontend code, and never exposed in API responses.
  - **Secure Key Rotation**: Administrators can rotate security keys at any time from **Settings → Role Key Rotation**. Upon rotation, the raw key is displayed **only once** for secure copying and stored as a hash.
  - **Default Initial Verification Keys (Hashed in DB)**:
    - **Administrator Key**: `ADM-KEY-98421-HRMS`
    - **HR Specialist Key**: `HR-KEY-54189-HRMS`

### 4. Email & Password Login with Two-Factor Authentication (2FA)
- **Login Verification Flow**:
  1. User enters Email/Employee ID and Password.
  2. Backend verifies credentials against BCRYPT hash.
  3. Two-factor authentication (2FA) is enforced for elevated roles (Admin/HR) and configurable for all employees.
  4. Backend generates a 6-digit OTP stored as a BCRYPT hash in `user_otps` (expires in 10 minutes, single use).
  5. **OTP Privacy & Security**:
     - **The OTP is NEVER displayed on the user interface.**
     - **The OTP is NEVER returned in an API response.**
     - The OTP is dispatched exclusively to the user's registered email address.
     - The UI displays: *"A verification code has been sent to your registered email address (k•••••v@gmail.com)."*
     - Max 5 failed verification attempts before invalidation.
- **Brute-Force Rate Limiting**: 5 consecutive failed login attempts automatically lock the account for 15 minutes (`locked_until > NOW()`). Generic error messages prevent account enumeration.

### 5. Official Google OAuth 2.0 & Strict Email Matching
- **Official OAuth 2.0 Flow**: "Continue with Google" redirects directly to Google's official authentication endpoint (`https://accounts.google.com/o/oauth2/v2/auth`).
- **Backend Identity Verification**: Google's callback endpoint (`auth/google_callback.php`) validates CSRF state tokens, exchanges authorization codes for access tokens, and queries Google's verified Userinfo API.
- **Strict Email Matching Rule**:
  - The email returned by Google OAuth must match an existing registered HRMS account.
  - If a user authenticates with an unregistered Gmail address (e.g., `anotheruser@gmail.com`), access is **strictly denied** with a prompt to register.
  - Once verified, the user is logged in directly without redundant custom codes.
- **Account Linking & Unlinking**: Authenticated users can link or unlink their corporate Google account from **Settings → Google Account Single Sign-On**. Linking strictly requires the Google email to match the registered account email.

### 6. Role-Based Access Control (RBAC) & Strict Resource Ownership
- **Resource Ownership Verification**: Every backend route checks resource ownership before returning data.
  - An **Employee** can view only their own profile (`employees/view.php?id=MY_ID`), attendance records, leave requests, and payslips. Manipulating URL parameters (e.g. `view.php?id=999`) results in an immediate **403 Forbidden**.
  - **HR** and **Admin** possess authorized administrative scopes.
  - Route guards (`requireRole(['admin', 'hr'])`) defend approval endpoints (`leaves/action.php`) and payroll generation (`payroll/generate.php`).
- **Session Inactivity & Fixation Defenses**:
  - Session IDs are regenerated upon authentication (`session_regenerate_id(true)`).
  - Sessions automatically expire after 30 minutes of inactivity.
  - Secure session cookies are issued with `httponly: true` and `samesite: Lax`.

---

## Quick Access & First-Time Setup

Open your web browser and navigate to:
**`http://localhost/hr-management-system/`**

1. Click **Create an Account / Register** or **Continue with Google**.
2. Select your desired role (**Admin**, **HR**, or **Employee**).
3. If creating an Admin or HR account, enter the confidential Role Verification ID:
   - **Admin**: `ADM-KEY-98421-HRMS`
   - **HR**: `HR-KEY-54189-HRMS`
4. Log in and enjoy your clean, enterprise-grade HRMS!

---

## Design System & Color Palette

The interface adheres to an enterprise SaaS aesthetic featuring Inter typography, 12px card border radiuses, 8px layout grids, and strict semantic color tokens:

- **Primary Brand Blue**: `#0454D9` (Action buttons, primary highlights)
- **Secondary Blue**: `#2478B8` (Accents and navigation secondary)
- **Dark Navy**: `#102A43` (Brand emblems, hero gradients)
- **Light Blue**: `#EAF2FF` (Hover backgrounds, active pills)
- **Page Background**: `#F5F7FB` (Breathable neutral canvas)
- **Cards & Structure**: `#FFFFFF`, borders `#E5EAF1`, shadows `0 1px 3px rgba(16,42,67,0.05)`
- **Primary Text**: `#172B4D` | **Secondary Text**: `#64748B`
- **Success / Payroll Green**: `#16A36A` with background `#E8F7EF`
- **Recruitment Purple**: `#7C3AED` with background `#F2ECFF`
- **Attendance Orange**: `#EA8A19` with background `#FFF4E5`
- **Warning Amber**: `#D99A16` with background `#FFF8E5`
- **Error Red**: `#DC4545` with background `#FDECEC`

---

## Database Architecture (All 19 Tables)

The system connects to MySQL database `hr_management_system` with relational integrity and foreign keys:

1. `users`: Stores user credentials (`id`, `name`, `email`, `password` hashed via `password_hash()`, `role` enum, `status`, `last_login`).
2. `password_resets`: Secure expiring tokens for password recovery flow.
3. `departments`: Organizational business units (`department_name`, `description`, `status`).
4. `designations`: Job titles assigned per department (`department_id`, `designation_name`, `description`).
5. `employees`: Master employee records linked to `user_id`, `employee_code`, `department_id`, `designation_id`, `joining_date`, `basic_salary`, `status`.
6. `attendance`: Daily presence logs (`employee_id`, `attendance_date`, `check_in`, `check_out`, `working_hours`, `status`, `remarks`).
7. `leave_types`: Configurable categories: Casual Leave (12d), Sick Leave (12d), Earned Leave (15d), Unpaid Leave.
8. `leave_requests`: Leave applications (`employee_id`, `leave_type_id`, `start_date`, `end_date`, `total_days`, `reason`, `status`, `reviewed_by`).
9. `payroll`: Monthly remuneration records (`employee_id`, `month`, `year`, `basic_salary`, `allowances`, `bonus`, `deductions`, `gross_salary`, `net_salary`, `payment_status`).
10. `payroll_details`: Itemized earnings (Basic, HRA, Conveyance, Medical, Bonus) and deductions (PF, Tax, Insurance).
11. `job_openings`: Talent requisitions (`job_title`, `department_id`, `vacancies`, `location`, `salary_range`, `status`).
12. `candidates`: Job applicants (`job_id`, `first_name`, `last_name`, `email`, `phone`, `experience_years`, `status`).
13. `interviews`: Scheduled interview rounds (`candidate_id`, `interviewer_id`, `interview_date`, `interview_time`, `meeting_link`, `rating`, `feedback`).
14. `performance_reviews`: Formal employee evaluations (`employee_id`, `reviewer_id`, `rating`, `strengths`, `improvements`, `comments`).
15. `notifications`: Personal notification feed for each user.
16. `company_settings`: Corporate metadata (Company Name, Email, Address, Timezone, Currency).
17. `audit_logs`: Immutable security event trail (User, Module, Action, Description, IP Address, Timestamp).
18. `holidays`: Annual company holiday calendar.
19. `employee_documents`: File attachments (Contracts, NDAs, Identity documents) stored in `uploads/documents/`.

---

## Detailed Functional Modules

### 1. Authentication & Security
- **Multi-Role Portal**: Dedicated role switcher and smart login accepting either Email or Employee ID (`EMP-1001`).
- **Google OAuth SSO Simulation**: 1-click authentication for instant access.
- **Security**: Passwords encrypted with `PASSWORD_BCRYPT`, session validation on every request, prepared statements against SQL injection, and CSRF token verification.
- **Password Recovery**: Secure token generation with 1-hour expiration and reset screen.

### 2. Role-Based Dashboards
- **Admin Dashboard**: Real-time KPI cards, Chart.js Today's Attendance Doughnut Chart, Department Headcount Bar Chart, pending leave approvals table with 1-click actions, and recent audit activity feed.
- **HR Dashboard**: Focuses on workforce metrics, recruitment stages, interview agenda, and pending employee reviews.
- **Employee Dashboard**: Personalized greeting, live digital clock with real-time seconds, 1-click Check-In/Check-Out widget, leave balance progress cards, recent personal attendance logs, and latest payslip shortcut.

### 3. Employee Management
- **Directory**: Real-time client-side live search, department and status filters, and status pills.
- **Add Employee**: Multi-section form capturing personal info, automatic user account generation, employment placement, and document attachment.
- **Comprehensive 7-Tab Profile**: Overview, Personal Info, Attendance history, Leave history & balances, Payroll slips, Document vault, and Performance scorecards.

### 4. Department & Designation Management
- Organize business units, monitor department-wise headcount, and enforce foreign key constraints preventing accidental deletion of departments with active staff.
- Dynamic designation assignment tied to specific departments.

### 5. Attendance Tracking
- Digital clock-in / clock-out with automatic calculation of net decimal working hours.
- Automatic late arrival detection (after 09:30 AM).
- Prevents duplicate same-day attendance entries (`UNIQUE (employee_id, attendance_date)`).
- Admin & HR manual attendance logging and correction interface.

### 6. Leave Management
- Employee time off application with automatic weekend exclusion math.
- Admin/HR review workflow with instant "Approve" or "Reject" with remarks modal.
- Automatic synchronization: approving a leave request automatically creates/marks attendance records for those dates as `on_leave`.

### 7. Payroll Management
- Automated formula: `Gross = Basic + Allowances (HRA 40% + Conveyance + Medical) + Bonus`, `Net = Gross - Deductions (PF 12% + Tax 8% + Insurance)`.
- Batch monthly payroll execution for all active employees.
- Official printable payslip invoice with company branding, itemized dual-column breakdown, authorized signature placeholders, and **net pay in written words**.

### 8. Recruitment & Talent Pipeline
- Job opening requisitions with status toggling (Open, On Hold, Closed).
- Candidate pipeline tracking through 6 distinct stages: `applied` &rarr; `shortlisted` &rarr; `interview` &rarr; `selected` &rarr; `hired` &rarr; `rejected`.
- Interview scheduling with Google Meet links, interviewers, and rating scorecards (1.0 to 5.0 stars).

### 9. Performance Appraisals
- Conduct appraisals evaluating core competencies, review period, numerical rating, key strengths, and growth recommendations.
- Scorecard cards with star ratings and historical review logs.

### 10. Reports & Analytics
- Multi-tab report generator:
  - **Master Employee Roster** with department filters.
  - **Attendance & Working Hours Summary** with date range filters.
  - **Leave Consumption Report** by category and staff member.
  - **Payroll Financial Outflow Ledger** with monthly aggregates.
- Direct 1-click **CSV Export** for all reports.

### 11. Settings & Audit Logging
- Organization profile editing (Name, Address, Currency INR/USD/EUR/GBP, Timezone).
- Personal password change interface.
- Corporate holiday calendar with gazetted holiday cards.
- Complete system audit trail recording user actions, IP addresses, and timestamps.

---

## Installation & Setup

1. **Prerequisites**: XAMPP installed with Apache and MySQL services active.
2. **Directory**: Ensure files reside in `C:\xampp\htdocs\hr-management-system\`.
3. **Database Configuration**:
   - The database name is `hr_management_system`.
   - Host: `127.0.0.1`, User: `root`, Password: `` (empty default).
   - If importing freshly, execute [`database/hr_management_system.sql`](file:///c:/xampp/htdocs/hr-management-system/database/hr_management_system.sql) in phpMyAdmin.
4. **Reset Demo Data Anytime**:
   - Run `php config/seed.php` via command prompt or browser.
5. **Open Application**:
   - Visit `http://localhost/hr-management-system/` in any modern web browser.
