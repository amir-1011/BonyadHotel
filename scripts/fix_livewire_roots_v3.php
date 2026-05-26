<?php
/**
 * Fix Livewire MultipleRootElementsDetectedException - v3
 * Handles the case: HTML content first, @push blocks at the bottom
 */

$vp = realpath(__DIR__ . '/../resources/views');

$files = [
    $vp . '\admin\accommodations\edit.blade.php',
    $vp . '\host\accommodations\create.blade.php',
    $vp . '\host\accommodations\edit.blade.php',
];

foreach ($files as $filePath) {
    $rel = str_replace($vp . '\\', '', $filePath);
    if (!file_exists($filePath)) { echo "NOT FOUND: $rel\n"; continue; }

    $content = file_get_contents($filePath);
    $lines = explode("\n", $content);
    $n = count($lines);

    // Find the first @push( line
    $firstPushIdx = -1;
    for ($i = 0; $i < $n; $i++) {
        if (preg_match('/^@push\(/', trim($lines[$i]))) {
            $firstPushIdx = $i;
            break;
        }
    }

    if ($firstPushIdx === -1) {
        // No @push at all - HTML fills entire file
        $htmlStart = 0;
        $htmlEnd = $n - 1;
    } else {
        $htmlStart = 0;
        $htmlEnd = $firstPushIdx - 1;
    }

    // Find last non-blank line in HTML region
    $lastHtmlLine = -1;
    for ($i = $htmlEnd; $i >= $htmlStart; $i--) {
        if (trim($lines[$i]) !== '') { $lastHtmlLine = $i; break; }
    }

    // Count unindented root elements
    $rootCount = 0;
    for ($i = $htmlStart; $i <= $htmlEnd; $i++) {
        $raw = $lines[$i];
        $t = trim($raw);
        $leading = strlen($raw) - strlen(ltrim($raw));
        if ($leading === 0 && preg_match('/^<[a-zA-Z]/', $t) && !preg_match('/^<\//', $t)) {
            $rootCount++;
        }
    }

    echo "File: $rel — $rootCount roots, firstPush=L" . ($firstPushIdx + 1) . ", lastHtml=L" . ($lastHtmlLine + 1) . "\n";

    if ($rootCount <= 1) { echo "  -> ALREADY SINGLE ROOT, skipping\n"; continue; }

    $newLines = $lines;

    // Insert </div> + blank line after last HTML line (before @push blocks)
    array_splice($newLines, $lastHtmlLine + 1, 0, ['</div>', '']);

    // Insert <div> at the very start (position 0)
    array_splice($newLines, 0, 0, ['<div>']);

    $newContent = implode("\n", $newLines);
    file_put_contents($filePath, $newContent);
    echo "  -> FIXED\n";
}
