<div align="center">

<img src="art/logo.png" alt="Zap Logo" width="200">

# ⚡ Laravel Zap

**Lightning-fast schedule management for Laravel**

[![PHP Version Require](http://poser.pugx.org/laraveljutsu/zap/require/php)](https://packagist.org/packages/laraveljutsu/zap)
[![Laravel Version](https://img.shields.io/badge/Laravel-11.0+-FF2D20?style=flat&logo=laravel)](https://laravel.com)
[![License](http://poser.pugx.org/laraveljutsu/zap/license)](https://packagist.org/packages/laraveljutsu/zap)

*A flexible, performant, and developer-friendly schedule management system with deep Laravel integration.*

[Installation](#-installation) • [Quick Start](#-quick-start) • [Features](#-features) • [Schedule Types](#-schedule-types) • [Documentation](#-advanced-usage) • [Contributing](#-contributing)

</div>

---

> ⚠️ **Beta Warning**: This package is currently in beta. While we're working hard to ensure stability, it's not recommended for use in production environments yet.

## ✨ Features

- **🏗️ Eloquent Integration** - User HasMany Schedules with period-based scheduling
- **⚡ Business Rules Engine** - Configurable validation with Laravel integration
- **🎛️ Granular Rule Control** - Individual rule enable/disable with per-schedule overrides
- **⏰ Temporal Operations** - Carbon-based date/time manipulation with timezone support
- **🔍 Smart Conflict Detection** - Automatic overlap checking with customizable buffers
- **🔄 Recurring Schedules** - Support for daily, weekly, monthly, and custom patterns
- **📊 Availability Management** - Intelligent time slot generation and conflict resolution
- **🎯 Schedule Types** - Availability, appointment, blocked, and custom scheduling types
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

### Schedule with Rule Overrides

```php
// Override specific rules for this schedule
$schedule = Zap::for($user)
    ->named('Weekend Emergency')
    ->from('2025-03-16')
    ->addPeriod('08:00', '20:00')
    ->withRule('working_hours', ['enabled' => false])  // Allow outside business hours
    ->withRule('no_weekends', ['enabled' => false])    // Allow weekend scheduling
    ->withRule('max_duration', ['enabled' => false])   // No duration limits
    ->save();
```

---

## 🎯 Schedule Types

Laravel Zap supports four distinct schedule types to handle complex scheduling scenarios:

### 1. **Availability Schedules**
Working hours or open time slots that **allow overlaps**. Perfect for defining when someone is available.

```php
// Define working hours
$availability = Zap::for($doctor)
    ->named('Office Hours')
    ->description('Available for patient appointments')
    ->availability()
    ->from('2025-01-01')
    ->to('2025-12-31')
    ->addPeriod('09:00', '12:00') // Morning session
    ->addPeriod('14:00', '17:00') // Afternoon session
    ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
    ->save();
```

### 2. **Appointment Schedules**
Actual bookings that **prevent overlaps**. These are concrete appointments scheduled within availability windows.

```php
// Create a patient appointment
$appointment = Zap::for($doctor)
    ->named('Patient A - Checkup')
    ->description('Annual checkup appointment')
    ->appointment()
    ->from('2025-01-15')
    ->addPeriod('10:00', '11:00')
    ->withMetadata([
        'patient_id' => 1,
        'appointment_type' => 'checkup',
        'notes' => 'Annual physical examination'
    ])
    ->save();
```

### 3. **Blocked Schedules**
Unavailable time periods that **prevent overlaps**. Used for lunch breaks, holidays, or maintenance.

```php
// Define lunch break
$lunchBreak = Zap::for($doctor)
    ->named('Lunch Break')
    ->description('Unavailable for appointments')
    ->blocked()
    ->from('2025-01-01')
    ->to('2025-12-31')
    ->addPeriod('12:00', '13:00')
    ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
    ->save();
```

### 4. **Custom Schedules**
Default type for backward compatibility. Behavior depends on explicit rules.

```php
// Custom schedule with explicit rules
$custom = Zap::for($user)
    ->named('Custom Event')
    ->custom()
    ->from('2025-01-15')
    ->addPeriod('15:00', '16:00')
    ->noOverlap() // Explicitly prevent overlaps
    ->save();
```

### Schedule Type Methods

```php
// Query schedules by type
$availability = Schedule::availability()->get();
$appointments = Schedule::appointments()->get();
$blocked = Schedule::blocked()->get();

// Using relationship methods
$userAppointments = $user->appointmentSchedules()->get();
$userAvailability = $user->availabilitySchedules()->get();
$userBlocked = $user->blockedSchedules()->get();

// Check schedule type
$schedule->isAvailability(); // true/false
$schedule->isAppointment();  // true/false
$schedule->isBlocked();      // true/false
$schedule->isCustom();       // true/false
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

### Advanced Rule Control

```php
// Disable overlap checking for availability schedules only
config(['zap.default_rules.no_overlap.applies_to' => ['appointment', 'blocked']]);

// Create availability that can overlap
$availability = Zap::for($user)
    ->named('General Availability')
    ->availability()  // Schedule type: availability
    ->from('2025-03-15')
    ->addPeriod('09:00', '17:00')
    ->save(); // No overlap validation applied

// Create appointment that requires validation
$appointment = Zap::for($user)
    ->named('Client Meeting')
    ->appointment()  // Schedule type: appointment
    ->from('2025-03-15')
    ->addPeriod('10:00', '11:00')
    ->save(); // Overlap validation applied

// Emergency override for specific case
$emergency = Zap::for($user)
    ->named('Emergency Surgery')
    ->from('2025-03-15')
    ->addPeriod('10:30', '12:00')
    ->withRule('no_overlap', ['enabled' => false])
    ->save(); // Bypasses overlap validation
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
        'no_overlap' => [
            'enabled' => true,
            'applies_to' => [
                // Granular control
                \Zap\Enums\ScheduleTypes::APPOINTMENT,
                \Zap\Enums\ScheduleTypes::BLOCKED,
            ],
        ],
        'working_hours' => [
            'enabled' => false,
            'start' => '09:00',
            'end' => '17:00',
            'timezone' => null, // Uses app timezone if null
        ],
        'max_duration' => [
            'enabled' => false,
            'minutes' => 480, // 8 hours
        ],
        'no_weekends' => [
            'enabled' => false,
            'saturday' => true,
            'sunday' => true,
        ],
    ],

    'conflict_detection' => [
        'enabled' => true,
        'buffer_minutes' => 0, // Buffer time between schedules
        'auto_resolve' => false, // Automatically resolve conflicts
        'strict_mode' => true, // Throw exceptions on conflicts
    ],

    'validation' => [
        'require_future_dates' => true, // Schedules must be in the future
        'max_date_range' => 365, // Maximum days between start and end date
        'min_period_duration' => 15, // Minimum period duration in minutes
        'max_period_duration' => 480, // Maximum period duration in minutes
        'max_periods_per_schedule' => 50, // Maximum periods per schedule
        'allow_overlapping_periods' => false, // Allow periods to overlap within same schedule
    ],
];
```

### Individual Rule Control

Control each validation rule independently:

```php
// Disable specific rules
config(['zap.default_rules.working_hours.enabled' => false]);
config(['zap.default_rules.no_overlap.enabled' => false]);

// Granular overlap control - only check overlaps for specific schedule types
config(['zap.default_rules.no_overlap.applies_to' => [\Zap\Enums\ScheduleTypes::APPOINTMENT]]);

// Allow weekend scheduling
config(['zap.default_rules.no_weekends.enabled' => false]);
```

### Per-Schedule Rule Override

Override rules for specific schedules:

```php
// Emergency appointment that can overlap
$schedule = Zap::for($user)
    ->named('Emergency Consultation')
    ->from('2025-03-15')
    ->addPeriod('10:00', '11:00')
    ->withRule('no_overlap', ['enabled' => false])
    ->save();

// Weekend work with extended hours
$schedule = Zap::for($user)
    ->named('Weekend Project')
    ->from('2025-03-16') // Saturday
    ->addPeriod('08:00', '20:00')
    ->withRule('working_hours', ['enabled' => false])
    ->withRule('no_weekends', ['enabled' => false])
    ->save();
```

---

## 🎯 Use Cases

<details>
<summary><strong>📅 Hospital Scheduling System</strong></summary>

```php
// Doctor's working hours (availability)
$availability = Zap::for($doctor)
    ->named('Dr. Smith - Office Hours')
    ->availability()
    ->from('2025-01-01')
    ->to('2025-12-31')
    ->addPeriod('09:00', '12:00')
    ->addPeriod('14:00', '17:00')
    ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
    ->save();

// Lunch break (blocked)
$lunchBreak = Zap::for($doctor)
    ->named('Lunch Break')
    ->blocked()
    ->from('2025-01-01')
    ->to('2025-12-31')
    ->addPeriod('12:00', '13:00')
    ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
    ->save();

// Patient appointments
$appointment1 = Zap::for($doctor)
    ->named('Patient A - Consultation')
    ->appointment()
    ->from('2025-01-15')
    ->addPeriod('10:00', '11:00')
    ->withMetadata(['patient_id' => 1, 'type' => 'consultation'])
    ->save();

$appointment2 = Zap::for($doctor)
    ->named('Patient B - Follow-up')
    ->appointment()
    ->from('2025-01-15')
    ->addPeriod('15:00', '16:00')
    ->withMetadata(['patient_id' => 2, 'type' => 'follow-up'])
    ->save();
```
</details>

<details>
<summary><strong>🏢 Meeting Room Management</strong></summary>

```php
// Room availability
$roomAvailability = Zap::for($room)
    ->named('Conference Room A - Available')
    ->availability()
    ->from('2025-01-01')
    ->to('2025-12-31')
    ->addPeriod('08:00', '18:00')
    ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
    ->save();

// Room maintenance (blocked)
$maintenance = Zap::for($room)
    ->named('Monthly Maintenance')
    ->blocked()
    ->from('2025-01-01')
    ->addPeriod('18:00', '20:00')
    ->monthly(['day_of_month' => 1])
    ->save();

// Meeting bookings
$meeting = Zap::for($room)
    ->named('Board Meeting')
    ->appointment()
    ->from('2025-03-15')
    ->addPeriod('09:00', '11:00')
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
// Regular shifts (availability)
$workSchedule = Zap::for($employee)
    ->named('Regular Shift')
    ->availability()
    ->from('2025-01-01')->to('2025-12-31')
    ->addPeriod('09:00', '17:00')
    ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
    ->save();

// Actual work assignments (appointments)
$assignment = Zap::for($employee)
    ->named('Project Alpha')
    ->appointment()
    ->from('2025-03-15')
    ->addPeriod('09:00', '12:00')
    ->withMetadata(['project_id' => 'alpha', 'priority' => 'high'])
    ->save();

// Time off (blocked)
$vacation = Zap::for($employee)
    ->named('Vacation Leave')
    ->blocked()
    ->from('2025-06-01')
    ->to('2025-06-15')
    ->addPeriod('00:00', '23:59')
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

### Eager Loading

```php
// Optimize queries
$schedules = Schedule::with(['periods', 'schedulable'])
    ->forDateRange('2025-03-01', '2025-03-31')
    ->get();
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
