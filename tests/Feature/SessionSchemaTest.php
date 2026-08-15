<?php

use Illuminate\Support\Facades\Schema;

test('database sessions accept ULID user identifiers', function () {
    expect(Schema::getColumnType('sessions', 'user_id'))->toBe('varchar');
});
