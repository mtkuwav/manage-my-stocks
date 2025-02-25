# API Documentation

## Table of Contents
- [IMPORTANT](#important-note)
- [Filters](#filters)
1. [Authentication](#authentication)
   - [Register](#register)
   - [Login](#login)
   - [Refresh Token](#refresh-token)
   - [Logout](#logout)
   - [Logout All Sessions](#logout-all-sessions)
2. [Users](#users)
   - [Promote User to Admin](#promote-a-manager-user-to-admin-role)
   - [Get User Profile](#get-user-profile-with-its-id)
   - [Get All Users](#get-all-users-profile-with-optional-limit)
   - [Update User](#update-user)
   - [Update Password](#update-user-password)
   - [Delete User](#delete-user)

3. [Products](#products)
   - [Create Product](#create-product)
   - [Update Product](#update-product)
   - [Get Product](#get-product)
   - [List Products](#list-products)
   - [Delete Product](#delete-product)

4. [Orders](#orders)
   - [Create Order](#create-order)
   - [Get Order](#get-order)
   - [List Orders](#get-all-orders)
   - [Get Order Statistics](#get-order-statistics)
   - [Update Order Status](#update-order-status)
   - [Cancel Order](#cancel-order)

5. [Deliveries](#deliveries)
   - [Create Delivery](#create-delivery)
   - [Update Delivery Status](#update-delivery-status)
   - [Get Delivery](#get-delivery)
   - [List Deliveries](#list-deliveries)
   - [Get Order Deliveries](#get-order-deliveries)

6. [Returns](#returns)
   - [Create Return](#create-return)
   - [Process Return](#process-return)
   - [Get Return](#get-return)
   - [List Returns](#list-returns)
   - [Get Returns by Product](#get-returns-by-product)
   - [Get Returns Statistics](#get-returns-statistics)

7. [Inventory Logs](#inventory-logs)
   - [List logs](#list-logs-with-optional-limit)
   - [Get A Log](#get-log)

8. [Categories](#categories)
   - [Create Category](#create-category)
   - [Update Category](#update-category)
   - [Get Category](#get-category)
   - [List Categories](#list-categories)
   - [Delete Category](#delete-category)

9. [Error Handling](#error-handling)
   - [Error Responses](#error-responses)

10. [Security Information](#security-information)
   - [Token Security](#token-security)

## **IMPORTANT NOTE**
At first, the database will only contain one admin user so that this user can promote other newly registered users to admin role. This is made so for security reasons so that no user can register as an admin. Here are their credenials, **DO NOT FORGET TO UPDATE THEM OR DELETE THIS USER AFTERWARDS.**

**email**: ``admin@boutique.com`` <br>
**password**: ``adminpswd``

## Filters

The API supports filtering for list endpoints (GET methods returning multiple items). Filters can be combined to refine results.

### Common Filters
All list endpoints support these basic filters:
```json
{
    "date_from": "2025-01-01",  // Filter items from this date
    "date_to": "2025-12-31",    // Filter items up to this date
    "limit": 10                 // Limit number of returned items
}
```

### Resource-Specific Filters

#### Orders
```json
{
    "status": "pending",        // Filter by order status
    "user_id": 1,              // Filter by user ID
    "min_amount": 50.00,       // Minimum order amount
    "max_amount": 200.00       // Maximum order amount
}
```

#### Products
```json
{
    "category_id": 1,          // Filter by category
    "price_min": 10.00,        // Minimum price
    "price_max": 100.00        // Maximum price
}
```

#### Inventory Logs
```json
{
    "change_type": "adjustment", // Filter by change type
    "product_id": 1             // Filter by product ID
}
```

#### Users
```json
{
    "role": "manager",          // Filter by user role
    "username": "john"          // Filter by username (partial match)
}
```

#### Deliveries
```json
{
    "status": "pending",        // Filter by delivery status
    "order_id": 1              // Filter by order ID
}
```

### Usage Examples

1. Get orders from last month with status 'pending':
```
GET /orders?date_from=2025-01-01&date_to=2025-01-31&status=pending
```

2. Get products in price range:
```
GET /products?price_min=10.00&price_max=100.00&limit=5
```

3. Get inventory logs for a specific product:
```
GET /inventory-logs?product_id=1&change_type=adjustment
```

4. Get manager users created this year:
```
GET /users?role=manager&date_from=2025-01-01
```

### Notes
- All date filters must be in `YYYY-MM-DD` format
- Price and amount filters must be decimal numbers
- IDs must be integers
- Text filters are case-insensitive
- Multiple filters can be combined using `&`
- Invalid or unknown filter parameters are ignored

## Authentication

### Authentication Flow
The API uses a JWT (JSON Web Token) based authentication system with refresh tokens. Here's how it works:

1. User logs in with credentials
2. Server provides:
   - Access token (valid for 1 hour)
   - Refresh token (valid for 7 days)
3. Client uses access token for API requests
4. When access token expires, use refresh token to get a new access token
5. If refresh token expires, user must login again

### Register
- **Route**: `POST /auth/register`
- **Access**: Public
- **Description**: Register a new user account

**Request Body**:
```json
{
    "username": "johndoe",
    "email": "john.doe@example.com",
    "password": "password123"
}
```

**Success Response** (200):
```json
{
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "ae2f8d91c9b8e0e4...",
    "expires_in": 3600,
    "token_type": "Bearer"
}
```

**Error Response** (400):
```json
{
    "error": "User already exists!"
}
```

### Login
- **Route**: `POST /auth/login`
- **Access**: Public
- **Description**: Authenticate a user and return both access and refresh tokens

**Request Body**:
```json
{
    "email": "john.doe@example.com",
    "password": "password123"
}
```

**Success Response** (200):
```json
{
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "ae2f8d91c9b8e0e4...",
    "expires_in": 3600,
    "token_type": "Bearer"
}
```

**Error Response** (401):
```json
{
    "error": "Invalid credentials"
}
```

### Refresh Token
- **Route**: `PUT /auth/refresh`
- **Access**: Public
- **Description**: Get a new access token using a valid refresh token

**Request Body**:
```json
{
    "refresh_token": "ae2f8d91c9b8e0e4..."
}
```

**Success Response** (200):
```json
{
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 3600,
    "token_type": "Bearer"
}
```

**Error Response** (401):
```json
{
    "error": "Invalid or expired refresh token"
}
```

### Logout
- **Route**: `DELETE /auth/logout`
- **Access**: Protected (requires valid access token)
- **Description**: Revoke the current refresh token
- **Headers Required**: 
  - `Authorization: Bearer <access_token>`

**Request Body**:
```json
{
    "refresh_token": "ae2f8d91c9b8e0e4..."
}
```

**Success Response** (200):
```json
{
    "message": "Successfully logged out"
}
```

### Logout All Sessions
- **Route**: `DELETE /auth/logout/all`
- **Access**: Protected (requires valid access token)
- **Description**: Revoke all refresh tokens for the user
- **Headers Required**: 
  - `Authorization: Bearer <access_token>`

**Success Response** (200):
```json
{
    "message": "Successfully logged out from all sessions"
}
```

### Update User Password
- **Route**: `PATCH /auth/update-password`
- **Access**: Private (Admin and manager users)
- **Description**: Update the authenticated user's password based on its bearer token

**Request**:
```json
{
    "current_password": "current123",
    "new_password": "new123"
}
```

**Response**:
```json
{
    "message": "Password updated successfully"
}
```

## Users

### Promote a manager user to admin role
- **Route**: `POST /users/:id/promote`
- **Access**: Private (Admin users)
- **Description**: Promote a manager user to admin role

**Response**:
```json
{
    "id": 1,
    "username": "John Doe",
    "password_hash": "$2y$10$fV3K74l...",
    "email": "john.doe@example.com",
    "role": "admin",
    "created_at": "2025-01-01T00:00:00.000Z",
    "updated_at": "2025-01-01T00:00:00.000Z"
}
```

### Get User Profile with its ID
- **Route**: `GET /users/:id`
- **Access**: Private (Admin users)
- **Description**: Retrieve the profile of the user from their ID

**Response**:
```json
{
    "id": 1,
    "username": "John Doe",
    "password_hash": "$2y$10$fV3K74l...",
    "email": "john.doe@example.com",
    "role": "admin",
    "created_at": "2025-01-01T00:00:00.000Z",
    "updated_at": "2025-01-01T00:00:00.000Z"
}
```

### List Users Profile
- **Route**: `GET /users`
- **Access**: Private (Admin users)
- **Description**: List users with optional filters

**Query Parameters**:
```json
{
    "limit": 10,              // Optional: Limit number of results
    "date_from": "2025-01-01", // Optional: Filter from date
    "date_to": "2025-12-31",   // Optional: Filter to date
    "role": "manager",         // Optional: Filter by role
    "username": "john"         // Optional: Filter by username (partial match)
}
```

**Response**:
```json
[
    {
        "id": 1,
        "username": "John Doe",
        "email": "john.doe@example.com",
        "role": "admin",
        "created_at": "2025-01-01T00:00:00.000Z",
        "updated_at": "2025-01-01T00:00:00.000Z"
    },
    {
        "id": 2,
        "username": "Alice Bob",
        "email": "alice.bob@example.com",
        "role": "manager",
        "created_at": "2025-01-01T00:00:00.000Z",
        "updated_at": "2025-01-01T00:00:00.000Z"
    },
    // etc.
]
```

### Update User
- **Route**: `PATCH /users/:id`
- **Access**: Private (Admin users)
- **Description**: Update user information (username and/or email)

**Request**:
```json
{
    "username": "Updated Username",
    "email": "updated.email@example.com"
}
```

**Response**:
```json
{
    "id": 1,
    "username": "Updated Username",
    "email": "updated.email@example.com",
    "role": "manager",
    "created_at": "2025-01-01T00:00:00.000Z",
    "updated_at": "2025-01-01T00:00:00.000Z"
}
```

### Delete user
- **Route**: `DELETE /users/:id`
- **Access**: Private (Admin users)
- **Description**: Deletes an user of the system

**Response**:
```json
{
    "message": "User successfully deleted"
}
```

## Products

### Create Product
- **Route**: `POST /products`
- **Access**: Private (admin, manager)
- **Description**: Create a new product

**Request**:
```json
{
    "name": "Product Name",
    "price": 99.99,
    "category_id": 1,
    "description": "Product Description",
    "quantity_in_stock": 10
}
```

**Response**:
```json
{
    "id": 1,
    "name": "Product Name",
    "description": "Product Description",
    "SKU": "PROD-DES-0000000000",
    "price": 99.99,
    "quantity_in_stock": 10,
    "category_id": 1,
    "created_at": "2021-01-01T00:00:00.000Z",
    "updated_at": "2021-01-01T00:00:00.000Z"
}
```

### Update Product
- **Route**: `PATCH /products/{id}`
- **Access**: Private (admin, manager)
- **Description**: Update product details

**Request**:
```json
{
    "name": "Updated Name",
    "price": 149.99,
    "quantity_in_stock": 15
}
```

**Response**:
```json
{
    "id": 1,
    "name": "Updated Name",
    "description": "Product Description",
    "SKU": "PROD-DES-0000000000",
    "price": 99.99,
    "quantity_in_stock": 10,
    "category_id": 1,
    "created_at": "2021-01-01T00:00:00.000Z",
    "updated_at": "2021-01-01T00:00:00.000Z"
}
```

### Get Product
- **Route**: `GET /products/{id}`
- **Access**: Private (admin, manager)
- **Description**: Retrieve product details

**Response**:
```json
{
    "id": 1,
    "name": "Product Name",
    "description": "Product Description",
    "SKU": "PROD-DES-0000000000",
    "price": 99.99,
    "quantity_in_stock": 10,
    "category_id": 1,
    "created_at": "2021-01-01T00:00:00.000Z",
    "updated_at": "2021-01-01T00:00:00.000Z"
}
```

### List Products
- **Route**: `GET /products`
- **Access**: Private (admin, manager)
- **Description**: List products with optional filters

**Query Parameters**:
```json
{
    "limit": 10,              // Optional: Limit number of results
    "date_from": "2025-01-01", // Optional: Filter from date
    "date_to": "2025-12-31",   // Optional: Filter to date
    "category_id": 1,          // Optional: Filter by category
    "price_min": 10.00,        // Optional: Minimum price
    "price_max": 100.00        // Optional: Maximum price
}
```

**Response**:
```json
[
    {
        "id": 1,
        "name": "Product Name",
        "description": "Product Description",
        "SKU": "PROD-DES-0000000000",
        "price": 99.99,
        "quantity_in_stock": 10,
        "category_id": 1,
        "created_at": "2021-01-01T00:00:00.000Z",
        "updated_at": "2021-01-01T00:00:00.000Z"
    },
    // ...
]
```

### Delete Product
- **Route**: `DELETE /products/{id}`
- **Access**: Private (admin, manager)
- **Description**: Delete a product

**Response**:
```json
{
    "message": "Product deleted successfully"
}
```

## Inventory Logs

### List Inventory Logs
- **Route**: `GET /inventory-logs`
- **Access**: Private (Admin and manager users)
- **Description**: List inventory logs with optional filters

**Query Parameters**:
```json
{
    "limit": 10,              // Optional: Limit number of results
    "date_from": "2025-01-01", // Optional: Filter from date
    "date_to": "2025-12-31",   // Optional: Filter to date
    "change_type": "initial",  // Optional: Filter by change type
    "product_id": 1           // Optional: Filter by product ID
}
```

**Response**:
```json
[
    {
        "id": "1",
        "product_id": "1",
        "old_quantity": "0",
        "new_quantity": "10",
        "change_type": "initial",
        "created_at": "2021-01-01T00:00:00.000Z",
        "username": "john.doe",
        "product_name": "Product name",
        "product_sku": "PROD-DES-0000000000"
    },
    // ...
]
```

### Get Log
- **Route**: `GET /inventory-logs/{id}`
- **Access**: Private (admin, manager)
- **Description**: Retrieve a single log details

**Response**:
```json
{
    "id": "1",
    "product_id": "1",
    "old_quantity": "0",
    "new_quantity": "10",
    "change_type": "initial",
    "created_at": "2021-01-01T00:00:00.000Z",
    "username": "john.doe",
    "product_name": "Product name",
    "product_sku": "PROD-DES-0000000000"
}
```

## Orders

### Create Order
- **Route**: `POST /orders`
- **Access**: Private (admin, manager)
- **Description**: Create a new order and update product stock accordingly

**Request**:
```json
{
    "items": [
        {
            "product_id": 1,
            "quantity": 2
        },
        {
            "product_id": 2,
            "quantity": 1
        }
    ]
}
```

**Response**:
```json
{
    "id": "3",
    "user_id": "1",
    "order_date": "2021-01-01T00:00:00.000Z",
    "status": "processing",
    "total_amount": "2649.97",
    "created_at": "2021-01-01T00:00:00.000Z",
    "updated_at": "2021-01-01T00:00:00.000Z",
    "user_name": "john.doe",
    "items": [
        {
            "id": "1",
            "order_id": "3",
            "product_id": "1",
            "quantity": "2",
            "unit_price": "1299.99",
            "created_at": "2021-01-01T00:00:00.000Z",
            "updated_at": "2021-01-01T00:00:00.000Z",
            "product_name": "Laptop Dell XPS 13",
            "product_sku": "ELEC-LAP-1740092136",
            "current_price": "1299.99"
        },
        {
            "id": "2",
            "order_id": "3",
            "product_id": "2",
            "quantity": "1",
            "unit_price": "49.99",
            "created_at": "2021-01-01T00:00:00.000Z",
            "updated_at": "2021-01-01T00:00:00.000Z",
            "product_name": "The Art of Programming",
            "product_sku": "BOOK-THE-1740092136",
            "current_price": "49.99"
        }
    ]
}
```

### Get Order
- **Route**: `GET /orders/{id}`
- **Access**: Private (admin, manager)
- **Description**: Retrieve order details with its items

**Response**: Same as create order response

### List Orders
- **Route**: `GET /orders`
- **Access**: Private (admin, manager)
- **Description**: Get all orders with optional filters

**Query Parameters**:
```json
{
    "limit": 10,              // Optional: Limit number of results
    "status": "pending",      // Optional: Filter by order status
    "user_id": 1,            // Optional: Filter by user
    "date_from": "2025-01-01", // Optional: Filter from date
    "date_to": "2025-12-31",   // Optional: Filter to date
    "min_amount": 50.00,      // Optional: Minimum order amount
    "max_amount": 200.00      // Optional: Maximum order amount
}
```

**Response**:
```json
[
    {
        "id": 1,
        "user_id": 1,
        "status": "pending",
        "total_amount": 149.99,
        "created_at": "2025-01-01T00:00:00.000Z",
        "updated_at": "2025-01-01T00:00:00.000Z",
        "user_name": "john.doe",
        "items": [
            // ... items array
        ]
    }
    // ... more orders
]
```

### Get Order Statistics
- **Route**: `GET /orders/statistics`
- **Access**: Private (admin only)
- **Description**: Get order statistics with optional filters

**Query Parameters**:
```json
{
    "status": "completed",     // Optional: Filter by status
    "date_from": "2025-01-01", // Optional: From date
    "date_to": "2025-12-31"    // Optional: To date
}
```

**Response**:
```json
{
    "total_orders": 50,
    "total_revenue": 15000.00,
    "completed_orders": 40,
    "cancelled_orders": 5,
    "average_order_value": 300.00
}
```

### Update Order Status
- **Route**: `PATCH /orders/{id}/status`
- **Access**: Private (admin, manager)
- **Description**: Update the status of an order

**Request**:
```json
{
    "status": "processing"
}
```

**Available Statuses**:
- `pending`: Order is awaiting processing
- `processing`: Order is being processed
- `completed`: Order has been completed
- `cancelled`: Order has been cancelled

**Response**: Same as create order response but with updated status

### Cancel Order
- **Route**: `POST /orders/{id}/cancel`
- **Access**: Private (admin only)
- **Description**: Cancel an order and restore product stock

**Response**: Same as get order response but with status "cancelled"

## Deliveries

### Create Delivery
- **Route**: `POST /deliveries`
- **Access**: Private (admin, manager)
- **Description**: Create a new delivery for an order

**Request**:
```json
{
    "order_id": 1
}
```

**Response**:
```json
{
    "id": 1,
    "order_id": 1,
    "status": "pending",
    "created_at": "2025-01-01T00:00:00.000Z",
    "updated_at": "2025-01-01T00:00:00.000Z",
    "order_details": {
        "user_name": "john.doe",
        "total_amount": 149.99,
        "items": [
            {
                "product_name": "Laptop Dell XPS 13",
                "quantity": 1,
                "unit_price": 149.99
            }
        ]
    }
}
```

### Update Delivery Status
- **Route**: `PATCH /deliveries/{id}`
- **Access**: Private (admin, manager)
- **Description**: Update the status of a delivery

**Request**:
```json
{
    "status": "delivered"
}
```

**Available Statuses**:
- `pending`: Delivery is being prepared
- `shipped`: Delivery is on its way
- `delivered`: Delivery has been completed
- `failed`: Delivery attempt failed

**Response**:
```json
{
    "id": 1,
    "order_id": 1,
    "status": "in_transit",
    "created_at": "2025-01-01T00:00:00.000Z",
    "updated_at": "2025-01-01T00:00:00.000Z",
    "order_details": {
        "user_name": "john.doe",
        "total_amount": 149.99,
        "items": [
            {
                "product_name": "Laptop Dell XPS 13",
                "quantity": 1,
                "unit_price": 149.99
            }
        ]
    }
}
```

### Get Delivery
- **Route**: `GET /deliveries/{id}`
- **Access**: Private (admin, manager)
- **Description**: Get details of a specific delivery

**Response**: Same as update delivery status response

### List Deliveries
- **Route**: `GET /deliveries`
- **Access**: Private (admin, manager)
- **Description**: List all deliveries with optional filters

**Query Parameters**:
```json
{
    "status": "pending",        // Optional: Filter by status
    "date_from": "2025-01-01", // Optional: Filter from date
    "date_to": "2025-12-31",   // Optional: Filter to date
    "limit": 10               // Optional: Limit number of results
}
```

**Response**:
```json
[
    {
        "id": 1,
        "order_id": 1,
        "status": "pending",
        "created_at": "2025-01-01T00:00:00.000Z",
        "updated_at": "2025-01-01T00:00:00.000Z",
        "order_details": {
            "user_name": "john.doe",
            "total_amount": 149.99,
            "items": [
                {
                    "product_name": "Laptop Dell XPS 13",
                    "quantity": 1,
                    "unit_price": 149.99
                }
            ]
        }
    }
    // ... more deliveries
]
```

### Get Order Deliveries
- **Route**: `GET /orders/{id}/deliveries`
- **Access**: Private (admin, manager)
- **Description**: Get all deliveries for a specific order

**Query Parameters**: Same as List Deliveries

**Response**: Same format as List Deliveries

## Returns

### Create Return
- **Route**: `POST /returns`
- **Access**: Private (admin, manager)
- **Description**: Create a new return request

**Request**:
```json
{
    "order_item_id": 1,
    "quantity_returned": 2,
    "reason": "Defective product"
}
```

**Response**:
```json
{
    "id": 1,
    "order_item_id": 1,
    "quantity_returned": 2,
    "reason": "Defective product",
    "status": "requested",
    "created_at": "2025-01-01T00:00:00.000Z",
    "updated_at": "2025-01-01T00:00:00.000Z"
}
```

### Process Return
- **Route**: `PATCH /returns/{id}`
- **Access**: Private (admin, manager)
- **Description**: Process a return request (approve or reject)

**Request**:
```json
{
    "status": "approved"  // or "rejected"
}
```

**Response**:
```json
{
    "id": 1,
    "order_item_id": 1,
    "quantity_returned": 2,
    "reason": "Defective product",
    "status": "approved",
    "processed_by": 1,
    "created_at": "2025-01-01T00:00:00.000Z",
    "updated_at": "2025-01-01T00:00:00.000Z"
}
```

### Get Return
- **Route**: `GET /returns/{id}`
- **Access**: Private (admin, manager)
- **Description**: Get details of a specific return

**Response**:
```json
{
    "id": 1,
    "order_item_id": 1,
    "quantity_returned": 2,
    "reason": "Defective product",
    "status": "requested",
    "processed_by": 1,
    "created_at": "2025-01-01T00:00:00.000Z",
    "updated_at": "2025-01-01T00:00:00.000Z",
    "processed_by_username": "john.doe",
    "product_name": "Product Name",
    "product_sku": "PROD-123",
    "ordered_quantity": 5
}
```

### List Returns
- **Route**: `GET /returns`
- **Access**: Private (admin, manager)
- **Description**: List all returns with optional filters

**Query Parameters**:
```json
{
    "status": "requested",     // Optional: Filter by status
    "date_from": "2025-01-01", // Optional: Filter from date
    "date_to": "2025-12-31",   // Optional: Filter to date
    "limit": 10               // Optional: Limit number of results
}
```

**Response**:
```json
[
    {
        "id": 1,
        "order_item_id": 1,
        "quantity_returned": 2,
        "reason": "Defective product",
        "status": "requested",
        "processed_by": 1,
        "created_at": "2025-01-01T00:00:00.000Z",
        "updated_at": "2025-01-01T00:00:00.000Z",
        "processed_by_username": "john.doe",
        "product_name": "Product Name",
        "product_sku": "PROD-123",
        "ordered_quantity": 5
    },
    // ... more returns
]
```

### Get Returns by Product
- **Route**: `GET /products/{id}/returns`
- **Access**: Private (admin, manager)
- **Description**: Get all returns for a specific product

**Query Parameters**: Same as List Returns

**Response**: Same format as List Returns

### Get Returns Statistics
- **Route**: `GET /returns/statistics`
- **Access**: Private (admin only)
- **Description**: Get returns statistics with optional filters

**Query Parameters**:
```json
{
    "date_from": "2025-01-01", // Optional: From date
    "date_to": "2025-12-31"    // Optional: To date
}
```

**Response**:
```json
{
    "total_returns": 50,
    "approved_returns": 40,
    "rejected_returns": 5,
    "pending_returns": 5,
    "total_items_returned": 75,
    "avg_return_quantity": 1.5
}
```

## Categories

### Create Category
- **Route**: `POST /categories`
- **Access**: Private (admin, manager)
- **Description**: Create a new category

**Request**:
```json
{
    "name": "Category Name"
}
```

**Response**:
```json
{
    "id": 1,
    "name": "Category Name"
}
```

### Update Category
- **Route**: `PATCH /categories/{id}`
- **Access**: Private (admin, manager)
- **Description**: Update category details

**Request**:
```json
{
    "name": "Updated Category Name"
}
```

**Response**:
```json
{
    "id": 1,
    "name": "Updated Category Name",
    "created_at": "2021-01-01T00:00:00.000Z",
    "updated_at": "2021-01-01T00:00:00.000Z"
}
```

### Get Category
- **Route**: `GET /categories/{id}`
- **Access**: Private (admin, manager)
- **Description**: Retrieve category details

**Response**:
```json
{
    "id": 1,
    "name": "Category Name",
    "created_at": "2021-01-01T00:00:00.000Z",
    "updated_at": "2021-01-01T00:00:00.000Z"
}
```

### List Categories
- **Route**: `GET /categories`
- **Access**: Private (admin, manager)
- **Description**: List categories with optional filters

**Query Parameters**:
```json
{
    "limit": 10,              // Optional: Limit number of results
    "date_from": "2025-01-01", // Optional: Filter from date
    "date_to": "2025-12-31"    // Optional: Filter to date
}
```

**Response**:
```json
[
    {
        "id": 1,
        "name": "Category Name",
        "created_at": "2021-01-01T00:00:00.000Z",
        "updated_at": "2021-01-01T00:00:00.000Z"
    },
    // ...
]
```

### Delete Category
- **Route**: `DELETE /categories/{id}`
- **Access**: Private (admin, manager)
- **Description**: Delete a category

**Response**:
```json
{
    "message": "Category deleted successfully"
}
```

## Error Handling

### Error Responses
- **400 Bad Request**: Invalid request data
- **401 Unauthorized**: Authentication required
- **403 Forbidden**: Insufficient permissions
- **404 Not Found**: Resource not found
- **500 Internal Server Error**: Server error

**Example**:
```json
{
    "error": "Invalid request data"
}
```

## Security Information

### Token Security
- Access tokens expire after 1 hour
- Refresh tokens expire after 7 days
- Maximum 5 active sessions per user (that is to say 5 valid refresh tokens per user)
- Oldest sessions are automatically invalidated when limit is reached
