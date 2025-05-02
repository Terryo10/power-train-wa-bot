# Powertrain - Delivery Management System

A Laravel-based delivery management system for construction materials, featuring an admin dashboard, driver management, product catalog, order processing, and WhatsApp integration.

## Features

- **Admin Dashboard**: Built with [Filament](https://filamentphp.com/) for a modern, responsive UI
- **Product Management**: Catalog of truck loads and building materials
- **Driver Management**: Track driver availability and delivery status
- **Order Processing**: Create and manage customer orders
- **WhatsApp Integration**: Two-way communication with drivers and customers via Twilio

## System Requirements

- PHP 8.1 or higher
- Composer
- MySQL or PostgreSQL
- Laravel 10.x
- Node.js and NPM (for asset compilation)
- Twilio account for WhatsApp integration

## Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/yourusername/powertrain.git
   cd powertrain
   ```

2. Install PHP dependencies:
   ```bash
   composer install
   ```

3. Create environment file:
   ```bash
   cp .env.example .env
   ```

4. Generate application key:
   ```bash
   php artisan key:generate
   ```

5. Configure your database in `.env`:
   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=powertrain
   DB_USERNAME=root
   DB_PASSWORD=
   ```

6. Configure Twilio settings in `.env`:
   ```
   TWILIO_SID=your_twilio_sid
   TWILIO_AUTH_TOKEN=your_twilio_auth_token
   TWILIO_WHATSAPP_NUMBER=your_twilio_whatsapp_number
   ```

7. Run migrations and seed the database:
   ```bash
   php artisan migrate
   php artisan db:seed --class=ProductSeeder
   ```

8. Create a user account:
   ```bash
   php artisan make:filament-user
   ```

9. Start the development server:
   ```bash
   php artisan serve
   ```

10. Access the admin panel at `http://localhost:8000/admin`

## Project Structure

The application follows Laravel's standard structure with some additional components:

- **Admin Panel Resources**: `app/Filament/Resources/`
- **Models**: `app/Models/`
- **Services**: `app/Services/`
- **Controllers**: `app/Http/Controllers/`
- **Migrations**: `database/migrations/`
- **Routes**: `routes/`

## Core Components

### Models

- **Product**: Construction materials and truck loads
- **Driver**: Delivery personnel details and availability status
- **Order**: Customer orders with delivery information
- **OrderItem**: Line items within an order

### Admin Resources

- **ProductResource**: Manage product catalog with conditional fields
- **DriverResource**: Manage drivers and their availability
- **OrderResource**: Process orders with driver assignment capability

### Twilio Integration

The application uses Twilio for WhatsApp communication:

- **WhatsAppService**: Handles messaging logic
- **TwilioWebhookController**: Processes incoming messages

## Workflows

### Order Processing

1. Admin creates a new order via the dashboard
2. Admin assigns an available driver
3. Driver receives WhatsApp notification
4. Driver updates status via WhatsApp (Start Loading, Start Delivery, Delivered)
5. Customer receives status updates via WhatsApp

### WhatsApp Ordering

Customers can also place orders directly via WhatsApp:

1. Customer starts conversation with the business WhatsApp number
2. System guides customer through product selection and order details
3. Order is created in the system once confirmed
4. Admin can assign a driver through the dashboard

## Development

### Creating New Resources

To create a new Filament resource:

```bash
php artisan make:filament-resource ResourceName
```

### Database Changes

For database changes, create a new migration:

```bash
php artisan make:migration create_table_name
```

## Deployment

For production deployment:

1. Set appropriate values in `.env`
2. Configure a public URL for Twilio webhooks
3. Set up a queue worker for background processing:
   ```bash
   php artisan queue:work
   ```

## Security Considerations

- Ensure your Twilio webhook endpoint is properly secured
- Update JWT and session configuration for production
- Configure HTTPS for all traffic

## License

[MIT License](LICENSE)

## Credits

Developed with Laravel and Filament.