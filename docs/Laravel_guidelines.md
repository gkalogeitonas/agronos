## Laravel Resource Classes and Best Practices

### Core Resource Classes

For each primary resource in Agronos (Farms, Devices, Sensors, etc.), the following classes should be implemented for proper separation of concerns:

#### 1. Models
- `app/Models/Farm.php`, `app/Models/Device.php`, etc.
- Define relationships, accessors/mutators, scopes, and validation rules
- Implement trait-based behavior extensions (e.g., `HasUuid`, `HasFactory`)

#### 2. Controllers
- `app/Http/Controllers/FarmController.php`, etc.
- Keep controllers thin by delegating to services where appropriate
- Use resource controllers with standard CRUD actions
- For web routes: `index()`, `create()`, `store()`, `show()`, `edit()`, `update()`, `destroy()`
- For API routes: `index()`, `store()`, `show()`, `update()`, `destroy()`

#### 3. API Resources/Transformers
- `app/Http/Resources/FarmResource.php`, etc.
- Handle data transformation for API responses
- Support versioned responses as needed
- Consider collections for pagination and metadata

#### 4. Form Requests
- `app/Http/Requests/StoreFarmRequest.php`, `app/Http/Requests/UpdateFarmRequest.php`, etc.
- Centralize validation rules and authorization logic
- Keep validation out of controllers
- Can include custom error messages and validation attributes

#### 5. Policies
- `app/Policies/FarmPolicy.php`, etc.
- Implement granular authorization rules
- Methods: `viewAny()`, `view()`, `create()`, `update()`, `delete()`, `restore()`, `forceDelete()`
- Register in `AuthServiceProvider.php`

#### 6. Factories
- `database/factories/FarmFactory.php`, etc.
- Use for test data generation and seeding
- Implement realistic data with faker and states

#### 7. Seeders
- `database/seeders/FarmSeeder.php`, etc.
- Create initial data and test data
- Consider environment-specific seeding strategies
- Create realistic relationships across tables

#### 8. Migrations
- `database/migrations/2025_06_19_create_farms_table.php`, etc.
- Well-documented column definitions
- Use appropriate column types and constraints
- Include foreign key relationships with cascades where appropriate

### Additional Important Classes

For more comprehensive applications like Agronos, consider these additional patterns:

#### 9. Services
- `app/Services/FarmService.php`, etc.
- Implement business logic separate from controllers
- Handle complex operations that span multiple models
- Can be injected into controllers or used in jobs

#### 10. Actions
- `app/Actions/Farms/CreateFarm.php`, `app/Actions/Farms/UpdateFarm.php`, etc.
- Single-purpose classes for specific business operations
- Provides more granularity than service classes
- Used by Laravel Fortify/Jetstream



#### 11. Jobs
- `app/Jobs/ProcessSensorData.php`, etc.
- Handle queued or long-running tasks
- Follow Single Responsibility Principle
- Set timeouts, retries, queue priority

#### 12. Events & Listeners
- `app/Events/FarmCreated.php`, `app/Listeners/NotifyFarmCreation.php`, etc.
- Implement event-driven architecture for loose coupling
- Register in `EventServiceProvider.php`
- Consider queued listeners for async operations

#### 13. Notifications
- `app/Notifications/SensorAlertNotification.php`, etc.
- Handle multi-channel notifications (email, SMS, database)
- Implement notification preferences per user

#### 14. Observers
- `app/Observers/FarmObserver.php`, etc.
- Watch for model events: created, updated, deleted, etc.
- Register in `EventServiceProvider.php` or a model provider
- Use instead of model events for cleaner code

#### 15. Middleware
- `app/Http/Middleware/EnsureUserOwnsFarm.php`, etc.
- Filter HTTP requests before they hit controllers
- Use for cross-cutting concerns like authentication, logging

#### 16. Tests
- Feature tests: `tests/Feature/FarmManagementTest.php`, etc.
- Unit tests: `tests/Unit/Services/MqttServiceTest.php`, etc.
- Write tests using Pest's expressive syntax
- Utilize factories to create test data

### Testing Best Practices

The Agronos project uses Pest PHP for testing, with two effective approaches for organizing tests:

#### 1. Resource-Based Test Organization

For most features, we follow a consolidated approach with a single test file handling all CRUD operations for a resource:

```
tests/
  ├─ Feature/
  │   ├─ AuthManagementTest.php    // All auth-related tests
  │   ├─ FarmManagementTest.php    // All CRUD for farms
  │   ├─ SensorManagementTest.php  // All CRUD for sensors
  │   └─ DeviceManagementTest.php  // All CRUD for devices
  └─ Unit/
      ├─ Models/
      │   └─ FarmTest.php         // Unit tests for Farm model
      └─ Services/
          └─ MqttServiceTest.php  // Unit tests for MQTT services
```

With this approach:
- Group related CRUD functionality in a single test file
- Use Pest's `describe()` blocks to organize tests within the file:

## Resource Ownership Test Template

### Example: Farm Resource Management

#### Index (Viewing Farms List)
- User can see their own farms in the farms list
- User cannot see farms belonging to other users
- Farms list shows correct information for each farm

#### Show (Viewing a Single Farm)
- User can view details of their own farm
- User cannot view a farm they don't own (receives 403 Forbidden)
- Farm details page shows all required information

#### Create (Adding New Farms)
- User can create a new farm
- Newly created farm is associated with the authenticated user
- Farm validation rules are enforced
- Farm appears in the user's farm list after creation

#### Update (Modifying Farms)
- User can update details of their own farm
- User cannot update a farm they don't own (receives 403 Forbidden)
- Farm details are correctly updated in the database
- Validation rules prevent invalid updates

#### Delete (Removing Farms)
- User can delete their own farm
- User cannot delete a farm they don't own (receives 403 Forbidden)
- Farm is properly removed from database after deletion
- Associated resources (sensors, measurements) are handled appropriately


## Multi-Tenancy Implementation

The application implements multi-tenancy at the database query level using Laravel Global Scopes. This ensures data isolation between users (tenants) and prevents any data leakage.

### TenantScope

The core component is the `TenantScope` class which automatically filters database queries:

```php
namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        if (Auth::check()) {
            $builder->where('user_id', Auth::id());
        }
    }
    
    public function extend(Builder $builder)
    {
        // Method to bypass tenant scope when needed (e.g., for admin functions)
        $builder->macro('withoutTenant', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });
    }
}
```

### BelongsToTenant Trait

For easy application across models, we use the BelongsToTenant trait:

```php
namespace App\Traits;

use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant()
    {
        // Apply the tenant scope to all queries
        static::addGlobalScope(new TenantScope);
        
        // Automatically assign the current user_id when creating models
        static::creating(function (Model $model) {
            if (!$model->user_id && Auth::check()) {
                $model->user_id = Auth::id();
            }
        });
    }
    
    // Define the user relationship
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
```

### Usage in Models

To make a model tenant-aware, simply use the trait:

```php
namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Farm extends Model
{
    use BelongsToTenant;
    
    // Rest of the model...
}
```

### Bypassing Tenant Scoping

For admin functionality or special cases:

```php
// Get all farms across all users
$allFarms = Farm::withoutTenant()->get();
```

### Benefits

- **Automatic data isolation**: Users can only see their own data
- **Security by default**: Prevents accidental data leakage
- **Simplified controllers**: No need to add user filtering in every query
- **Consistent approach**: Works the same way across all models
