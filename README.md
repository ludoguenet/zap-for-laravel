# ⚡ Laravel Zap

```
 ███████╗ █████╗ ██████╗
 ╚══███╔╝██╔══██╗██╔══██╗
   ███╔╝ ███████║██████╔╝
  ███╔╝  ██╔══██║██╔═══╝
 ███████╗██║  ██║██║
 ╚══════╝╚═╝  ╚═╝╚═╝
```

**Lightning-fast schedule management for Laravel**

A flexible, performant, and developer-friendly schedule management system with deep Laravel integration.

## ✨ Features

- **🏗️ Eloquent-based Schedule System**: User HasMany Schedules with period-based scheduling
- **⚡ Business Rules Engine**: Configurable validation with Laravel validation integration
- **⏰ Temporal Operations**: Carbon-based date/time manipulation
- **🔍 Conflict Detection**: Automatic overlap checking with Laravel events
- **🧩 Laravel Integration**: Facades, service providers, configuration
- **👩‍💻 Developer Experience**: Fluent API, comprehensive testing
- **🔄 Recurring Schedules**: Support for daily, weekly, monthly patterns
- **📊 Availability Checking**: Smart time slot generation and conflict resolution

## 📋 Requirements

- PHP 8.2+
- Laravel 11.0+
- Carbon 2.0+ or 3.0+

## 📦 Installation

### 1. Install via Composer

```bash
composer require laraveljutsu/zap
```

### 2. Publish Migrations and Configuration

```bash
# Publish migrations
php artisan vendor:publish --tag=zap-migrations

# Publish configuration
php artisan vendor:publish --tag=zap-config
```

### 3. Run Migrations

```bash
php artisan migrate
```

### 4. Add the HasSchedules Trait

Add the `HasSchedules` trait to your User model (or any other schedulable model):

```php
<?php

namespace App\Models;

use Zap\Models\Concerns\HasSchedules;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasSchedules;

    // ... rest of your model
}
```

## 🎯 Quick Start

### Basic Schedule Creation

```php
use Zap\Facades\Zap;
use App\Models\User;

$user = User::find(1);

// Create a simple one-time schedule
$schedule = Zap::for($user)
    ->named('Doctor Appointment')
    ->description('Annual checkup')
    ->from('2025-03-15')
    ->addPeriod('09:00', '10:00')
    ->save();
```

### Recurring Schedules

```php
// Weekly recurring meeting
$schedule = Zap::for($user)
    ->named('Team Standup')
    ->from('2025-01-01')
    ->to('2025-12-31')
    ->addPeriod('09:00', '09:30')
    ->weekly(['monday', 'wednesday', 'friday'])
    ->save();

// Daily work schedule
$workSchedule = Zap::for($user)
    ->named('Work Hours')
    ->from('2025-01-01')
    ->addPeriod('09:00', '12:00') // Morning session
    ->addPeriod('13:00', '17:00') // Afternoon session
    ->daily()
    ->noWeekends()
    ->save();
```

### Schedule with Validation Rules

```php
$schedule = Zap::for($user)
    ->named('Client Meeting')
    ->from('2025-03-15')
    ->addPeriod('14:00', '16:00')
    ->noOverlap()                    // Prevent overlapping schedules
    ->workingHoursOnly('09:00', '18:00')  // Only during business hours
    ->maxDuration(240)               // Max 4 hours
    ->withMetadata([
        'location' => 'Conference Room A',
        'attendees' => ['client@example.com'],
        'priority' => 'high'
    ])
    ->save();
```

## 🔧 Advanced Usage

### Availability Checking

```php
// Check if user is available at specific time
$isAvailable = $user->isAvailableAt('2025-03-15', '14:00', '16:00');

// Get available time slots for a day
$slots = $user->getAvailableSlots(
    date: '2025-03-15',
    dayStart: '09:00',
    dayEnd: '17:00',
    slotDuration: 60 // 1-hour slots
);

// Find next available slot
$nextSlot = $user->getNextAvailableSlot(
    afterDate: '2025-03-15',
    duration: 120, // 2 hours
    dayStart: '09:00',
    dayEnd: '17:00'
);
```

### Conflict Detection

```php
// Check for conflicts before creating
$conflicts = Zap::findConflicts($schedule);

if (!empty($conflicts)) {
    // Handle conflicts
    foreach ($conflicts as $conflictingSchedule) {
        echo "Conflict with: " . $conflictingSchedule->name;
    }
}

// Automatic conflict detection (throws exception)
try {
    $schedule = Zap::for($user)
        ->from('2025-03-15')
        ->addPeriod('14:00', '16:00')
        ->noOverlap()
        ->save();
} catch (ScheduleConflictException $e) {
    $conflicts = $e->getConflictingSchedules();
    // Handle conflict...
}
```

### Querying Schedules

```php
// Get schedules for a specific date
$todaySchedules = $user->schedulesForDate(today());

// Get schedules for date range
$weekSchedules = $user->schedulesForDateRange('2025-03-11', '2025-03-17');

// Get only active schedules
$activeSchedules = $user->activeSchedules;

// Get recurring schedules
$recurringSchedules = $user->recurringSchedules;

// Advanced queries using scopes
$schedules = Schedule::active()
    ->forDate('2025-03-15')
    ->whereHas('periods', function ($query) {
        $query->where('start_time', '>=', '09:00')
              ->where('end_time', '<=', '17:00');
    })
    ->get();
```

## ✅ Laravel Validation

Zap provides built-in validation through the `ValidationService`:

```php
// Validation is handled automatically when creating schedules
$schedule = Zap::for($user)
    ->named('Meeting')
    ->from('2025-03-15')
    ->addPeriod('09:00', '10:00')
    ->noOverlap()                    // Built-in rule validation
    ->workingHoursOnly('09:00', '18:00')  // Built-in rule validation
    ->maxDuration(240)               // Built-in rule validation
    ->save();
```

## ⚙️ Configuration

The configuration file `config/zap.php` provides extensive customization options:

```php
return [
    // Default validation rules
    'default_rules' => [
        'no_overlap' => true,
        'working_hours' => [
            'enabled' => false,
            'start' => '09:00',
            'end' => '17:00',
        ],
        'max_duration' => [
            'enabled' => false,
            'minutes' => 480, // 8 hours
        ],
    ],

    // Conflict detection settings
    'conflict_detection' => [
        'enabled' => true,
        'buffer_minutes' => 0,
        'strict_mode' => true,
    ],

    // Recurring schedule settings
    'recurring' => [
        'process_days_ahead' => 30,
        'cleanup_expired_after_days' => 90,
    ],

    // Cache configuration
    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
        'prefix' => 'zap_schedule_',
    ],


];
```

## 🔧 Recurring Schedules

Recurring schedules are processed automatically by the system. You can configure the behavior in the `config/zap.php` file:

```php
'recurring' => [
    'process_days_ahead' => 30, // Generate instances this many days ahead
    'cleanup_expired_after_days' => 90, // Clean up expired schedules after X days
    'max_instances' => 1000, // Maximum instances to generate at once
],
```

## 📡 Events

Zap fires events that you can listen to:

```php
// In your EventServiceProvider
protected $listen = [
    \Zap\Events\ScheduleCreated::class => [
        \App\Listeners\SendScheduleNotification::class,
    ],
];
```

## 🧪 Testing

Zap includes test helpers for easy testing:

```php
// Use the provided test helpers in your tests
function createUser()
{
    return new class extends \Illuminate\Database\Eloquent\Model
    {
        use \Zap\Models\Concerns\HasSchedules;
        protected $table = 'users';
        public function getKey() { return 1; }
    };
}

function createScheduleFor($schedulable, array $attributes = [])
{
    $attributes = array_merge([
        'name' => 'Test Schedule',
        'start_date' => '2025-01-01',
        'periods' => [['start_time' => '09:00', 'end_time' => '10:00']],
    ], $attributes);

    $builder = \Zap\Facades\Zap::for($schedulable)
        ->named($attributes['name'])
        ->from($attributes['start_date']);

    foreach ($attributes['periods'] as $period) {
        $builder->addPeriod($period['start_time'], $period['end_time']);
    }

    return $builder->save();
}
```

## 🎯 Use Cases

### 📅 Appointment Booking System

```php
// Doctor's availability
$doctor = User::find(1);

$availability = Zap::for($doctor)
    ->named('Available Hours')
    ->from('2025-03-01')
    ->to('2025-03-31')
    ->addPeriod('09:00', '12:00')
    ->addPeriod('14:00', '17:00')
    ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
    ->save();

// Book an appointment
$appointment = Zap::for($doctor)
    ->named('Patient Consultation')
    ->from('2025-03-15')
    ->addPeriod('10:00', '10:30')
    ->noOverlap()
    ->save();
```

### 🏢 Meeting Room Management

```php
$room = Room::find(1); // Assuming Room uses HasSchedules trait

// Block room for maintenance
$maintenance = Zap::for($room)
    ->named('Monthly Maintenance')
    ->from('2025-03-01')
    ->addPeriod('18:00', '20:00')
    ->monthly(['day_of_month' => 1])
    ->save();

// Book room for meeting
$meeting = Zap::for($room)
    ->named('Board Meeting')
    ->description('Q1 Results Review')
    ->from('2025-03-15')
    ->addPeriod('09:00', '11:00')
    ->noOverlap()
    ->withMetadata([
        'organizer' => 'john@company.com',
        'capacity_needed' => 12,
        'equipment' => ['projector', 'whiteboard']
    ])
    ->save();
```

### 👨‍💼 Employee Shift Management

```php
$employee = User::find(1);

// Regular work schedule
$workSchedule = Zap::for($employee)
    ->named('Regular Shift')
    ->from('2025-01-01')
    ->to('2025-12-31')
    ->addPeriod('09:00', '17:00')
    ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
    ->noWeekends()
    ->save();

// Overtime shift
$overtime = Zap::for($employee)
    ->named('Overtime - Project Deadline')
    ->from('2025-03-15')
    ->addPeriod('18:00', '22:00')
    ->maxDuration(240) // 4 hours max
    ->save();
```

## 🛠️ Advanced Customization

### Custom Query Scopes

```php
// Add custom scopes to your Schedule model
class Schedule extends Model
{
    public function scopeForDepartment($query, string $department)
    {
        return $query->whereHas('schedulable', function ($query) use ($department) {
            $query->where('department', $department);
        });
    }
}

// Usage
$schedules = Schedule::forDepartment('Engineering')->get();
```

## 🔧 Performance Optimization

### Database Indexes

Zap automatically creates optimized indexes, but you can add custom ones:

```php
// Custom migration
Schema::table('schedules', function (Blueprint $table) {
    $table->index(['schedulable_type', 'start_date', 'is_active']);
});
```

### Caching

```php
// Enable query caching
'cache' => [
    'enabled' => true,
    'ttl' => 3600,
    'tags' => ['zap', 'schedules'],
],

// Manual cache control
Cache::tags(['zap', 'schedules'])->flush();
```

### Eager Loading

```php
// Optimize queries with eager loading
$schedules = Schedule::with(['periods', 'schedulable'])
    ->forDateRange('2025-03-01', '2025-03-31')
    ->get();
```

## 🤝 Contributing

We welcome contributions! Feel free to submit issues and pull requests on GitHub.

## 🔒 Security

If you discover any security-related issues, please email contact@laraveljutsu.net instead of using the issue tracker.

## 📜 License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## 🎉 Credits

- **[Laravel Jutsu](https://laraveljutsu.net)** - Package development and maintenance
- **Laravel Community** - Inspiration and best practices
- **All Contributors** - Thank you for making this package better!

---

<p align="center">
    <strong>⚡ Made with ❤️ by Laravel Jutsu for the Laravel community ⚡</strong>
</p>
