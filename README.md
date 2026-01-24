# Leave Management API

A comprehensive RESTful API for managing employee leave requests with role-based access control and approval workflows.

## 📋 Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Technology Stack](#technology-stack)
- [Installation & Setup](#installation--setup)
- [API Documentation](#api-documentation)
- [Database Schema](#database-schema)
- [Architecture Decisions](#architecture-decisions)
- [Testing](#testing)
- [Known Limitations](#known-limitations)

---

## 🎯 Overview

This Leave Management API is designed to handle employee leave requests with a three-tier approval hierarchy:
- **General Users** submit leave requests → require HR approval
- **HR Users** submit leave requests → require Admin approval  
- **Admin Users** submit leave requests → auto-approved

The system supports full-day, half-day, and multi-day leave requests with automatic leave balance tracking and validation.

---

## ✨ Features

### User Management
- ✅ User registration and authentication (JWT via Laravel Sanctum)
- ✅ Role-based access control (Admin, HR, General)
- ✅ Secure password hashing

### Leave Request Management
- ✅ Submit leave requests (full-day, half-day, multi-day)
- ✅ Approve/reject leave requests based on role hierarchy
- ✅ View leave history with filtering (by status) and pagination
- ✅ Prevent overlapping approved leave requests
- ✅ Date range validation

### Leave Balance Tracking
- ✅ Annual leave entitlement (30 days default)
- ✅ Real-time leave balance calculation
- ✅ Used days tracking (approved leaves only)
- ✅ Remaining balance display
- ✅ Insufficient balance prevention

### Security & Validation
- ✅ Role-based authorization middleware
- ✅ Input validation on all endpoints
- ✅ Business logic validation (overlapping, balance, dates)
- ✅ Consistent error handling

---

## 🛠 Technology Stack

- **Framework:** Laravel 10.x
- **Authentication:** Laravel Sanctum (Bearer Token)
- **Database:** MySQL
- **API Documentation:** OpenAPI 3.0 / Swagger (via L5-Swagger)
- **PHP Version:** 8.1+

---

## 📦 Installation & Setup

### Prerequisites
- PHP >= 8.1
- Composer
- MySQL >= 5.7

### Installation Steps

```bash
# 1. Install Dependencies
composer install

# 2. Environment Setup
cp .env.example .env
php artisan key:generate

# 3. Configure Database (.env file)
DB_CONNECTION=mysql
DB_DATABASE=leave_management
DB_USERNAME=root
DB_PASSWORD=

# 4. Create Database
mysql -u root -p
CREATE DATABASE leave_management;

# 5. Run Migrations
php artisan migrate

# 6. Seed Database (Optional)
php artisan db:seed

# 7. Install Swagger
composer require "darkaonline/l5-swagger"
php artisan vendor:publish --provider "L5Swagger\L5SwaggerServiceProvider"
php artisan l5-swagger:generate

# 8. Start Server
php artisan serve
```

**Access:**
- API: `http://localhost:8000/api`
- Swagger Docs: `http://localhost:8000/api/documentation`

**Test Users (after seeding):**
- Admin: admin@example.com / password
- HR: hr@example.com / password
- General: john@example.com / password

---

## 📚 API Endpoints

**Authentication:**
- POST `/api/auth/register` - Register
- POST `/api/auth/login` - Login
- POST `/api/auth/logout` - Logout
- GET `/api/auth/me` - Get user

**Leave Requests:**
- GET `/api/leave-requests` - List all
- POST `/api/leave-requests` - Create
- GET `/api/leave-requests/{id}` - Get one
- POST `/api/leave-requests/{id}/approve` - Approve (HR/Admin)
- POST `/api/leave-requests/{id}/reject` - Reject (HR/Admin)

**Leave Balance:**
- GET `/api/leave-balance` - Own balance
- GET `/api/leave-balance/all` - All users (HR/Admin)

**Full Documentation:** Visit Swagger UI at `http://localhost:8000/api/documentation`

---

## 🗄 Database Schema

**users:** id, name, email, password, role (admin/hr/general), annual_leave_entitlement (30)

**leave_requests:** id, user_id, leave_type, start_date, end_date, half_day_period, reason, status, approved_by, rejection_reason, approved_at, days_count

---

## 🏗 Key Architecture Decisions

1. **Sanctum for Authentication** - Lightweight, token-based, perfect for APIs
2. **Role-based Middleware** - Simple enum roles with custom middleware
3. **Real-time Balance Calculation** - No caching, always accurate
4. **Form Request Validation** - Clean separation of concerns
5. **Business Logic in Controller** - Explicit approval hierarchy
6. **Calendar Day Calculation** - Weekends/holidays excluded per requirements

---

## 🧪 Testing

**Complete test suite with 72 tests covering all functionality:**

- **Feature Tests:** 52 tests for API endpoints
- **Unit Tests:** 20 tests for models and business logic

### Run Tests

```bash
# Create test database first
CREATE DATABASE leave_management_test;

# Run all tests
php artisan test

# Run with coverage
php artisan test --coverage
```

**Test Coverage:**
- ✅ Authentication (registration, login, logout)
- ✅ Leave requests (create, list, approve, reject)
- ✅ Leave balance calculations
- ✅ Role-based authorization
- ✅ Input validation
- ✅ Model methods and relationships

See [TESTING.md](TESTING.md) for detailed testing guide.

---

## 📄 Documentation

- **[ASSUMPTIONS.md](ASSUMPTIONS.md)** - Comprehensive design decisions and assumptions
- **[TESTING.md](TESTING.md)** - Complete testing guide and best practices
- **API Documentation** - Available at `/api/documentation` (Swagger UI)

---

## ⚠️ Known Limitations

1. **No weekend/holiday exclusion** - Calendar days only (per requirements)
2. **No leave proration** - Fixed 30 days for all (per requirements)
3. **No email notifications** - Not in scope
4. **No leave cancellation** - One-way workflow
5. **No document attachments** - Not implemented
6. **No carry forward** - Annual cycle not defined

---

## 📊 Manual Testing with Swagger

Use Swagger UI for interactive API testing:
1. Open `http://localhost:8000/api/documentation`
2. Click "Authorize" button
3. Enter Bearer token from login
4. Test all endpoints

---

## 📝 Code Quality

- PSR-12 Standards
- RESTful Design
- SOLID Principles
- Comprehensive Documentation
- Consistent Response Structure

---

**Built with Laravel**


We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[WebReinvent](https://webreinvent.com/)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Jump24](https://jump24.co.uk)**
- **[Redberry](https://redberry.international/laravel/)**
- **[Active Logic](https://activelogic.com)**
- **[byte5](https://byte5.de)**
- **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
