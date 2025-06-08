<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(Zap\Tests\TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

expect()->extend('toHaveSchedule', function () {
    return $this->toHaveProperty('schedules');
});

expect()->extend('toBeSchedulable', function () {
    return $this->toHaveMethod('schedules');
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function createUser()
{
    static $instance = null;

    if ($instance === null) {
        $instance = new class extends \Illuminate\Database\Eloquent\Model
        {
            use \Zap\Models\Concerns\HasSchedules;

            protected $table = 'users';

            protected $fillable = ['name', 'email'];

            public function getKey()
            {
                return 1; // Mock user ID
            }
        };
    }

    return $instance;
}

function createRoom()
{
    static $instance = null;

    if ($instance === null) {
        $instance = new class extends \Illuminate\Database\Eloquent\Model
        {
            use \Zap\Models\Concerns\HasSchedules;

            protected $table = 'rooms';

            protected $fillable = ['name', 'capacity'];

            public function getKey()
            {
                return 1; // Mock room ID
            }
        };
    }

    return $instance;
}

function createScheduleFor($schedulable, array $attributes = [])
{
    $attributes = array_merge([
        'name' => 'Test Schedule',
        'start_date' => '2024-01-01',
        'periods' => [
            ['start_time' => '09:00', 'end_time' => '10:00'],
        ],
    ], $attributes);

    $builder = \Zap\Facades\Zap::for($schedulable)
        ->named($attributes['name'])
        ->from($attributes['start_date']);

    if (isset($attributes['end_date'])) {
        $builder->to($attributes['end_date']);
    }

    foreach ($attributes['periods'] as $period) {
        $builder->addPeriod($period['start_time'], $period['end_time']);
    }

    return $builder->save();
}
