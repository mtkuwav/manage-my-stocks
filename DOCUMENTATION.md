# API Documentation

## Table of Contents
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

4. [Categories](#categories)
   - [Create Category](#create-category)
   - [Update Category](#update-category)
   - [Get Category](#get-category)
   - [List Categories](#list-categories)
   - [Delete Category](#delete-category)

5. [Error Handling](#error-handling)
   - [Error Responses](#error-responses)

6. [Security Information](#security-information)
   - [Token Security](#token-security)

## **IMPORTANT NOTE**
At first, the database will only contain one admin user so that this user can promote other newly registered users to admin role. This is made so for security reasons so that no user can register as an admin. Here are their credenials, **DO NOT FORGET TO UPDATE THEM OR DELETE THIS USER AFTERWARDS.**

**email**: ``admin@boutique.com`` <br>
**password**: ``adminpswd``

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

### List Users Profile (with optional limit)
- **Route**: `GET /users` (add `?limit=[the limit you want]` if you want limited results)
- **Access**: Private (Admin users)
- **Description**: Retrieve the profile of all users in an array or the number of them specified

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

### List Products (with optional limit)
- **Route**: `GET /products` (add `?limit=[the limit you want]` if you want limited results)
- **Access**: Private (admin, manager)
- **Description**: List all products or the number of products specified

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

## Products Logs

### Get All Logs (with optional limit)

- **Route**: `GET /inventory-logs` (add `?limit=[the limit you want]` if you want limited results)
- **Access**: Private (Admin and manager users)
- **Description**: Retrieve the profile of all users in an array or the number of them specified

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
        "username": "john.doe"
    },
    {
        "id": "2",
        "product_id": "2",
        "old_quantity": "0",
        "new_quantity": "25",
        "change_type": "initial",
        "created_at": "2021-01-01T00:00:00.000Z",
        "username": "john.doe"
    },
    // etc.
]
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
- **Description**: List all categories

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
