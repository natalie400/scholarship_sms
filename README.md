# Scholarship Management System (SMS)

A role-based PHP web application for managing scholarships.

It supports three main user roles:

- Student: sign up, log in, browse scholarships, and submit applications.
- Signatory: create and manage scholarships, review student applications.
- Admin: verify users, approve/reject scholarships, and manage platform access.

## Tech Stack

- PHP (classic multi-page app)
- MySQL / MariaDB
- HTML, CSS, JavaScript, jQuery, Bootstrap
- PHPMailer (email verification and password reset flows)

## Project Structure

- `index.php`: login page (entry point)
- `signup.php`, `signup_student.php`, `signup_sig.php`: registration flows
- `forgotpassword.php`, `backend/reset_pass.php`: password reset flow
- `admin/`: admin pages
- `signatory/`: signatory pages
- `student/`: student pages
- `backend/`: authentication, security, data handlers, SQL dump
- `config.php`: database and SMTP configuration
- `css/`, `js/`, `images/`, `fonts/`: static assets

## Prerequisites

- PHP 7.x or newer (project was originally developed around PHP 7.3)
- MySQL/MariaDB
- Apache/Nginx (or local stacks like XAMPP/LAMP/WAMP)

## Setup Instructions

1. Clone or copy the project into your web server root.
2. Create a MySQL database (or reuse the bundled one from SQL import).
3. Import the schema and seed data:
	 - File: `backend/scholarship_management_system.sql`
	 - Example command:
		 ```bash
		 mysql -u root -p < backend/scholarship_management_system.sql
		 ```
4. Create a local `.env` file (copy from `.env.example`) and set your environment values.
	The app expects the following keys:

	```env
	# Database
	DB_HOST=127.0.0.1
	DB_PORT=3306
	DB_USER=root
	DB_PASS=your_db_password
	DB_NAME=sms

	# SMTP / Email
	SMTP_HOST=smtp.gmail.com
	SMTP_PORT=587
	SMTP_AUTH=true
	SMTP_SECURE=tls
	SMTP_USER=your_email@example.com
	SMTP_PASS=your_email_app_password
	SMTP_FROM_NAME=SMS Portal

	# Africa's Talking API
	AT_USERNAME=sandbox
	AT_API_KEY=your_africastalking_api_key
	AT_SENDER_ID=
	```

	What each key is for:
	- `DB_HOST`: Database server host (usually `127.0.0.1` for local).
	- `DB_PORT`: Database port (default MySQL is `3306`).
	- `DB_USER`: Database username.
	- `DB_PASS`: Database user password.
	- `DB_NAME`: Database name to use.
	- `SMTP_HOST`: SMTP server host (for Gmail, `smtp.gmail.com`).
	- `SMTP_PORT`: SMTP port (`587` for TLS, `465` for SSL).
	- `SMTP_AUTH`: Usually `true` when login is required.
	- `SMTP_SECURE`: Encryption type (`tls` or `ssl`).
	- `SMTP_USER`: SMTP account email/username.
	- `SMTP_PASS`: SMTP password (for Gmail, use an App Password).
	- `SMTP_FROM_NAME`: Sender name shown in emails.
	- `AT_USERNAME`: Africa's Talking username (`sandbox` for sandbox mode).
	- `AT_API_KEY`: Africa's Talking API key.
	- `AT_SENDER_ID`: Sender ID for SMS (can be blank in sandbox depending on account setup).

	Notes:
	- Do not commit real credentials to git.
	- If SMS features are not used yet, keep Africa's Talking values set but non-production.
	- `config.php` reads these values from `.env`.
5. Ensure PHP has required extensions enabled:
	 - `mysqli`
	 - `pdo_mysql`
6. Start your web server and open:
	 - `http://localhost/smss/index.php`

## Default Seed Accounts

The bundled SQL dump includes sample users. Example admin account in seed data:

- Email: `admin@gmail.com`
- Password: stored as a hash in DB (use password reset flow or create your own user if needed)

## Security Notes

- Keep credentials in `.env` and out of source control.
- Use environment-specific secrets for DB and SMTP credentials.
- Disable debug output in production.
- Serve the app over HTTPS in production.

## Common Troubleshooting

- Database connection error:
	- Verify DB credentials and database name in `config.php`.
	- Confirm MySQL service is running.
- Email not sending:
	- Verify SMTP host/port/security values.
	- If using Gmail, use an App Password and allow SMTP access.
- Blank page / 500 error:
	- Check web server and PHP error logs.
	- Confirm required PHP extensions are enabled.

## Notes for Development

- This project uses a traditional PHP page structure (not MVC).
- Business logic and presentation are mixed in multiple files; refactoring to controllers/services can improve maintainability.

## License

No explicit license file is currently included in this repository.
Add a `LICENSE` file if you plan to distribute or open-source the project.
