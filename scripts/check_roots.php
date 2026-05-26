<?php
$vp = __DIR__ . '/../resources/views';
$vp = realpath($vp);
$iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($vp));
foreach ($iter as $file) {
    if (!str_ends_with($file->getPathname(), '.blade.php')) continue;
    if (str_contains($file->getPathname(), 'layouts')) continue;
    $lines = file($file->getPathname());
    $cnt = 0;
    $inPush = false;
    foreach ($lines as $l) {
        $t = trim($l);
        if (preg_match('/^@push\(/', $t)) { $inPush = true; }
        if ($t === '@endpush') { $inPush = false; continue; }
        if (!$inPush && preg_match('/^<(div|section|nav|header|main|article|ul|table|form)[\s>]/', $t)) {
            $cnt++;
        }
    }
    if ($cnt > 1) {
        $rel = str_replace($vp . DIRECTORY_SEPARATOR, '', $file->getPathname());
        echo $cnt . "\t" . $rel . PHP_EOL;
    }
}
