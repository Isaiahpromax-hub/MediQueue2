# MediQueue - Healthcare Queue & Appointment Management System

A complete, modern, responsive web-based healthcare management system built with PHP, MySQL, HTML5, CSS3, and JavaScript.

## Features

- **Patient Portal**: Register, book appointments, join virtual queues, monitor queue status in real-time, view notifications, manage profile
- **Staff Portal**: Manage appointments, control queue flow, register patients, confirm/cancel appointments
- **Doctor Portal**: View assigned appointments, manage patient queue, start consultations, mark patients as served
- **Admin Portal**: Full system control - manage users, patients, doctors, staff, services, view reports, audit logs, system settings
- **Real-time Queue Updates**: AJAX polling for live queue status without page refresh
- **Notification System**: Internal notifications for appointments, queue updates, and system messages
- **Audit Logging**: Track all important system actions

## Demo Accounts

All demo accounts use password: **password**

| Role | Email |
|------|-------|
| Administrator | admin@mediqueue.com |
| Receptionist | receptionist@mediqueue.com |
| Doctor | doctor@mediqueue.com |
| Patient | patient@mediqueue.com |

Additional demo accounts:
- patient2@mediqueue.com / password
- patient3@mediqueue.com / password
- doctor2@mediqueue.com / password
- receptionist2@mediqueue.com / password

## Installation (XAMPP)

### Prerequisites
- XAMPP (Apache + PHP 8+ + MySQL/MariaDB)

### Steps

1. **Copy the project** to `C:\xampp\htdocs\MediQueue\`

2. **Start XAMPP**: Open XAMPP Control Panel and start Apache and MySQL.

3. **Create the database**:
   - Open phpMyAdmin: http://localhost/phpmyadmin
   - Click "Import" tab
   - Click "Choose File" and select `database/mediqueue.sql`
   - Click "Go" to import

4. **Access the application**:
   - Open your browser and go to: http://localhost/MediQueue2/
   - Use the demo accounts above to log in

### Command Line Import (Alternative)

```bash
mysql -u root -p < C:\xampp\htdocs\MediQueue\database\mediqueue.sql
```

## Project Structure

```
MediQueue/
├── index.php                  # Landing page
├── login.php                  # Login page
├── register.php               # Patient registration
├── logout.php                 # Logout handler
├── config/
│   └── database.php           # Database configuration and helper class
├── includes/
│   ├── auth.php               # Authentication helpers, session management
│   ├── header.php             # Common page header with sidebar navigation
│   └── footer.php             # Common page footer with notification panel
├── assets/
│   ├── css/
│   │   └── style.css          # Complete stylesheet (responsive)
│   ├── js/
│   │   └── script.js          # JavaScript (queue polling, notifications, UI)
│   └── images/                # Image assets
├── api/
│   ├── notifications.php      # AJAX notification endpoint
│   ├── queue_status.php       # AJAX queue status polling
│   ├── queue_list.php         # AJAX queue list for staff
│   └── queue_action.php       # Queue actions (call, serve, skip, leave)
├── patient/
│   ├── dashboard.php          # Patient dashboard
│   ├── book_appointment.php   # Book appointment form
│   ├── appointments.php       # View/manage appointments
│   ├── join_queue.php         # Join virtual queue
│   ├── check_queue.php        # Real-time queue status
│   ├── queue_history.php      # Queue history with filters
│   ├── notifications.php      # View notifications
│   └── profile.php            # Edit profile
├── staff/
│   ├── dashboard.php          # Staff dashboard
│   ├── queue.php              # Queue management
│   ├── appointments.php       # Appointment management
│   └── patients.php           # Patient list
├── doctor/
│   ├── dashboard.php          # Doctor dashboard
│   ├── appointments.php       # View appointments
│   └── queue.php              # Doctor queue management
├── admin/
│   ├── dashboard.php          # Admin dashboard with statistics
│   ├── patients.php           # Manage patients
│   ├── doctors.php            # Manage doctors
│   ├── staff.php              # Manage staff
│   ├── appointments.php       # Manage appointments
│   ├── queues.php             # Queue management
│   ├── services.php           # Manage services/departments
│   ├── reports.php            # Generate reports
│   ├── audit_logs.php         # View audit logs
│   └── settings.php           # System settings
└── database/
    └── mediqueue.sql          # Complete database schema + demo data
```

## Database Schema

- `users` - User accounts with roles (patient, receptionist, doctor, admin)
- `patients` - Patient profiles linked to user accounts
- `doctors` - Doctor profiles with specializations
- `staff` - Staff/receptionist profiles
- `services` - Available services/departments
- `doctor_services` - Many-to-many: doctors to services
- `appointments` - Appointment bookings
- `queue_entries` - Virtual queue entries
- `notifications` - User notifications
- `audit_logs` - System audit trail
- `system_settings` - Configurable system settings

## Security Features

- Password hashing with `password_hash()` / `password_verify()`
- Prepared statements for all database queries
- CSRF token protection on forms
- Role-based access control
- Session management with regeneration
- Input validation and output escaping
- SQL injection prevention

## Technology Stack

- **Frontend**: HTML5, CSS3, JavaScript (vanilla)
- **Backend**: PHP 8+
- **Database**: MySQL/MariaDB
- **Server**: Apache (via XAMPP)
