# Student Attendance Management System

A web-based Student Attendance Management System developed using PHP and MySQL, with QR-code functionality to support student attendance management.

## About the Project

The Student Attendance Management System is designed to simplify attendance management in educational institutions. It provides separate sections for administrators, lecturers, and students to support attendance-related activities.

The system uses PHP for server-side processing, MySQL for database management, and a QR-code library for QR-code-related functionality.

## Features

- User authentication and role management.
- Administrator, lecturer, and student sections.
- Student information management.
- Course management.
- Attendance session management.
- QR-code-related attendance functionality.
- Attendance record management.
- Attendance status tracking.
- Database management for students, courses, sessions, and attendance records.

Feature availability depends on the implementation of the individual modules.

## Technologies Used

- PHP
- MySQL
- HTML
- CSS
- JavaScript, where applicable
- QR-code generation library
- Apache Web Server

## Project Structure

    attendance-management-system/
    ├── admin/
    ├── assets/
    │   └── css/
    ├── auth/
    ├── config/
    ├── includes/
    ├── lecturer/
    ├── phpqrcode/
    ├── qr_codes/
    ├── student/
    ├── index.php
    ├── sql.script
    └── README.md

## Requirements

Before installing the application, ensure you have:

- PHP
- MySQL or MariaDB
- Apache Web Server
- A modern web browser
- phpMyAdmin or another database management tool

XAMPP is recommended for local development.

## Installation and Setup

### 1. Clone the Repository

Run the following command in your terminal:

    git clone https://github.com/khalitmaigari1-4645/attendance-management-system.git

### 2. Move the Project

For XAMPP, place the project folder inside the htdocs directory.

Example:

    htdocs/attendance-management-system/

### 3. Create the Database

1. Start Apache and MySQL.
2. Open phpMyAdmin in your browser.
3. Import the sql.script file included in the project.
4. Review the database script before importing it.

The supplied SQL script creates a database named attendance_system.

### 4. Configure the Database

Open the configuration files inside the config/ directory.

Update the database connection settings to match your local environment:

- Database host
- Database name
- Database username
- Database password

Use the configuration variable names expected by the application.

### 5. Run the Application

Open your browser and visit:

    http://localhost/attendance-management-system/

Ensure the application's redirect paths match your project directory.

### 6. Log In

Use valid accounts configured in the database and supported by the application's authentication system.

Review any sample accounts in the SQL script before using them. Do not use sample credentials in a production environment.

## Database Structure

The supplied database script defines the following tables:

| Table | Description |
|---|---|
| users | Stores user accounts and roles |
| students | Stores student information |
| courses | Stores course information |
| sessions | Stores attendance session details |
| attendance | Stores attendance records |

## Security Considerations

Before deploying the application in a real institution:

- Use PHP's password_hash() and password_verify() for password storage and verification.
- Use prepared statements to protect against SQL injection.
- Validate user input on the server.
- Enforce authentication and authorization for protected pages.
- Validate QR-code attendance submissions on the server.
- Prevent unauthorized and duplicate attendance submissions.
- Protect database credentials and session information.
- Remove or restrict debugging and testing scripts.
- Never publish real student records or sensitive database credentials.
- Use HTTPS in production.

## Project Status

This project is intended for educational use, demonstration, and further development. Test the application thoroughly in a local environment before using it in a real institution.

## Author

GitHub: https://github.com/khalitmaigari1-4645

## Repository

https://github.com/khalitmaigari1-4645/attendance-management-system
