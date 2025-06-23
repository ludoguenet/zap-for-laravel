<div align="center">

<img src="art/logo.png" alt="Zap Logo" width="200">

# ⚡ Laravel Zap

**Lightning-fast schedule management for Laravel**

[![PHP Version Require](http://poser.pugx.org/laraveljutsu/zap/require/php)](https://packagist.org/packages/laraveljutsu/zap)
[![Laravel Version](https://img.shields.io/badge/Laravel-11.0+-FF2D20?style=flat&logo=laravel)](https://laravel.com)
[![License](http://poser.pugx.org/laraveljutsu/zap/license)](https://packagist.org/packages/laraveljutsu/zap)

*A flexible, performant, and developer-friendly schedule management system with deep Laravel integration.*

[Installation](#-installation) • [Quick Start](#-quick-start) • [Features](#-features) • [Documentation](#-advanced-usage) • [Contributing](#-contributing)

</div>

---

## ✨ Features

- **🏗️ Eloquent Integration** - User HasMany Schedules with period-based scheduling
- **⚡ Business Rules Engine** - Configurable validation with Laravel integration
- **⏰ Temporal Operations** - Carbon-based date/time manipulation with timezone support
- **🔍 Smart Conflict Detection** - Automatic overlap checking with customizable buffers
- **🔄 Recurring Schedules** - Support for daily, weekly, monthly, and custom patterns
- **📊 Availability Management** - Intelligent time slot generation and conflict resolution
- **🧩 Laravel Native** - Facades, service providers, events, and configuration
- **👩‍💻 Developer Experience** - Fluent API, comprehensive testing, and clear documentation

---

## 📋 Requirements

- **PHP** 8.2+
- **Laravel** 11.0+
- **Carbon** 2.0+ or 3.0+

---

## 📦 Installation

### Install Package

```bash
composer require laraveljutsu/zap
```

### Setup

```bash
# Publish and run migrations
php artisan vendor:publish --tag=zap-migrations
php artisan migrate

# Publish configuration (optional)
php artisan vendor:publish --tag=zap-config
```

### Add Trait to Models

```php
use Zap\Models\Concerns\HasSchedules;

class User extends Authenticatable
{
    use HasSchedules;
    // ...
}
```

---

## 🚀 Quick Start

### Basic Schedule

```php
use Zap\Facades\Zap;

$user = User::find(1);

$schedule = Zap::for($user)
    ->named('Doctor Appointment')
    ->description('Annual checkup')
    ->from('2025-03-15')
    ->addPeriod('09:00', '10:00')
    ->save();
```

### Recurring Schedule

```php
// Weekly team meeting
$meeting = Zap::for($user)
    ->named('Team Standup')
    ->from('2025-01-01')
    ->to('2025-12-31')
    ->addPeriod('09:00', '09:30')
    ->weekly(['monday', 'wednesday', 'friday'])
    ->save();
```

### Schedule with Rules

```php
$schedule = Zap::for($user)
    ->named('Client Meeting')
    ->from('2025-03-15')
    ->addPeriod('14:00', '16:00')
    ->noOverlap()                    // Prevent conflicts
    ->workingHoursOnly('09:00', '18:00')  // Business hours only
    ->maxDuration(240)               // Max 4 hours
    ->withMetadata([
        'location' => 'Conference Room A',
        'priority' => 'high'
    ])
    ->save();
```

---

## 🔧 Advanced Usage

### Availability Checking

```php
// Check availability
$available = $user->isAvailableAt('2025-03-15', '14:00', '16:00');

// Get available slots
$slots = $user->getAvailableSlots(
    date: '2025-03-15',
    dayStart: '09:00',
    dayEnd: '17:00',
    slotDuration: 60
);

// Find next available slot
$nextSlot = $user->getNextAvailableSlot(
    afterDate: '2025-03-15',
    duration: 120,
    dayStart: '09:00',
    dayEnd: '17:00'
);
```

### Conflict Management

```php
// Check for conflicts
$conflicts = Zap::findConflicts($schedule);

// Automatic conflict prevention
try {
    $schedule = Zap::for($user)
        ->from('2025-03-15')
        ->addPeriod('14:00', '16:00')
        ->noOverlap()
        ->save();
} catch (ScheduleConflictException $e) {
    $conflicts = $e->getConflictingSchedules();
}
```

### Schedule Queries

```php
// Get schedules for date
$todaySchedules = $user->schedulesForDate(today());

// Get schedules for range
$weekSchedules = $user->schedulesForDateRange('2025-03-11', '2025-03-17');

// Advanced queries
$schedules = Schedule::active()
    ->forDate('2025-03-15')
    ->whereHas('periods', function ($query) {
        $query->whereBetween('start_time', ['09:00', '17:00']);
    })
    ->get();
```

---

## ⚙️ Configuration

Configure Zap in `config/zap.php`:

```php
return [
    'default_rules' => [
        'no_overlap' => true,
        'working_hours' => [
            'enabled' => false,
            'start' => '09:00',
            'end' => '17:00',
        ],
        'max_duration' => [
            'enabled' => false,
            'minutes' => 480,
        ],
    ],

    'conflict_detection' => [
        'enabled' => true,
        'buffer_minutes' => 0,
        'strict_mode' => true,
    ],

    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
        'prefix' => 'zap_schedule_',
    ],
];
```

---

## 🎯 Use Cases

<details>
<summary><strong>📅 Appointment Booking System</strong></summary>

```php
// Doctor availability
$availability = Zap::for($doctor)
    ->named('Available Hours')
    ->from('2025-03-01')->to('2025-03-31')
    ->addPeriod('09:00', '12:00')
    ->addPeriod('14:00', '17:00')
    ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
    ->save();

// Book appointment
$appointment = Zap::for($doctor)
    ->named('Patient Consultation')
    ->from('2025-03-15')
    ->addPeriod('10:00', '10:30')
    ->noOverlap()
    ->save();
```
</details>

<details>
<summary><strong>🏢 Meeting Room Management</strong></summary>

```php
// Room maintenance
$maintenance = Zap::for($room)
    ->named('Monthly Maintenance')
    ->from('2025-03-01')
    ->addPeriod('18:00', '20:00')
    ->monthly(['day_of_month' => 1])
    ->save();

// Book meeting room
$meeting = Zap::for($room)
    ->named('Board Meeting')
    ->from('2025-03-15')
    ->addPeriod('09:00', '11:00')
    ->noOverlap()
    ->withMetadata([
        'organizer' => 'john@company.com',
        'equipment' => ['projector', 'whiteboard']
    ])
    ->save();
```
</details>

<details>
<summary><strong>👨‍💼 Employee Shift Management</strong></summary>

```php
// Regular shifts
$workSchedule = Zap::for($employee)
    ->named('Regular Shift')
    ->from('2025-01-01')->to('2025-12-31')
    ->addPeriod('09:00', '17:00')
    ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
    ->noWeekends()
    ->save();

// Overtime
$overtime = Zap::for($employee)
    ->named('Overtime - Project Deadline')
    ->from('2025-03-15')
    ->addPeriod('18:00', '22:00')
    ->maxDuration(240)
    ->save();
```
</details>

---

## 📡 Events & Testing

### Events

```php
// Listen to schedule events
protected $listen = [
    \Zap\Events\ScheduleCreated::class => [
        \App\Listeners\SendScheduleNotification::class,
    ],
];
```

### Testing Helpers

```php
// Create test schedules easily
$schedule = createScheduleFor($user, [
    'name' => 'Test Meeting',
    'start_date' => '2025-01-01',
    'periods' => [['start_time' => '09:00', 'end_time' => '10:00']],
]);
```

---

## 🛠️ Performance & Optimization

### Database Optimization

```php
// Custom indexes for better performance
Schema::table('schedules', function (Blueprint $table) {
    $table->index(['schedulable_type', 'start_date', 'is_active']);
});
```

### Caching & Eager Loading

```php
// Optimize queries
$schedules = Schedule::with(['periods', 'schedulable'])
    ->forDateRange('2025-03-01', '2025-03-31')
    ->get();

// Cache control
Cache::tags(['zap', 'schedules'])->flush();
```

---

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guidelines](CONTRIBUTING.md) for details.

### Development Setup

```bash
git clone https://github.com/laraveljutsu/zap.git
cd zap
composer install
vendor/bin/pest
```

---

## 📜 License

Laravel Zap is open-source software licensed under the [MIT License](LICENSE).

---

## 🔒 Security

If you discover security vulnerabilities, please email **ludo@epekta.com** instead of using the issue tracker.

---

<div align="center">

**⚡ Made with ❤️ by [Laravel Jutsu](https://laraveljutsu.net) for the Laravel community ⚡**

[Website](https://laraveljutsu.net) • [Documentation](https://laraveljutsu.net/blog/laravel-zap) • [Support](mailto:ludo@epekta.com)

</div>
