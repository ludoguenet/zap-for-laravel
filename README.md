<div align="center">

<img src="art/logo.png" alt="Zap Logo" width="200">

**The missing schedule management for Laravel**

[![PHP Version Require](http://poser.pugx.org/laraveljutsu/zap/require/php)](https://packagist.org/packages/laraveljutsu/zap)
[![Laravel Version](https://img.shields.io/badge/Laravel-11.0+-FF2D20?style=flat&logo=laravel)](https://laravel.com)
[![License](http://poser.pugx.org/laraveljutsu/zap/license)](https://packagist.org/packages/laraveljutsu/zap)
[![Total Downloads](http://poser.pugx.org/laraveljutsu/zap/downloads)](https://packagist.org/packages/laraveljutsu/zap)

*A flexible, performant, and developer-friendly schedule management system with deep Laravel integration.*

[Installation](#installation) • [Quick Start](#quick-start) • [Schedule Types](#schedule-types) • [Features](#features) • [Documentation](#advanced-usage) • [Contributing](#contributing)

</div>

---

## 📦 Installation

**Requirements:** PHP 8.2+ • Laravel 11.0+ • Carbon 2.0/3.0+

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

$schedule = Zap::for($user)
    ->named('Doctor Appointment')
    ->description('Annual checkup')
    ->on('2025-03-15') // on() is an alias of from()
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

> [!IMPORTANT]
> The `workingHoursOnly()` and `maxDuration()` methods require enabling `working_hours` and `max_duration` validation rules in your config file. These are disabled by default.

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

## 🎯 Schedule Types

Laravel Zap supports four distinct schedule types for complex scheduling scenarios:

### 1. **Availability** - Working Hours
Define when someone/something is available. **Allows overlaps**.

```php
$availability = Zap::for($doctor)
    ->named('Office Hours')
    ->availability()
    ->from('2025-01-01')->to('2025-12-31')
    ->addPeriod('09:00', '12:00') // Morning session
    ->addPeriod('14:00', '17:00') // Afternoon session
    ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
    ->save();
```

### 2. **Appointment** - Actual Bookings
Concrete appointments within availability windows. **Prevents overlaps**.

```php
$appointment = Zap::for($doctor)
    ->named('Patient A - Checkup')
    ->appointment()
    ->from('2025-01-15')
    ->addPeriod('10:00', '11:00')
    ->withMetadata(['patient_id' => 1, 'type' => 'checkup'])
    ->save();
```

### 3. **Blocked** - Unavailable Time
Time periods that block scheduling (lunch, holidays). **Prevents overlaps**.

```php
$lunchBreak = Zap::for($doctor)
    ->named('Lunch Break')
    ->blocked()
    ->from('2025-01-01')->to('2025-12-31')
    ->addPeriod('12:00', '13:00')
    ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
    ->save();
```

### 4. **Custom** - Flexible Scheduling
Default type with explicit rule control.

```php
$custom = Zap::for($user)
    ->named('Custom Event')
    ->custom()
    ->from('2025-01-15')
    ->addPeriod('15:00', '16:00')
    ->noOverlap() // Explicitly prevent overlaps
    ->save();
```

### Query by Type
```php
// Query schedules by type
$availability = Schedule::availability()->get();
$appointments = Schedule::appointments()->get();
$blocked = Schedule::blocked()->get();

// Using relationship methods
$userAppointments = $user->appointmentSchedules()->get();
$userAvailability = $user->availabilitySchedules()->get();

// Check schedule type
$schedule->isAvailability(); // true/false
$schedule->isAppointment();  // true/false
$schedule->isBlocked();      // true/false
```

---

## ✨ Features

- **🏗️ Eloquent Integration** - Native Laravel models and relationships
- **🎛️ Business Rules Engine** - Configurable validation with granular control
- **⏰ Temporal Operations** - Carbon-based date/time with timezone support
- **🔍 Smart Conflict Detection** - Automatic overlap checking with buffers
- **🔄 Recurring Schedules** - Daily, weekly, monthly, and custom patterns
- **📊 Availability Management** - Intelligent time slot generation
- **🎯 Schedule Types** - Availability, appointment, blocked, and custom
- **🧩 Laravel Native** - Facades, service providers, events, configuration
- **👩‍💻 Developer Experience** - Fluent API, comprehensive testing, documentation

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
    ->availability()
    ->from('2025-03-15')
    ->addPeriod('09:00', '17:00')
    ->save(); // No overlap validation applied

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

## 🎯 Real-World Examples

<details>
<summary><strong>🏥 Hospital Scheduling System</strong></summary>

```php
// Doctor's working hours (availability)
$availability = Zap::for($doctor)
    ->named('Dr. Smith - Office Hours')
    ->availability()
    ->from('2025-01-01')->to('2025-12-31')
    ->addPeriod('09:00', '12:00')
    ->addPeriod('14:00', '17:00')
    ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
    ->save();

// Lunch break (blocked)
$lunchBreak = Zap::for($doctor)
    ->named('Lunch Break')
    ->blocked()
    ->from('2025-01-01')->to('2025-12-31')
    ->addPeriod('12:00', '13:00')
    ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
    ->save();

// Patient appointments
$appointment = Zap::for($doctor)
    ->named('Patient A - Consultation')
    ->appointment()
    ->from('2025-01-15')
    ->addPeriod('10:00', '11:00')
    ->withMetadata(['patient_id' => 1, 'type' => 'consultation'])
    ->save();
```
</details>

<details>
<summary><strong>🏢 Meeting Room Management</strong></summary>

```php
// Room availability
$roomAvailability = Zap::for($room)
    ->named('Conference Room A')
    ->availability()
    ->from('2025-01-01')->to('2025-12-31')
    ->addPeriod('08:00', '18:00')
    ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
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

// Time off (blocked)
$vacation = Zap::for($employee)
    ->named('Vacation Leave')
    ->blocked()
    ->from('2025-06-01')->to('2025-06-15')
    ->addPeriod('00:00', '23:59')
    ->save();
```
</details>

---

## 🤝 Contributing

We welcome contributions! Here's how to get started:

### Development Setup
```bash
git clone https://github.com/laraveljutsu/zap.git
cd zap
composer install
vendor/bin/pest
```

### Guidelines
- Follow PSR-12 coding standards
- Write tests for new features
- Update documentation as needed

---

## 📜 License

Laravel Zap is open-source software licensed under the [MIT License](LICENSE).

## 🔒 Security

If you discover security vulnerabilities, please email **ludo@epekta.com** instead of using the issue tracker.

---

<div align="center">

**⚡ Made with ❤️ by [Laravel Jutsu](https://laraveljutsu.net) for the Laravel community ⚡**

[Website](https://laraveljutsu.net) • [Documentation](https://laraveljutsu.net/blog/laravel-zap) • [Support](mailto:ludo@epekta.com)

</div>
