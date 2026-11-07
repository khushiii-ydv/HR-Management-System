<?php
/**
 * Database Seeder Script
 * HR Management System
 */

require_once __DIR__ . '/database.php';

echo "=== Seeding HR Management System Database ===\n";

try {
    $pdo = getDBConnection();
    // Disable foreign key checks for clean truncation if needed
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $tables = [
        'audit_logs', 'employee_documents', 'holidays', 'notifications',
        'performance_reviews', 'interviews', 'candidates', 'job_openings',
        'payroll_details', 'payroll', 'leave_requests', 'leave_types',
        'attendance', 'employees', 'designations', 'departments',
        'password_resets', 'users', 'company_settings'
    ];
    foreach ($tables as $t) {
        $pdo->exec("TRUNCATE TABLE `$t`");
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    $pdo->beginTransaction();

    echo "1. Inserting Company Settings...\n";
    $stmt = $pdo->prepare("INSERT INTO company_settings (id, company_name, company_email, company_phone, company_address, timezone, currency) VALUES (1, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        'HRMS Solutions Inc.',
        'support@hrms.com',
        '+1 (555) 345-6789',
        '500 Tech Parkway, Suite 700, San Francisco, CA 94107',
        'Asia/Kolkata',
        'INR'
    ]);

    echo "2. Inserting Leave Types...\n";
    $leaveTypes = [
        ['Casual Leave', 'Paid time off for personal and family needs', 12, 'active'],
        ['Sick Leave', 'Paid leave for medical recovery and health appointments', 12, 'active'],
        ['Earned Leave', 'Annual vacation / privilege leave accrued monthly', 15, 'active'],
        ['Unpaid Leave', 'Leave without pay approved on special grounds', 0, 'active'],
        ['Maternity / Paternity Leave', 'Parental leave for newborn care', 90, 'active']
    ];
    $stmt = $pdo->prepare("INSERT INTO leave_types (leave_name, description, annual_limit, status) VALUES (?, ?, ?, ?)");
    foreach ($leaveTypes as $lt) {
        $stmt->execute($lt);
    }

    echo "3. Inserting Departments...\n";
    $departments = [
        [1, 'Engineering', 'Software development, QA, infrastructure, and engineering management', 'active'],
        [2, 'Human Resources', 'People operations, talent acquisition, payroll, and employee relations', 'active'],
        [3, 'Product & Design', 'Product management, UX research, UI design, and design systems', 'active'],
        [4, 'Marketing & Sales', 'Brand strategy, growth marketing, digital outreach, and enterprise sales', 'active'],
        [5, 'Finance & Accounting', 'Corporate finance, budgeting, auditing, tax compliance, and payroll bookkeeping', 'active'],
        [6, 'Operations & Support', 'Workplace operations, IT helpdesk, customer success, and logistics', 'active']
    ];
    $stmt = $pdo->prepare("INSERT INTO departments (id, department_name, description, status) VALUES (?, ?, ?, ?)");
    foreach ($departments as $d) {
        $stmt->execute($d);
    }

    echo "4. Inserting Designations...\n";
    $designations = [
        // Dept 1: Engineering
        [1, 1, 'Senior Software Engineer', 'Leads backend development and architecture'],
        [2, 1, 'Full Stack Developer', 'Develops web applications across frontend and backend'],
        [3, 1, 'DevOps Lead', 'Manages cloud infrastructure, CI/CD pipelines, and reliability'],
        [4, 1, 'QA Automation Engineer', 'Builds automated test suites and guarantees quality'],
        
        // Dept 2: HR
        [5, 2, 'HR Manager', 'Oversees full employee lifecycle, policy, and compliance'],
        [6, 2, 'Talent Acquisition Specialist', 'Manages candidate sourcing, interviews, and hiring'],
        [7, 2, 'HR Generalist', 'Coordinates onboarding, benefits, and workplace culture'],

        // Dept 3: Product & Design
        [8, 3, 'Lead UI/UX Designer', 'Designs user journeys, interfaces, and component libraries'],
        [9, 3, 'Senior Product Designer', 'Designs responsive layouts and wireframes'],
        [10, 3, 'Product Manager', 'Defines product roadmaps and feature specs'],

        // Dept 4: Marketing & Sales
        [11, 4, 'Marketing Director', 'Directs omnichannel marketing and brand growth'],
        [12, 4, 'Growth Marketing Specialist', 'Runs SEO, paid search, and conversion campaigns'],
        [13, 4, 'Senior Account Executive', 'Drives B2B SaaS client acquisitions and demos'],

        // Dept 5: Finance & Accounting
        [14, 5, 'Finance Controller', 'Supervises fiscal planning, auditing, and financial reports'],
        [15, 5, 'Senior Accountant', 'Maintains general ledger, AP/AR, and payroll audit'],

        // Dept 6: Operations & Support
        [16, 6, 'Operations Manager', 'Coordinates facility logistics and company operations'],
        [17, 6, 'IT Systems Administrator', 'Maintains corporate networks, hardware, and access']
    ];
    $stmt = $pdo->prepare("INSERT INTO designations (id, department_id, designation_name, description) VALUES (?, ?, ?, ?)");
    foreach ($designations as $ds) {
        $stmt->execute($ds);
    }

    echo "5. Inserting Users...\n";
    // Standard passwords:
    // admin@hrms.com -> admin123
    // hr@hrms.com -> hr123
    // employees -> emp123
    $users = [
        [1, 'Alexander Wright', 'admin@hrms.com', password_hash('admin123', PASSWORD_BCRYPT), 'admin', 'active'],
        [2, 'Eleanor Vance', 'hr@hrms.com', password_hash('hr123', PASSWORD_BCRYPT), 'hr', 'active'],
        [3, 'Alex Morgan', 'alex.morgan@hrms.com', password_hash('emp123', PASSWORD_BCRYPT), 'employee', 'active'],
        [4, 'Sarah Jenkins', 'sarah.jenkins@hrms.com', password_hash('emp123', PASSWORD_BCRYPT), 'employee', 'active'],
        [5, 'Michael Chang', 'michael.chang@hrms.com', password_hash('emp123', PASSWORD_BCRYPT), 'employee', 'active'],
        [6, 'Priya Sharma', 'priya.sharma@hrms.com', password_hash('emp123', PASSWORD_BCRYPT), 'employee', 'active'],
        [7, 'David Wilson', 'david.wilson@hrms.com', password_hash('emp123', PASSWORD_BCRYPT), 'employee', 'active'],
        [8, 'Emily Davis', 'emily.davis@hrms.com', password_hash('emp123', PASSWORD_BCRYPT), 'employee', 'active']
    ];
    $stmt = $pdo->prepare("INSERT INTO users (id, name, email, password, role, status, last_login) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    foreach ($users as $u) {
        $stmt->execute($u);
    }

    echo "6. Inserting Employees...\n";
    $employees = [
        // HR Profile (id: 1, user_id: 2)
        [
            1, 2, 'EMP-1000', 'Eleanor', 'Vance', 'female', '1990-04-12', '+1 (555) 234-5600',
            '42 Elm Street, San Francisco, CA', 2, 5, '2020-01-15', 'full_time', 78000.00,
            null, '+1 (555) 999-1000', 'active'
        ],
        // Alex Morgan (id: 2, user_id: 3)
        [
            2, 3, 'EMP-1001', 'Alex', 'Morgan', 'male', '1992-06-18', '+1 (555) 234-5601',
            '104 Market St, Apt 4B, San Francisco, CA', 1, 1, '2021-03-01', 'full_time', 92000.00,
            null, '+1 (555) 999-1001', 'active'
        ],
        // Sarah Jenkins (id: 3, user_id: 4)
        [
            3, 4, 'EMP-1002', 'Sarah', 'Jenkins', 'female', '1994-09-24', '+1 (555) 234-5602',
            '215 Pine Tree Lane, Oakland, CA', 3, 8, '2021-06-15', 'full_time', 84000.00,
            null, '+1 (555) 999-1002', 'active'
        ],
        // Michael Chang (id: 4, user_id: 5)
        [
            4, 5, 'EMP-1003', 'Michael', 'Chang', 'male', '1995-11-08', '+1 (555) 234-5603',
            '88 Harrison Blvd, Berkeley, CA', 1, 2, '2022-02-10', 'full_time', 72000.00,
            null, '+1 (555) 999-1003', 'active'
        ],
        // Priya Sharma (id: 5, user_id: 6)
        [
            5, 6, 'EMP-1004', 'Priya', 'Sharma', 'female', '1991-03-14', '+1 (555) 234-5604',
            '332 Sunset Ave, San Jose, CA', 5, 15, '2020-08-01', 'full_time', 76000.00,
            null, '+1 (555) 999-1004', 'active'
        ],
        // David Wilson (id: 6, user_id: 7)
        [
            6, 7, 'EMP-1005', 'David', 'Wilson', 'male', '1988-12-02', '+1 (555) 234-5605',
            '740 Mission Street, San Francisco, CA', 1, 3, '2019-10-15', 'full_time', 98000.00,
            null, '+1 (555) 999-1005', 'active'
        ],
        // Emily Davis (id: 7, user_id: 8)
        [
            7, 8, 'EMP-1006', 'Emily', 'Davis', 'female', '1993-07-29', '+1 (555) 234-5606',
            '512 Valencia St, San Francisco, CA', 4, 11, '2021-08-01', 'full_time', 86000.00,
            null, '+1 (555) 999-1006', 'active'
        ]
    ];
    $stmt = $pdo->prepare("INSERT INTO employees (id, user_id, employee_code, first_name, last_name, gender, date_of_birth, phone, address, department_id, designation_id, joining_date, employment_type, basic_salary, profile_image, emergency_contact, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($employees as $e) {
        $stmt->execute($e);
    }

    echo "7. Inserting Attendance Records (Past 10 days + Today)...\n";
    $stmt = $pdo->prepare("INSERT INTO attendance (employee_id, attendance_date, check_in, check_out, status, working_hours, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    // Generate dates for last 10 days up to today
    $today = date('Y-m-d');
    $empIds = [1, 2, 3, 4, 5, 6, 7];

    for ($i = 9; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $dayOfWeek = date('N', strtotime($date));
        if ($dayOfWeek >= 6) continue; // Skip Saturday & Sunday

        foreach ($empIds as $empId) {
            // Give realistic variance
            $rand = ($empId * 13 + $i * 7) % 10;
            
            if ($i === 0) {
                // Today: Check-ins
                if ($empId === 3) {
                    // On leave today
                    $stmt->execute([$empId, $date, null, null, 'on_leave', 0.00, 'Approved casual leave']);
                } elseif ($empId === 5) {
                    // Late today
                    $stmt->execute([$empId, $date, '09:42:00', null, 'late', 0.00, 'Traffic congestion']);
                } else {
                    $stmt->execute([$empId, $date, '09:05:00', null, 'present', 0.00, 'Regular check-in']);
                }
            } else {
                // Historical past days
                if ($rand === 1) {
                    // Late
                    $stmt->execute([$empId, $date, '09:45:00', '18:30:00', 'late', 8.75, 'Morning transit delay']);
                } elseif ($rand === 2) {
                    // Half day
                    $stmt->execute([$empId, $date, '09:00:00', '13:15:00', 'half_day', 4.25, 'Personal afternoon appointment']);
                } elseif ($rand === 3 && $empId === 4) {
                    // Absent or on leave
                    $stmt->execute([$empId, $date, null, null, 'on_leave', 0.00, 'Approved sick leave']);
                } else {
                    // Present
                    $checkInTime = sprintf("0%d:%02d:00", rand(8, 9), rand(0, 15));
                    $stmt->execute([$empId, $date, '09:02:00', '18:05:00', 'present', 9.05, 'Regular working day']);
                }
            }
        }
    }

    echo "8. Inserting Leave Requests...\n";
    $leaves = [
        [2, 1, date('Y-m-d', strtotime('+3 days')), date('Y-m-d', strtotime('+5 days')), 3.00, 'Family wedding out of town', 'pending', null, null, date('Y-m-d H:i:s', strtotime('-1 day'))],
        [3, 1, date('Y-m-d'), date('Y-m-d', strtotime('+1 day')), 2.00, 'Personal work at hometown bank', 'approved', 2, 'Approved. Hand over ongoing design reviews.', date('Y-m-d H:i:s', strtotime('-3 days'))],
        [4, 2, date('Y-m-d', strtotime('-5 days')), date('Y-m-d', strtotime('-4 days')), 2.00, 'High seasonal viral fever', 'approved', 2, 'Approved with medical certificate attached.', date('Y-m-d H:i:s', strtotime('-6 days'))],
        [5, 3, date('Y-m-d', strtotime('+12 days')), date('Y-m-d', strtotime('+16 days')), 5.00, 'Annual family holiday vacation', 'pending', null, null, date('Y-m-d H:i:s', strtotime('-2 days'))],
        [6, 1, date('Y-m-d', strtotime('-12 days')), date('Y-m-d', strtotime('-10 days')), 3.00, 'Home renovation supervision', 'rejected', 2, 'Clashed with critical database migration sprint.', date('Y-m-d H:i:s', strtotime('-14 days'))],
        [7, 3, date('Y-m-d', strtotime('+20 days')), date('Y-m-d', strtotime('+24 days')), 5.00, 'Trip to attending international tech conference', 'approved', 1, 'Approved. Share key takeaways with the team.', date('Y-m-d H:i:s', strtotime('-4 days'))]
    ];
    $stmt = $pdo->prepare("INSERT INTO leave_requests (employee_id, leave_type_id, start_date, end_date, total_days, reason, status, reviewed_by, review_remarks, applied_at, reviewed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    foreach ($leaves as $l) {
        $stmt->execute($l);
    }

    echo "9. Inserting Payroll & Payroll Details...\n";
    $stmtPayroll = $pdo->prepare("INSERT INTO payroll (id, employee_id, month, year, basic_salary, allowances, bonus, deductions, gross_salary, net_salary, payment_date, payment_status, generated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmtPayDetails = $pdo->prepare("INSERT INTO payroll_details (payroll_id, component_name, component_type, amount, description) VALUES (?, ?, ?, ?, ?)");

    $payrollId = 1;
    $prevMonth = (int)date('m', strtotime('-1 month'));
    $prevYear  = (int)date('Y', strtotime('-1 month'));

    foreach ($employees as $emp) {
        $empId = $emp[0];
        $basic = $emp[13];
        $hra = round($basic * 0.40, 2);
        $conveyance = 3000.00;
        $medical = 2500.00;
        $allowances = $hra + $conveyance + $medical;
        $bonus = ($empId === 2 || $empId === 6) ? 5000.00 : 0.00;
        $pf = round($basic * 0.12, 2);
        $tax = round($basic * 0.08, 2);
        $insurance = 1500.00;
        $deductions = $pf + $tax + $insurance;
        $gross = $basic + $allowances + $bonus;
        $net = $gross - $deductions;

        $stmtPayroll->execute([
            $payrollId, $empId, $prevMonth, $prevYear, $basic, $allowances, $bonus, $deductions, $gross, $net,
            date('Y-m-01', strtotime('-1 month')), 'paid', 1
        ]);

        // Details breakdown
        $stmtPayDetails->execute([$payrollId, 'Basic Salary', 'earning', $basic, 'Base agreed salary']);
        $stmtPayDetails->execute([$payrollId, 'House Rent Allowance (HRA)', 'earning', $hra, '40% of basic salary']);
        $stmtPayDetails->execute([$payrollId, 'Conveyance Allowance', 'earning', $conveyance, 'Fixed monthly conveyance']);
        $stmtPayDetails->execute([$payrollId, 'Medical Allowance', 'earning', $medical, 'Standard medical allowance']);
        if ($bonus > 0) {
            $stmtPayDetails->execute([$payrollId, 'Performance Bonus', 'earning', $bonus, 'Quarterly high performer award']);
        }
        $stmtPayDetails->execute([$payrollId, 'Provident Fund (PF)', 'deduction', $pf, 'Statutory retirement fund 12%']);
        $stmtPayDetails->execute([$payrollId, 'Professional Tax', 'deduction', $tax, 'Income tax deduction']);
        $stmtPayDetails->execute([$payrollId, 'Group Health Insurance', 'deduction', $insurance, 'Medical & life insurance cover']);

        $payrollId++;
    }

    echo "10. Inserting Job Openings...\n";
    $jobs = [
        [1, 1, 1, 'Senior Backend Engineer (PHP/Go)', 'Looking for an experienced engineer to build high throughput REST microservices and database architectures.', 'full_time', 2, 'San Francisco, CA / Remote', '$90k - $120k', date('Y-m-d', strtotime('-20 days')), date('Y-m-d', strtotime('+30 days')), 'open', 1],
        [2, 3, 8, 'UI/UX Design Systems Specialist', 'Create pixel-perfect Figma component libraries and accessible design guidelines across web & mobile.', 'full_time', 1, 'Oakland, CA / Hybrid', '$80k - $100k', date('Y-m-d', strtotime('-15 days')), date('Y-m-d', strtotime('+25 days')), 'open', 1],
        [3, 4, 13, 'Enterprise Account Executive', 'Drive new customer acquisitions for our enterprise SaaS product suite and manage pipeline.', 'full_time', 3, 'San Francisco, CA', '$75k - $95k + Commission', date('Y-m-d', strtotime('-10 days')), date('Y-m-d', strtotime('+35 days')), 'open', 2],
        [4, 1, 4, 'QA Automation Lead', 'Build scalable end-to-end Cypress and Playwright testing frameworks.', 'contract', 1, 'Remote', '$60k - $80k', date('Y-m-d', strtotime('-30 days')), date('Y-m-d', strtotime('-2 days')), 'closed', 1],
        [5, 2, 6, 'Junior Talent Sourcing Associate', 'Source qualified profiles on LinkedIn and conduct preliminary candidate evaluations.', 'intern', 2, 'San Francisco, CA', '$35k - $45k', date('Y-m-d', strtotime('-5 days')), date('Y-m-d', strtotime('+40 days')), 'open', 2]
    ];
    $stmt = $pdo->prepare("INSERT INTO job_openings (id, department_id, designation_id, job_title, job_description, employment_type, vacancies, location, salary_range, posted_date, closing_date, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($jobs as $j) {
        $stmt->execute($j);
    }

    echo "11. Inserting Candidates...\n";
    $candidates = [
        [1, 1, 'Julian', 'Castillo', 'julian.c@gmail.com', '+1 555-777-01', null, 5.5, 'B.S. in Computer Science', date('Y-m-d', strtotime('-12 days')), 'interview'],
        [2, 1, 'Rohan', 'Verma', 'rohan.v@outlook.com', '+1 555-777-02', null, 7.0, 'M.S. Software Engineering', date('Y-m-d', strtotime('-10 days')), 'shortlisted'],
        [3, 1, 'Chloe', 'Dupont', 'chloe.dupont@yahoo.com', '+1 555-777-03', null, 3.2, 'B.Tech Information Technology', date('Y-m-d', strtotime('-3 days')), 'applied'],
        [4, 2, 'Marcus', 'Aurelius', 'marcus.design@gmail.com', '+1 555-777-04', null, 6.0, 'B.Des Interaction Design', date('Y-m-d', strtotime('-14 days')), 'selected'],
        [5, 2, 'Nina', 'Kowalski', 'nina.k@gmail.com', '+1 555-777-05', null, 4.0, 'B.A. Graphic Design', date('Y-m-d', strtotime('-8 days')), 'interview'],
        [6, 3, 'Brian', 'O\'Connor', 'brian.sales@fastmail.com', '+1 555-777-06', null, 8.5, 'MBA Marketing', date('Y-m-d', strtotime('-9 days')), 'interview'],
        [7, 3, 'Jessica', 'Alba', 'jessica.a@domain.com', '+1 555-777-07', null, 2.0, 'B.Com Business Admin', date('Y-m-d', strtotime('-18 days')), 'rejected'],
        [8, 5, 'Samantha', 'Reed', 'sam.reed@gmail.com', '+1 555-777-08', null, 1.0, 'B.S. Human Resources', date('Y-m-d', strtotime('-2 days')), 'applied']
    ];
    $stmt = $pdo->prepare("INSERT INTO candidates (id, job_id, first_name, last_name, email, phone, resume_path, experience_years, qualification, application_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($candidates as $c) {
        $stmt->execute($c);
    }

    echo "12. Inserting Interviews...\n";
    $interviews = [
        [1, 1, 1, date('Y-m-d', strtotime('+1 day')), '11:00:00', 'online', 'https://meet.google.com/abc-defg-hij', 'scheduled', null, null],
        [2, 5, 2, date('Y-m-d', strtotime('+2 days')), '14:30:00', 'online', 'https://meet.google.com/xyz-uvwx-rst', 'scheduled', null, null],
        [3, 6, 1, date('Y-m-d', strtotime('+3 days')), '16:00:00', 'offline', 'Meeting Room 3B (Main Office)', 'scheduled', null, null],
        [4, 4, 2, date('Y-m-d', strtotime('-3 days')), '10:00:00', 'online', 'https://meet.google.com/klm-nopq-rst', 'completed', 'Outstanding Figma design exercise and strong communication.', 4.8],
        [5, 7, 2, date('Y-m-d', strtotime('-8 days')), '15:00:00', 'online', 'https://meet.google.com/ghi-jklm-nop', 'completed', 'Lacks experience in B2B enterprise SaaS negotiations.', 2.5]
    ];
    $stmt = $pdo->prepare("INSERT INTO interviews (id, candidate_id, interviewer_id, interview_date, interview_time, interview_mode, meeting_link, interview_status, feedback, rating) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($interviews as $iv) {
        $stmt->execute($iv);
    }

    echo "13. Inserting Performance Reviews...\n";
    $reviews = [
        [2, 1, date('Y-m-d', strtotime('-6 months')), date('Y-m-d', strtotime('-1 day')), 4.6, 'Excellent architectural design, prompt code reviews, and mentorship of junior engineers.', 'Could contribute more to cross-team architectural documentation.', 'Promoted to lead engineer on core platform refactoring project.', date('Y-m-d', strtotime('-10 days'))],
        [3, 1, date('Y-m-d', strtotime('-6 months')), date('Y-m-d', strtotime('-1 day')), 4.8, 'Top tier UI/UX consistency, established complete Figma token library, great user empathy.', 'Increase direct participation in customer testing interviews.', 'Exceeded expectations across all Q3 metrics.', date('Y-m-d', strtotime('-12 days'))],
        [4, 1, date('Y-m-d', strtotime('-6 months')), date('Y-m-d', strtotime('-1 day')), 3.9, 'Reliable sprint completion, quick turnaround on reported bug tickets.', 'Can improve automated unit test coverage on frontend features.', 'Good overall progress this evaluation cycle.', date('Y-m-d', strtotime('-15 days'))],
        [5, 2, date('Y-m-d', strtotime('-6 months')), date('Y-m-d', strtotime('-1 day')), 4.4, 'Flawless payroll audits, timely financial close, excellent tax compliance diligence.', 'Automate expense receipt matching with internal accounting software.', 'Consistently dependable and meticulous.', date('Y-m-d', strtotime('-8 days'))],
        [6, 1, date('Y-m-d', strtotime('-6 months')), date('Y-m-d', strtotime('-1 day')), 4.7, 'Zero downtime cloud migration, implemented automated Kubernetes scaling.', 'Document recovery runbooks for secondary standby cluster.', 'Outstanding work on cloud optimization reducing AWS bill by 18%.', date('Y-m-d', strtotime('-5 days'))]
    ];
    $stmt = $pdo->prepare("INSERT INTO performance_reviews (employee_id, reviewer_id, review_period_start, review_period_end, rating, strengths, improvements, comments, review_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($reviews as $r) {
        $stmt->execute($r);
    }

    echo "14. Inserting Holidays...\n";
    $holidays = [
        ['New Year\'s Day', date('Y') . '-01-01', 'Official holiday welcoming the new calendar year'],
        ['Republic Day / National Day', date('Y') . '-01-26', 'National anniversary celebration'],
        ['Holi Festival', date('Y') . '-03-25', 'Traditional spring festival of colors'],
        ['Labor Day', date('Y') . '-05-01', 'International Workers and Labor Rights Day'],
        ['Independence Day', date('Y') . '-08-15', 'National Independence Commemoration'],
        ['Mahatma Gandhi Birthday', date('Y') . '-10-02', 'National tribute and day of non-violence'],
        ['Diwali Festival of Lights', date('Y') . '-11-01', 'Traditional celebration of light over darkness'],
        ['Christmas Day', date('Y') . '-12-25', 'Global Christmas holiday']
    ];
    $stmt = $pdo->prepare("INSERT INTO holidays (holiday_name, holiday_date, description) VALUES (?, ?, ?)");
    foreach ($holidays as $h) {
        $stmt->execute($h);
    }

    echo "15. Inserting Notifications...\n";
    $notifications = [
        [1, 'System Initialized', 'HRMS v2.0 Enterprise system ready for operations.', 'info', 0],
        [1, 'New Leave Application', 'Alex Morgan has requested 3 days of Casual Leave.', 'leave', 0],
        [2, 'Interview Scheduled', 'Interview scheduled with Julian Castillo tomorrow at 11:00 AM.', 'recruitment', 0],
        [2, 'Candidate Application', 'Chloe Dupont applied for Senior Backend Engineer position.', 'recruitment', 1],
        [3, 'Payroll Processed', 'Your monthly salary for last month has been credited successfully.', 'payroll', 0],
        [3, 'Leave Status Update', 'Your leave request for upcoming personal work was approved.', 'leave', 1],
        [4, 'Quarterly Review Published', 'Your performance review evaluation has been completed.', 'performance', 0]
    ];
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, notification_type, is_read, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    foreach ($notifications as $n) {
        $stmt->execute($n);
    }

    echo "16. Inserting Employee Documents...\n";
    $docs = [
        [2, 'Signed Employment Contract', 'contract', 'uploads/documents/emp_1001_contract.pdf', 1],
        [2, 'NDA & Intellectual Property Deed', 'legal', 'uploads/documents/emp_1001_nda.pdf', 1],
        [3, 'Signed Employment Contract', 'contract', 'uploads/documents/emp_1002_contract.pdf', 1],
        [4, 'Tax Declaration & ID Proof', 'tax', 'uploads/documents/emp_1003_id.pdf', 1]
    ];
    $stmt = $pdo->prepare("INSERT INTO employee_documents (employee_id, document_name, document_type, file_path, uploaded_by) VALUES (?, ?, ?, ?, ?)");
    foreach ($docs as $dc) {
        $stmt->execute($dc);
    }

    echo "17. Inserting Audit Logs...\n";
    $logs = [
        [1, 'System Setup', 'Settings', 'Database initialized with demo enterprise records.', '127.0.0.1'],
        [1, 'User Login', 'Auth', 'Administrator signed into the dashboard.', '127.0.0.1'],
        [2, 'Leave Reviewed', 'Leave', 'HR approved 2-day leave application for Sarah Jenkins.', '127.0.0.1'],
        [1, 'Payroll Processed', 'Payroll', 'Batch payroll executed for 7 active employees.', '127.0.0.1']
    ];
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, module, description, ip_address, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    foreach ($logs as $al) {
        $stmt->execute($al);
    }

    $pdo->commit();
    echo "\n=== Database Seed Completed Successfully! ===\n";
    echo "Default Logins:\n";
    echo "- Admin: admin@hrms.com / admin123\n";
    echo "- HR: hr@hrms.com / hr123\n";
    echo "- Employee: alex.morgan@hrms.com or EMP-1001 / emp123\n";

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "ERROR SEEDING DATABASE: " . $e->getMessage() . "\n";
}
