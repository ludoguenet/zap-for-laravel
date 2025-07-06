# Schedule Types in Laravel Zap

Laravel Zap now supports different types of schedules to handle complex scheduling scenarios like hospital management systems, appointment booking, and resource allocation.

## Overview

The new `schedule_type` field allows you to distinguish between different types of schedules and control how they interact with each other:

- **Availability**: Working hours or open time slots that allow overlaps
- **Appointment**: Actual bookings that prevent overlaps
- **Blocked**: Unavailable time periods that prevent overlaps
- **Custom**: Default type for backward compatibility

## Schedule Types

### 1. Availability Schedules

Availability schedules represent working hours or open time slots where appointments can be booked. These schedules **allow overlaps** and are typically used to define when someone or something is available.

```php
// Define working hours
$workingHours = Zap::for($doctor)
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

### 2. Appointment Schedules

Appointment schedules represent actual bookings that **prevent overlaps**. These are the concrete appointments that get scheduled within availability windows.

```php
// Create a patient appointment
$appointment = Zap::for($doctor)
    ->named('Patient A - Checkup')
    ->description('Annual checkup appointment')
    ->appointment()
    ->from('2025-01-01')
    ->addPeriod('10:00', '11:00')
    ->withMetadata([
        'patient_id' => 1,
        'appointment_type' => 'checkup',
        'notes' => 'Annual physical examination'
    ])
    ->save();
```

### 3. Blocked Schedules

Blocked schedules represent unavailable time periods that **prevent overlaps**. These are typically used for lunch breaks, holidays, or other times when no appointments should be scheduled.

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

### 4. Custom Schedules

Custom schedules are the default type for backward compatibility. They don't have predefined overlap behavior and rely on the `noOverlap()` rule if specified.

```php
// Custom schedule (default behavior)
$custom = Zap::for($user)
    ->named('Custom Event')
    ->custom()
    ->from('2025-01-01')
    ->addPeriod('15:00', '16:00')
    ->save();
```

## Usage Examples

### Hospital Scheduling System

```php
// Doctor's working hours (availability)
$workingHours = Zap::for($doctor)
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
    ->from('2025-01-01')
    ->addPeriod('10:00', '11:00')
    ->withMetadata(['patient_id' => 1, 'type' => 'consultation'])
    ->save();

$appointment2 = Zap::for($doctor)
    ->named('Patient B - Follow-up')
    ->appointment()
    ->from('2025-01-01')
    ->addPeriod('15:00', '16:00')
    ->withMetadata(['patient_id' => 2, 'type' => 'follow-up'])
    ->save();
```

### Resource Booking System

```php
// Conference room availability
$roomAvailability = Zap::for($conferenceRoom)
    ->named('Conference Room A - Available')
    ->availability()
    ->from('2025-01-01')
    ->to('2025-12-31')
    ->addPeriod('08:00', '18:00')
    ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
    ->save();

// Room bookings
$meeting1 = Zap::for($conferenceRoom)
    ->named('Team Standup')
    ->appointment()
    ->from('2025-01-01')
    ->addPeriod('09:00', '10:00')
    ->withMetadata(['organizer' => 'john@company.com', 'attendees' => 8])
    ->save();

$meeting2 = Zap::for($conferenceRoom)
    ->named('Client Presentation')
    ->appointment()
    ->from('2025-01-01')
    ->addPeriod('14:00', '16:00')
    ->withMetadata(['organizer' => 'jane@company.com', 'attendees' => 15])
    ->save();
```

## Querying by Schedule Type

### Using Model Scopes

```php
// Get all availability schedules
$availabilitySchedules = Schedule::availability()->get();

// Get all appointment schedules
$appointmentSchedules = Schedule::appointments()->get();

// Get all blocked schedules
$blockedSchedules = Schedule::blocked()->get();

// Get schedules of specific type
$customSchedules = Schedule::ofType('custom')->get();
```

### Using Relationship Methods

```php
// Get availability schedules for a user
$userAvailability = $user->availabilitySchedules()->get();

// Get appointment schedules for a user
$userAppointments = $user->appointmentSchedules()->get();

// Get blocked schedules for a user
$userBlocked = $user->blockedSchedules()->get();

// Get schedules of specific type
$userCustom = $user->schedulesOfType('custom')->get();
```

## Availability Checking

The `isAvailableAt()` method now properly handles different schedule types:

```php
// Check if doctor is available at specific time
$isAvailable = $doctor->isAvailableAt('2025-01-01', '10:00', '11:00');

// This will return false if there's an appointment or blocked time
// This will return true if the time is within availability windows
```

## Conflict Detection

Conflict detection now respects schedule types:

- **Availability schedules** never cause conflicts
- **Appointment schedules** conflict with other appointments and blocked schedules
- **Blocked schedules** conflict with appointments and other blocked schedules
- **Custom schedules** follow the `noOverlap()` rule if specified

```php
// This will not cause a conflict (availability allows overlaps)
$availability = Zap::for($user)
    ->availability()
    ->from('2025-01-01')
    ->addPeriod('09:00', '17:00')
    ->save();

// This will cause a conflict with the appointment below
$appointment1 = Zap::for($user)
    ->appointment()
    ->from('2025-01-01')
    ->addPeriod('10:00', '11:00')
    ->save();

// This will throw ScheduleConflictException
try {
    $appointment2 = Zap::for($user)
        ->appointment()
        ->from('2025-01-01')
        ->addPeriod('10:30', '11:30') // Overlaps with appointment1
        ->save();
} catch (ScheduleConflictException $e) {
    // Handle conflict
}
```

## Migration from Metadata Approach

If you were previously using the metadata field to store schedule types, you can migrate to the new approach:

### Before (using metadata)
```php
$schedule = Zap::for($user)
    ->from('2025-01-01')
    ->addPeriod('09:00', '17:00')
    ->withMetadata(['type' => 'availability'])
    ->save();
```

### After (using schedule_type)
```php
$schedule = Zap::for($user)
    ->availability()
    ->from('2025-01-01')
    ->addPeriod('09:00', '17:00')
    ->save();
```

## Backward Compatibility

The new schedule types feature maintains full backward compatibility:

- Existing schedules will default to `custom` type
- The `noOverlap()` method still works as before
- All existing API methods continue to function

## Performance Benefits

The new `schedule_type` column provides several performance benefits:

1. **Indexed queries**: The `schedule_type` column is indexed for faster filtering
2. **Reduced conflict checks**: Only relevant schedule types are checked for conflicts
3. **Better query optimization**: Database can use type-specific indexes

## Best Practices

1. **Use appropriate types**: Always use the most specific schedule type for your use case
2. **Combine with metadata**: Use the `metadata` field for additional context-specific information
3. **Query efficiently**: Use type-specific scopes when querying large datasets
4. **Plan your schema**: Consider your scheduling requirements when choosing between types

## Database Schema

The new `schedule_type` column is added to the `schedules` table:

```sql
ALTER TABLE schedules ADD COLUMN schedule_type ENUM('availability', 'appointment', 'blocked', 'custom') DEFAULT 'custom';
CREATE INDEX schedules_type_index ON schedules(schedule_type);
CREATE INDEX schedules_schedulable_type_index ON schedules(schedulable_type, schedulable_id, schedule_type);
```

This enhancement makes Laravel Zap much more suitable for complex scheduling scenarios like hospital management systems, where the distinction between availability windows and actual appointments is crucial.
