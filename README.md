<div align="center">

<img src="art/logo.png?v=2" alt="Zap Logo" width="200">

**Flexible schedule management for modern Laravel applications**

[![PHP Version](https://img.shields.io/badge/PHP-%E2%89%A48.5-777BB4?style=for-the-badge&logo=php)](https://www.php.net/)
[![Laravel Version](https://img.shields.io/badge/Laravel-%E2%89%A413.0-FF2D20?style=for-the-badge&logo=laravel)](https://laravel.com)
[![License](http://poser.pugx.org/laraveljutsu/zap/license?style=for-the-badge)](https://packagist.org/packages/laraveljutsu/zap)
[![Total Downloads](http://poser.pugx.org/laraveljutsu/zap/downloads?style=for-the-badge)](https://packagist.org/packages/laraveljutsu/zap)
[![Why PHP](https://img.shields.io/badge/Why_PHP-in_2026-7A86E8?style=for-the-badge&labelColor=18181b)](https://whyphp.dev)

[Website](https://ludovicguenet.dev) • [Documentation](https://laravel-zap.com) • [Support](mailto:ludo@epekta.com)

</div>

---

## Table of contents

- [What is Zap?](#-what-is-zap)
- [Installation](#-installation)
- [Core concepts](#-core-concepts)
- [Quick start](#-quick-start)
- [Schedule patterns](#-schedule-patterns)
- [Query & availability](#-query--check-availability)
- [Real-world examples](#-real-world-examples)
- [Configuration](#️-configuration)
- [Advanced](#-advanced-features)
- [AI agent support](#-ai-agent-support)
- [Contributing](#-contributing)

---

## 🎯 What is Zap?

Zap is a calendar and scheduling package for Laravel. Define **availabilities**, **appointments**, **blocked times**, and **custom schedules** for any resource (doctors, rooms, employees, etc.).

**Use cases:** appointment booking, healthcare resources, employee shifts, shared space booking.

---

## 📦 Installation

**Requirements:** PHP ≥8.5 • Laravel ≥13.0

```bash
composer require laraveljutsu/zap
php artisan vendor:publish --provider="Zap\ZapServiceProvider"
```

**UUIDs/ULIDs:** If your app uses non-integer primary keys, read [Custom model support](#custom-model-support) *before* migrating. You may need to change migrations and config.

```bash
php artisan migrate
```

**Make a model schedulable:** add the `HasSchedules` trait.

```php
use Zap\Models\Concerns\HasSchedules;

class Doctor extends Model
{
    use HasSchedules;
}
```

---

## 🧩 Core concepts

| Type            | Purpose                          | Overlaps        |
|-----------------|----------------------------------|-----------------|
| **Availability** | When a resource can be booked    | Allowed         |
| **Appointment**  | Bookings / scheduled events      | Exclusive       |
| **Blocked**      | When booking is forbidden        | Exclusive       |
| **Custom**       | Your rules (overlap, etc.)       | You define      |

---

## 🚀 Quick start

```php
use Zap\Facades\Zap;

// 1. Working hours
Zap::for($doctor)
    ->named('Office Hours')
    ->availability()
    ->forYear(2025)
    ->addPeriod('09:00', '12:00')
    ->addPeriod('14:00', '17:00')
    ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
    ->save();

// 2. Block lunch
Zap::for($doctor)
    ->named('Lunch Break')
    ->blocked()
    ->forYear(2025)
    ->addPeriod('12:00', '13:00')
    ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
    ->save();

// 3. Create an appointment
Zap::for($doctor)
    ->named('Patient A - Consultation')
    ->appointment()
    ->from('2025-01-15')
    ->addPeriod('10:00', '11:00')
    ->withMetadata(['patient_id' => 1, 'type' => 'consultation'])
    ->save();

// 4. Get bookable slots (60 min, 15 min buffer)
$slots = $doctor->getBookableSlots('2025-01-15', 60, 15);

// 5. Next available slot
$nextSlot = $doctor->getNextBookableSlot('2025-01-15', 60, 15);
```

> 💡 Use the `zap()` helper instead of the facade when you prefer: `zap()->for($doctor)->...`

---

## 📅 Schedule patterns

### Recurrence at a glance

| Pattern              | Method / example |
|----------------------|------------------|
| Daily                | `daily()` |
| Weekly (days)        | `weekly(['monday', 'friday'])` |
| Weekly + period      | `weekDays(['monday', 'friday'], '09:00', '17:00')` |
| Odd/even weeks       | `weeklyOdd()`, `weeklyEven()` (+ `weekOddDays` / `weekEvenDays`) |
| Bi-weekly            | `biweekly(['tuesday'], $startsOn?)` |
| Monthly (dates)      | `monthly(['days_of_month' => [1, 15]])` |
| Bi-monthly / quarter / semi / annual | `bimonthly()`, `quarterly()`, `semiannually()`, `annually()` + config |
| **Ordinal weekday**  | `firstWednesdayOfMonth()`, `secondFridayOfMonth()`, `lastMondayOfMonth()` |
| Every N weeks        | `everyThreeWeeks()`, … `everyFiftyTwoWeeks()` |
| Every N months       | `everyFourMonths()`, … `everyElevenMonths()` |
| **RRULE (RFC 5545)** | `rrule('FREQ=WEEKLY;BYDAY=MO,WE,FR')` |

### Recurrence examples

**Daily & weekly**

```php
$schedule->daily()->from('2025-01-01')->to('2025-12-31');
$schedule->weekly(['monday', 'wednesday', 'friday'])->forYear(2025);
$schedule->weekDays(['monday', 'wednesday', 'friday'], '09:00', '17:00')->forYear(2025);
$schedule->weeklyOdd(['monday', 'wednesday', 'friday'])->forYear(2025);
$schedule->weeklyEven(['monday', 'wednesday', 'friday'])->forYear(2025);
$schedule->biweekly(['tuesday', 'thursday'], '2025-01-07')->from('2025-01-07')->to('2025-03-31');
```

**Monthly (by day of month)**

```php
$schedule->monthly(['days_of_month' => [1, 15]])->forYear(2025);
$schedule->bimonthly(['days_of_month' => [5, 20], 'start_month' => 2])->from('2025-01-05')->to('2025-06-30');
$schedule->quarterly(['days_of_month' => [7, 21], 'start_month' => 2])->from('2025-02-15')->to('2025-11-15');
$schedule->semiannually(['days_of_month' => [10], 'start_month' => 3])->from('2025-03-10')->to('2025-12-10');
$schedule->annually(['days_of_month' => [1, 15], 'start_month' => 4])->from('2025-04-01')->to('2026-04-01');
```

**Monthly ordinal weekday** (1st, 2nd, 3rd, 4th, or last weekday of the month)

```php
$schedule->firstWednesdayOfMonth()->forYear(2025);   // Every 1st Wednesday
$schedule->secondFridayOfMonth()->forYear(2025);     // Every 2nd Friday
$schedule->lastMondayOfMonth()->forYear(2025);      // Every last Monday
// Also: thirdTuesdayOfMonth(), fourthSaturdayOfMonth(), lastSundayOfMonth(), etc.
```

**Dynamic intervals**

```php
$schedule->everyThreeWeeks(['monday', 'friday'])->from('2025-01-06')->to('2025-12-31');
$schedule->everyFourWeeks(['tuesday'], '2025-01-06')->from('2025-01-13');
$schedule->everyFourMonths(['day_of_month' => 15])->forYear(2025);
$schedule->everyFiveMonths(['days_of_month' => [1, 15], 'start_month' => 2])->forYear(2025);
```

**RRULE (RFC 5545)**

For complex or standards-based recurrence, pass any valid [RFC 5545 RRULE](https://datatracker.ietf.org/doc/html/rfc5545#section-3.3.10) string. Powered by [php-rrule](https://github.com/rlanvin/php-rrule).

```php
// Every Monday, Wednesday, Friday
$schedule->rrule('FREQ=WEEKLY;BYDAY=MO,WE,FR')->forYear(2025);

// 1st and 15th of every month
$schedule->rrule('FREQ=MONTHLY;BYMONTHDAY=1,15')->forYear(2025);

// First Monday of every month
$schedule->rrule('FREQ=MONTHLY;BYDAY=1MO')->forYear(2025);

// Every 2 weeks on Tuesday (DTSTART derived from schedule start date)
$schedule->rrule('FREQ=WEEKLY;INTERVAL=2;BYDAY=TU')->from('2025-01-07')->to('2025-12-31');

// Every 3 days
$schedule->rrule('FREQ=DAILY;INTERVAL=3')->from('2025-01-01')->to('2025-12-31');
```

### Date ranges

```php
$schedule->from('2025-01-15');                           // Start
$schedule->on('2025-01-15');                             // Alias for from()
$schedule->from('2025-01-01')->to('2025-12-31');        // Range
$schedule->between('2025-01-01', '2025-12-31');          // Same
$schedule->forYear(2025);                                // Full year
```

### Time periods

```php
$schedule->addPeriod('09:00', '17:00');
$schedule->addPeriod('09:00', '12:00');
$schedule->addPeriod('14:00', '17:00');
```

---

## 🔍 Query & check availability

| Need                     | Method |
|--------------------------|--------|
| Any bookable slot today? | `$model->isBookableAt('2025-01-15', 60)` |
| Time range bookable?     | `$model->isBookableAtTime('2025-01-15', '09:00', '09:30')` |
| Time range bookable (custom slot)? | `$model->isBookableAtTime('2025-01-15', '09:30', '10:00', null, 30)` |
| Time range bookable (custom slot + buffer)? | `$model->isBookableAtTime('2025-01-15', '09:45', '10:15', null, 30, 15)` |
| List bookable slots      | `$model->getBookableSlots('2025-01-15', 60, 15)` |
| Next bookable slot       | `$model->getNextBookableSlot('2025-01-15', 60, 15)` |
| Conflicts for a schedule | `Zap::findConflicts($schedule)` / `Zap::hasConflicts($schedule)` |
| Schedules on a date      | `$model->schedulesForDate('2025-01-15')->get()` |
| Schedules in range       | `$model->schedulesForDateRange('2025-01-01', '2025-01-31')->get()` |
| By type                  | `$model->appointmentSchedules()`, `availabilitySchedules()`, `blockedSchedules()` |
| Schedule type checks     | `$schedule->isAvailability()`, `isAppointment()`, `isBlocked()` |

> ⚠️ `isAvailableAt()` is deprecated. Prefer `isBookableAt()`, `isBookableAtTime()`, and `getBookableSlots()`.

---

## 💼 Real-world examples

### Doctor

```php
Zap::for($doctor)->named('Office Hours')->availability()->forYear(2025)
    ->addPeriod('09:00', '12:00')->addPeriod('14:00', '17:00')
    ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])->save();

Zap::for($doctor)->named('Lunch Break')->blocked()->forYear(2025)
    ->addPeriod('12:00', '13:00')
    ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])->save();

Zap::for($doctor)->named('Patient A - Checkup')->appointment()
    ->from('2025-01-15')->addPeriod('10:00', '11:00')->withMetadata(['patient_id' => 1])->save();

$slots = $doctor->getBookableSlots('2025-01-15', 60, 15);
```

### Meeting room

```php
Zap::for($room)->named('Conference Room A')->availability()
    ->weekDays(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'], '08:00', '18:00')
    ->forYear(2025)->save();

Zap::for($room)->named('Board Meeting')->appointment()
    ->from('2025-03-15')->addPeriod('09:00', '11:00')
    ->withMetadata(['organizer' => 'john@company.com'])->save();
```

### Employee (with vacation)

```php
Zap::for($employee)->named('Regular Shift')->availability()
    ->weekDays(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'], '09:00', '17:00')
    ->forYear(2025)->save();

Zap::for($employee)->named('Vacation Leave')->blocked()
    ->between('2025-06-01', '2025-06-15')->addPeriod('00:00', '23:59')->save();
```

---

## ⚙️ Configuration

Publish assets:

```bash
php artisan vendor:publish --tag=zap-migrations
php artisan vendor:publish --tag=zap-config
```

Important keys in `config/zap.php`: `time_slots.buffer_minutes`, `default_rules.no_overlap`, `conflict_detection`, `validation`.

---

## 🛡️ Advanced features

**Custom schedules & rules**

```php
Zap::for($user)->named('Custom Event')->custom()
    ->from('2025-01-15')->addPeriod('15:00', '16:00')->noOverlap()->save();
```

**Metadata**

```php
->withMetadata(['patient_id' => 1, 'type' => 'consultation', 'notes' => 'Follow-up'])
```

**Validation rules:** `noOverlap()`, `allowOverlap()`, `workingHoursOnly('09:00', '17:00')`, `maxDuration(120)`, `noWeekends()`.

### Custom model support (UUIDs)

If your app uses **UUIDs/ULIDs** for primary keys:

1. **Models** — Extend `Zap\Models\Schedule` and `Zap\Models\SchedulePeriod`, add Laravel’s `HasUuids` trait. Add `HasUuids` to your schedulable model (e.g. `Doctor`) as well.
2. **Config** — In `config/zap.php`, set `models.schedule` and `models.schedule_period` to your extended classes.
3. **Migrations** — After publishing, change `id()` to `uuid('id')->primary()`, `morphs('schedulable')` to `uuidMorphs('schedulable')`, and `foreignId('schedule_id')` to `foreignUuid('schedule_id')` in the schedules and schedule_periods tables.

Do this *before* running migrations.

---

## 🧠 AI agent support

Zap provides [Laravel Boost](https://laravel.com/ai/boost) 2.0 skills. With Boost installed, agents get accurate knowledge of the API.

| Skill             | Contents |
|-------------------|----------|
| `zap-schedules`   | Types, builder API, validation, conflict detection |
| `zap-availability` | Bookable slots, availability checks, querying |
| `zap-recurrence`  | All recurrence patterns (daily, weekly, odd/even, monthly, ordinal weekday, dynamic) |

No extra configuration.

---

## 🤝 Contributing

Contributions are welcome. Use PSR-12 and add tests.

```bash
git clone https://github.com/ludoguenet/laravel-zap.git
cd laravel-zap
composer install
composer pest
```

---

## 📄 License

[MIT License](LICENSE).

## 🔒 Security

Report issues to **ludo@epekta.com** (not the public issue tracker).

---

<div align="center">

**Made with 💛 by [Ludovic Guénet](https://www.ludovicguenet.dev) for the Laravel community**

</div>
