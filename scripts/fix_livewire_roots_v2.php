<?php
/**
 * Fix Livewire MultipleRootElementsDetectedException - v2
 * Wraps all HTML content between @push blocks in a single <div> root
 */

$vp = realpath(__DIR__ . '/../resources/views');

$iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($vp));
$files = [];
foreach ($iter as $file) {
    if (!str_ends_with($file->getPathname(), '.blade.php')) continue;
    if (str_contains($file->getPathname(), DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR)) continue;
    $files[] = $file->getPathname();
}

sort($files);

$fixed = 0;
$alreadyWrapped = 0;
$skipped = 0;

foreach ($files as $filePath) {
    $rel = str_replace($vp . DIRECTORY_SEPARATOR, '', $filePath);
    $content = file_get_contents($filePath);
    $lines = explode("\n", $content);
    $n = count($lines);

    // Step 1: Find first @push('scripts') or @push('js') line index
    $scriptsPushIdx = -1;
    for ($i = 0; $i < $n; $i++) {
        $t = trim($lines[$i]);
        if (preg_match("/^@push\(['\"](scripts|js)['\"]\)/", $t)) {
            $scriptsPushIdx = $i;
            break;
        }
    }

    // Step 2: Find the last @endpush that is BEFORE @push('scripts')
    // (this is the end of @push('styles'))
    $stylesEndpushIdx = -1;
    $limit = ($scriptsPushIdx >= 0) ? $scriptsPushIdx : $n;
    for ($i = 0; $i < $limit; $i++) {
        $t = trim($lines[$i]);
        if ($t === '@endpush') {
            $stylesEndpushIdx = $i;
        }
    }

    // Step 3: Determine HTML region
    $htmlStart = ($stylesEndpushIdx >= 0) ? $stylesEndpushIdx + 1 : 0;
    $htmlEnd   = ($scriptsPushIdx >= 0) ? $scriptsPushIdx - 1 : $n - 1;

    // Step 4: Find first and last non-blank lines in HTML region
    $firstHtmlLine = -1;
    for ($i = $htmlStart; $i <= $htmlEnd; $i++) {
        $t = trim($lines[$i]);
        if ($t !== '') {
            $firstHtmlLine = $i;
            break;
        }
    }

    $lastHtmlLine = -1;
    for ($i = $htmlEnd; $i >= $htmlStart; $i--) {
        $t = trim($lines[$i]);
        if ($t !== '') {
            $lastHtmlLine = $i;
            break;
        }
    }

    if ($firstHtmlLine === -1) {
        echo "SKIP (empty HTML region): $rel\n";
        $skipped++;
        continue;
    }

    // Step 5: Check if already wrapped with a simple <div>
    // If first non-blank is exactly "<div>" and last non-blank is "</div>"
    $firstTag = trim($lines[$firstHtmlLine]);
    $lastTag  = trim($lines[$lastHtmlLine]);

    if ($firstTag === '<div>' && $lastTag === '</div>') {
        echo "ALREADY WRAPPED: $rel\n";
        $alreadyWrapped++;
        continue;
    }

    // Step 6: Count top-level (unindented) root HTML elements
    $rootCount = 0;
    $inPushInner = false;
    for ($i = $firstHtmlLine; $i <= $lastHtmlLine; $i++) {
        $raw = $lines[$i];
        $t = trim($raw);
        if (preg_match('/^@push\(/', $t)) { $inPushInner = true; }
        if ($t === '@endpush') { $inPushInner = false; continue; }
        if ($inPushInner) continue;
        // Root element: tag that starts at column 0 (no leading whitespace)
        $leadingSpaces = strlen($raw) - strlen(ltrim($raw));
        if ($leadingSpaces === 0 && preg_match('/^<[a-zA-Z]/', $t) && !preg_match('/^<\//', $t)) {
            $rootCount++;
        }
    }

    if ($rootCount <= 1) {
        echo "SINGLE ROOT ($rootCount): $rel\n";
        $skipped++;
        continue;
    }

    // Step 7: Perform the fix
    $newLines = $lines;

    // Insert </div> before @push('scripts') (or at end)
    if ($scriptsPushIdx >= 0) {
        array_splice($newLines, $scriptsPushIdx, 0, ['</div>', '']);
    } else {
        $newLines[] = '</div>';
    }

    // Insert <div> after @endpush (stylesEndpushIdx) or at htmlStart
    // Note: after the above splice, if scriptsPushIdx > stylesEndpushIdx (normal case), indices shift
    // But the insert is at stylesEndpushIdx + 1 which is before scriptsPushIdx, so no shift needed
    if ($stylesEndpushIdx >= 0) {
        array_splice($newLines, $stylesEndpushIdx + 1, 0, ['', '<div>']);
    } else {
        // No styles push - insert <div> at very beginning
        array_splice($newLines, 0, 0, ['<div>']);
    }

    $newContent = implode("\n", $newLines);
    file_put_contents($filePath, $newContent);
    echo "FIXED ($rootCount roots): $rel\n";
    $fixed++;
}

echo "\n=== SUMMARY ===\n";
echo "Fixed:           $fixed\n";
echo "Already wrapped: $alreadyWrapped\n";
echo "Skipped:         $skipped\n";
