# Event Management System - Documentation

## Project Structure

This is a comprehensive event management system with three main sectors:

### 1. Admin Dashboard (`/admin`)

- Dashboard overview
- Tickets management
- Events management
- Users management
- Clients management

### 2. Client Dashboard (`/client`)

- Dashboard overview
- Tickets management
- Events management
- Users management
- Media management

### 3. Public Landing Page (`/public`)

- Home page
- Events listing
- Event details
- Search functionality
- Checkout process

## Technology Stack

- **Frontend**: HTML, CSS, JavaScript
- **Backend**: PHP
- **Database**: MySQL
- **APIs**: Google (Sign-in, Maps), SMS, Paystack (Payment), Barcode, Email, Real-time Notifications

## Directory Structure

```
event-management-system/
├── admin/              # Admin dashboard
├── client/             # Client dashboard
├── public/             # Public landing page
├── api/                # API endpoints
├── config/             # Configuration files
├── database/           # Database schema and seeds
├── assets/             # Static assets (images, fonts, etc.)
├── includes/           # PHP classes, helpers, middleware
├── vendor/             # Third-party libraries
├── uploads/            # User uploaded files
├── logs/               # Application logs
└── docs/               # Documentation
```

## Setup Instructions

1. Extract the ZIP file to your web server directory
2. Configure database settings in `config/database.php`
3. Import database schema from `database/schema.sql`
4. Configure API keys in respective config files
5. Set proper permissions for `uploads/` and `logs/` directories
6. Access the application through your web browser

## API Configuration

Configure the following API keys in their respective config files:

- Google API (Sign-in, Maps): `config/google.php`
- Paystack Payment: `config/payment.php`
- SMS API: `config/sms.php`
- Email: `config/email.php`

## Future Extensibility

The project structure is designed to be scalable and flexible:

- Add new pages in the respective `pages/` directories
- Add new API endpoints in the `api/` directory
- Add new components in the `components/` directories
- Extend classes in `includes/classes/`
- Add new helpers in `includes/helpers/`
- Add new middleware in `includes/middleware/`

## Security Features

- Authentication middleware
- CORS protection
- Rate limiting
- Input validation
- Secure file uploads

## Support

For questions or issues, refer to the documentation in the `docs/` directory.
