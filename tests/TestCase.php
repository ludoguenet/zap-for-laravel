<?php

namespace Zap\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;
use Zap\ZapServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function getPackageProviders($app): array
    {
        return [
            ZapServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Load package configuration with test-friendly defaults
        $app['config']->set('zap', [
            'conflict_detection' => [
                'enabled' => true,
                'buffer_minutes' => 0,
            ],
            'validation' => [
                'require_future_dates' => false,
                'max_date_range' => 3650,
                'min_period_duration' => 1,
                'max_period_duration' => 1440,
                'max_periods_per_schedule' => 100,
                'allow_overlapping_periods' => true,
            ],
            'default_rules' => [
                'no_overlap' => [
                    'enabled' => true,
                    'applies_to' => ['appointment', 'blocked'],
                ],
                'working_hours' => [
                    'enabled' => false,
                    'start' => '09:00',
                    'end' => '17:00',
                ],
                'max_duration' => [
                    'enabled' => false,
                    'minutes' => 480,
                ],
                'no_weekends' => [
                    'enabled' => false,
                    'saturday' => true,
                    'sunday' => true,
                ],
            ],
            'cache' => [
                'enabled' => false,
            ],
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
