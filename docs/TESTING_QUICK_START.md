# Testing Quick Start Guide

## Running Tests

### Basic Commands

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature

# Run specific test file
php artisan test tests/Unit/Services/GameServiceTest.php

# Run specific test method
php artisan test --filter test_can_start_a_game_session

# Run with coverage (requires Xdebug or PCOV)
php artisan test --coverage

# Run with minimum coverage threshold
php artisan test --coverage --min=70
```

### Using the Test Runner Script

```bash
# Run comprehensive test suite
./scripts/run-tests.sh

# Or from project root
bash scripts/run-tests.sh
```

## Test Structure

```
tests/
├── Unit/                    # Unit tests (70% of tests)
│   └── Services/           # Service layer tests
│       ├── GameServiceTest.php
│       └── PrizeServiceTest.php
├── Feature/                 # Feature tests (20% of tests)
│   ├── StripeWebhookTest.php
│   └── GamePlayFlowTest.php
└── TestCase.php            # Base test case
```

## Critical Tests to Run Before Launch

### 1. Payment Processing
```bash
php artisan test tests/Feature/StripeWebhookTest.php
```

### 2. Game Play Logic
```bash
php artisan test tests/Unit/Services/GameServiceTest.php
php artisan test tests/Feature/GamePlayFlowTest.php
```

### 3. Reward System
```bash
php artisan test tests/Unit/Services/PrizeServiceTest.php
```

### 4. All Critical Tests
```bash
php artisan test --testsuite=Unit --testsuite=Feature
```

## Writing New Tests

### Unit Test Example
```php
<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MyServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_does_something()
    {
        // Arrange
        $service = new MyService();
        
        // Act
        $result = $service->doSomething();
        
        // Assert
        $this->assertNotNull($result);
    }
}
```

### Feature Test Example
```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MyFeatureTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_do_something()
    {
        $response = $this->get('/some-route');
        
        $response->assertStatus(200);
    }
}
```

## Test Data Factories

All models have factories for easy test data creation:

```php
// Create a single model
$user = User::factory()->create();

// Create with specific attributes
$business = Business::factory()->create([
    'name' => 'Test Business',
]);

// Create multiple
$games = Game::factory()->count(5)->create();

// Use factory states
$promotion = Promotion::factory()->percentage(25)->create();
```

## Common Testing Patterns

### Testing Database Transactions
```php
use Illuminate\Foundation\Testing\RefreshDatabase;

class MyTest extends TestCase
{
    use RefreshDatabase; // Automatically rolls back after each test
}
```

### Testing Authentication
```php
$user = User::factory()->create();
$this->actingAs($user);

$response = $this->get('/protected-route');
```

### Testing API Endpoints
```php
$response = $this->postJson('/api/endpoint', [
    'field' => 'value',
]);

$response->assertStatus(200)
    ->assertJson(['success' => true]);
```

### Mocking External Services
```php
use Mockery;

$mockService = Mockery::mock(ExternalService::class);
$mockService->shouldReceive('method')
    ->once()
    ->andReturn('result');

$this->app->instance(ExternalService::class, $mockService);
```

## Pre-Launch Checklist

- [ ] All unit tests passing
- [ ] All feature tests passing
- [ ] Stripe webhook tests passing
- [ ] Game play flow tests passing
- [ ] Reward calculation tests passing
- [ ] Test coverage > 70% for critical services
- [ ] Manual testing completed on staging
- [ ] Load testing completed
- [ ] Security testing completed

## Troubleshooting

### Tests failing due to database issues
- Ensure `.env.testing` is configured
- Run migrations: `php artisan migrate --env=testing`
- Check database connection in `phpunit.xml`

### Tests failing due to missing factories
- Check if factory exists in `database/factories/`
- Ensure model uses `HasFactory` trait
- Run `composer dump-autoload`

### Slow test execution
- Use `RefreshDatabase` instead of `DatabaseTransactions` for faster tests
- Consider parallel testing: `php artisan test --parallel`

## Resources

- [Laravel Testing Docs](https://laravel.com/docs/testing)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Testing Strategy](./TESTING_STRATEGY.md)

