<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$rows = Illuminate\Support\Facades\DB::select(
    "SELECT p.id FROM programs p JOIN accommodations a ON a.id = p.accommodation_id JOIN users u ON u.id = a.host_id WHERE u.mobile = '09110000001' LIMIT 3"
);
foreach ($rows as $r) { echo "program_id: " . $r->id . PHP_EOL; }
if (!$rows) echo "no programs found" . PHP_EOL;
