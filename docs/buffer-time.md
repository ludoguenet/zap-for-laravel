# Buffer Time in Laravel Zap

Buffer time adds configurable gaps between availability slots to accommodate setup time, cleanup, or prevent back-to-back appointments.

## Quick Start

### Configuration

Set default buffer time in `config/zap.php`:

```php
'time_slots' => [
    'buffer_minutes' => 10, // 10 minutes between appointments
    // ... other settings
],
```

### Usage

```php
// Use global buffer time (from config)
$slots = $user->getAvailableSlots('2025-03-15', '09:00', '17:00', 60);

// Override with specific buffer time
$slots = $user->getAvailableSlots('2025-03-15', '09:00', '17:00', 60, 15);

// Explicitly disable buffer
$slots = $user->getAvailableSlots('2025-03-15', '09:00', '17:00', 60, 0);
```

## How It Works

With 60-minute appointments and 15-minute buffer:
- **9:00-10:00** (Appointment 1)
- **10:15-11:15** (Appointment 2) ← 15-minute gap
- **11:30-12:30** (Appointment 3) ← 15-minute gap

## API Methods

### getAvailableSlots()

```php
$slots = $user->getAvailableSlots(
    date: '2025-03-15',
    dayStart: '09:00',
    dayEnd: '17:00',
    slotDuration: 60,
    bufferMinutes: 15
);

// Response includes buffer_minutes field
[
    [
        'start_time' => '09:00',
        'end_time' => '10:00',
        'is_available' => true,
        'buffer_minutes' => 15
    ],
    // ...
]
```

### getNextAvailableSlot()

```php
$nextSlot = $user->getNextAvailableSlot(
    afterDate: '2025-03-15',
    duration: 90,
    dayStart: '09:00',
    dayEnd: '17:00',
    bufferMinutes: 10
);
```

## Examples

### Healthcare System

```php
// Different buffer times for different appointment types
$consultationSlots = $doctor->getAvailableSlots('2025-03-15', '09:00', '17:00', 30, 10);
$surgerySlots = $surgeon->getAvailableSlots('2025-03-15', '08:00', '16:00', 120, 30);
```

### Service Business

```php
// Hair salon with cleanup time
$haircutSlots = $stylist->getAvailableSlots('2025-03-15', '09:00', '18:00', 45, 15);
$coloringSlots = $stylist->getAvailableSlots('2025-03-15', '09:00', '18:00', 120, 30);
```

## Parameter Precedence

1. **Explicit parameter** (highest priority)
2. **Config value** (when parameter is `null`)
3. **Zero** (when no config set)

## Edge Cases

- **Negative values**: Automatically converted to 0
- **Large buffers**: May reduce number of available slots
- **Buffer > slot duration**: Perfectly valid (e.g., 30min slots with 45min buffer)

## Best Practices

1. **Match your use case**: Medical = longer buffers, quick calls = shorter buffers
2. **Test availability**: Ensure buffer doesn't over-reduce available slots
3. **Document clearly**: Make buffer time visible to users
4. **Consider peak times**: Different buffers for busy vs quiet periods