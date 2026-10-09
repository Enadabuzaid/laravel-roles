<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

function seedUsers(int $count): void
{
    $rows = [];
    for ($i = 1; $i <= $count; $i++) {
        $rows[] = ['name' => "User {$i}", 'email' => "user{$i}@example.test", 'password' => 'x', 'created_at' => now(), 'updated_at' => now()];
    }
    DB::table('users')->insert($rows);
}
