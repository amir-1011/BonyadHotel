<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Accommodation;
use App\Models\RoomType;

function existsOnDisk($relativePath) {
    if (!$relativePath) return false;
    $p = storage_path('app/public/' . ltrim($relativePath, '/'));
    return file_exists($p);
}

$fixedAcc = 0;
foreach (Accommodation::all() as $acc) {
    $changed = false;
    $images = is_array($acc->images) ? $acc->images : [];
    $images = array_values(array_filter($images, function($i){ return existsOnDisk($i); }));
    if (count($images) !== count($acc->images ?? [])) {
        $acc->images = $images;
        $changed = true;
    }
    $first = $acc->image;
    if ($first && !existsOnDisk($first)) {
        $acc->image = $images[0] ?? null;
        $changed = true;
    }
    if ($changed) { $acc->save(); $fixedAcc++; echo "Fixed accommodation {$acc->id}\n"; }
}

$fixedRt = 0;
foreach (RoomType::all() as $rt) {
    $changed = false;
    $images = is_array($rt->images) ? $rt->images : [];
    $images = array_values(array_filter($images, function($i){ return existsOnDisk($i); }));
    if (count($images) !== count($rt->images ?? [])) {
        $rt->images = $images;
        $changed = true;
    }
    if ($changed) { $rt->save(); $fixedRt++; echo "Fixed room type {$rt->id}\n"; }
}

echo "Summary: accommodations fixed={$fixedAcc}, room_types fixed={$fixedRt}\n";
