<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Accommodation;
use App\Models\RoomType;

function stripStoragePrefix($s) {
    if ($s === null) return null;
    return preg_replace('#^/*storage/*#', '', ltrim($s, '/'));
}

// Fix accommodations
foreach (Accommodation::all() as $acc) {
    $changed = false;
    if ($acc->image) {
        $new = stripStoragePrefix($acc->image);
        if ($new !== $acc->image) { $acc->image = $new; $changed = true; }
    }
    if ($acc->images && is_array($acc->images)) {
        $newImgs = array_map(fn($i)=>stripStoragePrefix($i), $acc->images);
        if ($newImgs !== $acc->images) { $acc->images = $newImgs; $changed = true; }
    }
    if ($changed) { $acc->save(); echo "Fixed accommodation: {$acc->id}\n"; }
}

// Fix room types images
foreach (RoomType::all() as $rt) {
    if ($rt->images && is_array($rt->images)) {
        $newImgs = array_map(fn($i)=>stripStoragePrefix($i), $rt->images);
        if ($newImgs !== $rt->images) { $rt->images = $newImgs; $rt->save(); echo "Fixed room type images: {$rt->id}\n"; }
    }
}

echo "Done.\n";
