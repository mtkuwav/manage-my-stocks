# Inventory Management API

## Overview

This API provides a comprehensive solution for small business owners to manage their inventory in real-time. Built with PHP and Docker, it offers secure endpoints for stock management, order processing, and product management.

## Features

- 🔐 JWT Authentication
- 📦 Complete Product Management (CRUD)
- 🗂️ Stock Level Tracking
- 📋 Order Processing System
- 🚚 Delivery Management
- ↩️ Returns Processing

## Technical Requirements

- Docker
- Docker Compose
- Composer (for local development)

## Quick Start

1. Clone the repository and setup environment:
```sh
git clone <repository-url>
cd php-docker-api
cp .env.sample .env
```

2. Configure your `.env` file with appropriate values:
```env
DB_NAME=inventory_db
DB_USER=your_user
DB_PASSWORD=your_password
DB_ROOT_PASSWORD=your_root_password
DB_PORT=3306
PHPMYADMIN_PORT=8090
JWT_SECRET=your_secret_key
```

3. Install dependencies and start the containers:
```sh
cd app && composer install && cd ../
docker-compose up -d
```

4. (Optional) If you want to populate the database, execute this PHP script :
```sh
php app/src/scripts/populate_db.php
```
This will do it automatically for you.

## Database diagram

You can consult the database diagram either with [the pdf file](https://github.com/mtkuwav/manage-my-stocks/blob/main/database%20diagram.pdf) in the repo or directly in dbdiagram.io with [this link](https://dbdiagram.io/d/inventory-final-67ba1182263d6cf9a018de50).

## API Endpoints

### Authentication
- POST `/api/auth/register` - Register new user
- POST `/api/auth/login` - Login and receive JWT token

### Products
- GET `/api/products` - List all products
- GET `/api/products/{id}` - Get single product
- POST `/api/products` - Create new product
- PUT `/api/products/{id}` - Update product
- DELETE `/api/products/{id}` - Delete product

 ### Orders
- GET `/api/orders` - List all orders
- POST `/api/orders` - Create new order
- PUT `/api/orders/{id}` - Update order status
- DELETE `/api/orders/{id}` - Cancel order

### **And more availible [on the doc !](./DOCUMENTATION.md)**

## Development Access

- API Base URL: [http://localhost/](http://localhost/)
- phpMyAdmin: [http://localhost:8090](http://localhost:8090)
<!-- - API Documentation: [http://localhost/api/docs](http://localhost/api/docs) -->

## Security

All API endpoints (except authentication) require a valid JWT token in the Authorization header:
```
Authorization: Bearer <your_jwt_token>
```

## Error Handling

The API uses standard HTTP status codes and returns JSON responses:
```json
{
    "message": "Error description"
}
```

## Testing

Run the test suite with:
```sh
docker-compose exec php vendor/bin/phpunit
```

## Contributing

1. Fork the repository
2. Create your feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details.
