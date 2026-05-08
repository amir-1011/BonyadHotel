<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$acc = App\Models\Accommodation::first();
if (!$acc) {
    echo "NO_ACCOMMODATION\n";
    exit(1);
}
echo "image:" . ($acc->image ?? 'NULL') . "\n";
echo "images:" . json_encode($acc->images) . "\n";
