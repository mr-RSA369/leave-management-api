# TESTING GUIDE

## Prerequisites

Before running tests, ensure you have:
1. PHP 8.1 or higher installed
2. MySQL server running
3. Composer dependencies installed

## Setup Test Database

Create a separate test database to avoid affecting your development data:

```sql
CREATE DATABASE leave_management_test;
```

## Running Tests

### Run All Tests

```bash
php artisan test
```

Or using PHPUnit directly:

```bash
vendor\bin\phpunit
```

### Run Specific Test Suite

**Feature Tests Only:**
```bash
php artisan test --testsuite=Feature
```

**Unit Tests Only:**
```bash
php artisan test --testsuite=Unit
```

### Run Specific Test File

```bash
php artisan test tests/Feature/AuthenticationTest.php
```

### Run Specific Test Method

```bash
php artisan test --filter test_user_can_register_with_valid_data
```

### Run Tests with Coverage (Requires Xdebug)

```bash
php artisan test --coverage
```

## Test Structure

```
tests/
├── Feature/
│   ├── AuthenticationTest.php       # Authentication endpoints (13 tests)
│   ├── LeaveRequestTest.php         # Leave request CRUD & approval (26 tests)
│   └── LeaveBalanceTest.php         # Leave balance calculations (13 tests)
├── Unit/
│   ├── LeaveRequestModelTest.php    # LeaveRequest model methods (9 tests)
│   └── UserModelTest.php            # User model methods (11 tests)
└── TestCase.php
```

## Total Test Coverage

- **Total Tests:** 72
- **Feature Tests:** 52
- **Unit Tests:** 20

### Test Categories

#### Authentication Tests (13)
- User registration (valid/invalid scenarios)
- Login (success/failure cases)
- Logout functionality
- User profile retrieval
- Email uniqueness
- Password validation

#### Leave Request Tests (26)
- Submit leave (full_day, half_day, multi_day)
- Auto-approval for admin users
- Validation errors (reason, dates, periods)
- List requests (role-based filtering)
- View specific request (authorization)
- Approve/reject workflow
- Role-based approval hierarchy
- Error handling for processed requests

#### Leave Balance Tests (13)
- Own balance calculation
- View other users' balance (role-based)
- Balance with approved/pending/rejected leaves
- Half-day leave calculations
- Mixed leave type calculations
- Authorization checks

#### Model Unit Tests (20)
- Relationship methods
- Status helper methods (isPending, isApproved, isRejected)
- Role helper methods (isAdmin, isHR, isGeneral)
- Day calculation methods
- Date casting
- Data validation

## Understanding Test Results

### Successful Test Run
```
PASS  Tests\Feature\AuthenticationTest
✓ user can register with valid data
✓ user can login with valid credentials
...

Tests:  72 passed
Time:   12.34s
```

### Failed Test
```
FAILED  Tests\Feature\LeaveRequestTest > test_hr_can_approve_general_user_leave_request
Expected status code 200 but received 403.
Failed asserting that 403 is identical to 200.
```

## Test Database Configuration

Tests use a separate database configured in `phpunit.xml`:

```xml
<env name="DB_CONNECTION" value="mysql"/>
<env name="DB_DATABASE" value="leave_management_test"/>
```

The `RefreshDatabase` trait is used in all test classes, which:
- Migrates the database before each test
- Rolls back changes after each test
- Ensures test isolation

## Common Issues

### Issue: "Database not found"
**Solution:** Create the test database:
```sql
CREATE DATABASE leave_management_test;
```

### Issue: "could not find driver" (SQLite)
**Solution:** Tests are configured for MySQL. Ensure MySQL is running and accessible.

### Issue: "Access denied for user"
**Solution:** Check your `.env` file database credentials match your MySQL configuration.

### Issue: "Base table or view not found"
**Solution:** Clear config cache:
```bash
php artisan config:clear
php artisan cache:clear
```

## Writing New Tests

### Feature Test Template

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class YourFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_example(): void
    {
        $response = $this->postJson('/api/your-endpoint', [
            'field' => 'value'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ]);
    }
}
```

### Unit Test Template

```php
<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class YourUnitTest extends TestCase
{
    use RefreshDatabase;

    public function test_example(): void
    {
        // Arrange
        $model = Model::factory()->create();

        // Act
        $result = $model->someMethod();

        // Assert
        $this->assertTrue($result);
    }
}
```

## Test Data Factories

Factories are available for generating test data:

```php
// Create a user
$user = User::factory()->create();

// Create user with specific role
$admin = User::factory()->create(['role' => 'admin']);

// Create leave request
$leave = LeaveRequest::factory()->create();

// Create approved leave
$approvedLeave = LeaveRequest::factory()->approved()->create();

// Create half-day leave
$halfDayLeave = LeaveRequest::factory()->halfDay()->create();
```

## Continuous Integration

For CI/CD pipelines, use:

```bash
php artisan test --parallel --coverage --min=80
```

This runs tests in parallel and ensures minimum 80% code coverage.

## Best Practices

1. **Test Isolation:** Each test should be independent
2. **Descriptive Names:** Use clear test method names
3. **Arrange-Act-Assert:** Follow AAA pattern
4. **Factory Usage:** Use factories for test data
5. **Assertions:** Use specific assertions (assertJson, assertStatus)
6. **Clean Up:** RefreshDatabase trait handles cleanup automatically

## Need Help?

- Check Laravel Testing Docs: https://laravel.com/docs/testing
- Review existing tests for patterns
- Ensure database is properly configured

---

**Happy Testing! 🚀**
