<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Schedule Rules
    |--------------------------------------------------------------------------
    |
    | These are the default validation rules that will be applied to all
    | schedules unless overridden during creation.
    |
    */
    'default_rules' => [
        'no_overlap' => [
            'enabled' => true,
            'applies_to' => [
                // Which schedule types get this rule automatically
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
            'minutes' => 480, // Maximum period duration in minutes if enabled
        ],
        'no_weekends' => [
            'enabled' => false,
            'saturday' => true,
            'sunday' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Conflict Detection
    |--------------------------------------------------------------------------
    |
    | Configure how schedule conflicts are detected and handled.
    |
    */
    'conflict_detection' => [
        'enabled' => true,
        'buffer_minutes' => 0, // Buffer time between schedules
        'auto_resolve' => false, // Automatically resolve conflicts
        'strict_mode' => true, // Throw exceptions on conflicts
    ],

    /*
    |--------------------------------------------------------------------------
    | Recurring Schedules
    |--------------------------------------------------------------------------
    |
    | Settings for processing recurring schedules and cleanup.
    |
    */
    'recurring' => [
        'process_days_ahead' => 30, // Generate instances this many days ahead
        'cleanup_expired_after_days' => 90, // Clean up expired schedules after X days
        'max_instances' => 1000, // Maximum instances to generate at once
        'supported_frequencies' => ['daily', 'weekly', 'monthly', 'yearly'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Time Slots Configuration
    |--------------------------------------------------------------------------
    |
    | Default settings for time slot generation and availability checking.
    |
    */
    'time_slots' => [
        'default_duration' => 60, // minutes
        'min_duration' => 15, // minutes
        'max_duration' => 480, // minutes (8 hours)
        'business_hours' => [
            'start' => '09:00',
            'end' => '17:00',
        ],
        'slot_intervals' => [15, 30, 60, 120], // Available slot durations
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    |
    | Configure custom validation rules and their settings.
    |
    */
    'validation' => [
        'require_future_dates' => true, // Schedules must be in the future
        'max_date_range' => 365, // Maximum days between start and end date
        'min_period_duration' => 15, // Minimum period duration in minutes
        'max_periods_per_schedule' => 50, // Maximum periods per schedule
        'allow_overlapping_periods' => false, // Allow periods to overlap within same schedule
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Listeners
    |--------------------------------------------------------------------------
    |
    | Configure which events should be fired and handled.
    |
    */
    'events' => [
        'schedule_created' => true,
    ],
];
