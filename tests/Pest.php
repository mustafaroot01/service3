<?php

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)
    ->beforeEach(fn () => $this->seed(RolePermissionSeeder::class))
    ->in('Feature');

uses(TestCase::class)->in('Unit');
