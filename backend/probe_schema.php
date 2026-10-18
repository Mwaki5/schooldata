<?php

// One-off probe (deleted after running): index names, orphan masters, migration state.
$app = require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "units indexes:\n";
foreach (DB::select('SHOW INDEX FROM units') as $ix) {
    echo "  Key_name={$ix->Key_name} seq={$ix->Seq_in_index} col={$ix->Column_name} unique=" . ($ix->Non_unique ? 'no' : 'yes') . "\n";
}

echo "masters with no course_units row: " .
    DB::table('units')
        ->leftJoin('course_units', 'units.id', '=', 'course_units.unit_id')
        ->whereNull('course_units.id')
        ->count() . "\n";

echo "distinct unit_ids in course_units: " .
    DB::table('course_units')->distinct()->count('unit_id') . "\n";

echo "units rows (id/code/name/deleted): " .
    json_encode(DB::select('SELECT id, code, name, deleted_at FROM units')) . "\n";

echo "course_units soft-deleted: " . DB::table('course_units')->whereNotNull('deleted_at')->count() .
    ' live: ' . DB::table('course_units')->whereNull('deleted_at')->count() . "\n";

echo "migrations: " .
    implode(', ', DB::table('migrations')->orderBy('id')->pluck('migration')->all()) . "\n";
