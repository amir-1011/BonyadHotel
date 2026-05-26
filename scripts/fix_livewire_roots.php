<?php
/**
 * Fix Livewire MultipleRootElementsDetectedException
 * Wraps all HTML content between @push blocks in a single <div> root
 */

$vp = realpath(__DIR__ . '/../resources/views');

// Files already fixed or to skip
$skip = ['welcome.blade.php']; // welcome is not a Livewire component

// Files to fix (all non-layout blade files)
$iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($vp));
$files = [];
foreach ($iter as $file) {
    if (!str_ends_with($file->getPathname(), '.blade.php')) continue;
    if (str_contains($file->getPathname(), DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR)) continue;
    $rel = str_replace($vp . DIRECTORY_SEPARATOR, '', $file->getPathname());
    if (in_array(basename($rel), $skip)) continue;
    $files[] = $file->getPathname();
}

$fixed = 0;
$skipped = 0;
$errors = [];

foreach ($files as $filePath) {
    $rel = str_replace($vp . DIRECTORY_SEPARATOR, '', $filePath);
    $content = file_get_contents($filePath);
    $lines = explode("\n", $content);
    $n = count($lines);
    
    // Find the last @endpush line index (0-based)
    $lastEndpushIdx = -1;
    $inPush = false;
    $pushCount = 0;
    for ($i = 0; $i < $n; $i++) {
        $t = trim($lines[$i]);
        if (preg_match('/^@push\(/', $t)) { $inPush = true; $pushCount++; }
        if ($t === '@endpush') { $inPush = false; $lastEndpushIdx = $i; }
    }
    
    // Find first @push('scripts') or @push('js') after HTML content
    $scriptsPushIdx = -1;
    for ($i = 0; $i < $n; $i++) {
        $t = trim($lines[$i]);
        if (preg_match("/^@push\(['\"](scripts|js)['\"]\)/", $t)) {
            $scriptsPushIdx = $i;
            break;
        }
    }
    
    // Determine the HTML content region
    // HTML starts after last @endpush (or at line 0 if no @push blocks)
    $htmlStart = ($lastEndpushIdx >= 0) ? $lastEndpushIdx + 1 : 0;
    // HTML ends before @push('scripts') (or at end of file)
    $htmlEnd = ($scriptsPushIdx >= 0) ? $scriptsPushIdx - 1 : $n - 1;
    
    // Find actual first HTML line (skip blanks and blade comments)
    $firstHtmlLine = -1;
    for ($i = $htmlStart; $i <= $htmlEnd; $i++) {
        $t = trim($lines[$i]);
        if ($t !== '' && !preg_match('/^{{--/', $t) && !preg_match('/^--}}/', $t) && !preg_match('/^@/', $t)) {
            if (preg_match('/^</', $t) || preg_match('/^@(if|foreach|for|while|php|auth|guest|can)/', $t)) {
                $firstHtmlLine = $i;
                break;
            }
        }
    }
    
    // Find actual last HTML line (skip blanks)
    $lastHtmlLine = -1;
    for ($i = $htmlEnd; $i >= $htmlStart; $i--) {
        $t = trim($lines[$i]);
        if ($t !== '') {
            $lastHtmlLine = $i;
            break;
        }
    }
    
    if ($firstHtmlLine === -1 || $lastHtmlLine === -1) {
        echo "SKIP (no HTML): $rel\n";
        $skipped++;
        continue;
    }
    
    // Check if already wrapped in a single <div>
    // Look at $firstHtmlLine - it should be a simple <div> (the wrapper)
    $firstHtmlTag = trim($lines[$firstHtmlLine]);
    $lastHtmlTag = trim($lines[$lastHtmlLine]);
    
    // If first line is exactly "<div>" and last is "</div>", already wrapped
    if ($firstHtmlTag === '<div>' && $lastHtmlTag === '</div>') {
        // Verify it's a wrapper by checking it closes at lastHtmlLine
        // Simple check: skip
        echo "ALREADY WRAPPED: $rel\n";
        $skipped++;
        continue;
    }
    
    // Count top-level root elements (elements at column 0 outside @push)
    $rootCount = 0;
    $inPush2 = false;
    for ($i = $htmlStart; $i <= $htmlEnd; $i++) {
        $t = trim($lines[$i]);
        if (preg_match('/^@push\(/', $t)) { $inPush2 = true; }
        if ($t === '@endpush') { $inPush2 = false; continue; }
        if (!$inPush2 && preg_match('/^<[a-zA-Z]/', $t) && !preg_match('/^<\//', $t)) {
            // Check if this is at the start of the content (not indented)
            if (strlen($lines[$i]) === strlen(ltrim($lines[$i])) || 
                (strlen($lines[$i]) - strlen(ltrim($lines[$i]))) === 0) {
                $rootCount++;
            }
        }
    }
    
    if ($rootCount <= 1) {
        echo "SINGLE ROOT ($rootCount): $rel\n";
        $skipped++;
        continue;
    }
    
    // Perform the fix: insert <div> after htmlStart-1 and </div> before scriptsPushIdx
    // Build new content
    $newLines = $lines;
    
    // Insert </div> before @push('scripts') or at end
    if ($scriptsPushIdx >= 0) {
        array_splice($newLines, $scriptsPushIdx, 0, ['', '</div>']);
    } else {
        // Add at end
        $newLines[] = '</div>';
    }
    
    // Insert <div> after last @endpush (or at start)
    // Find the blank lines between @endpush and first HTML
    $insertAfter = $lastEndpushIdx >= 0 ? $lastEndpushIdx : -1;
    array_splice($newLines, $insertAfter + 1, 0, ['<div>']);
    
    $newContent = implode("\n", $newLines);
    file_put_contents($filePath, $newContent);
    echo "FIXED ($rootCount roots -> 1): $rel\n";
    $fixed++;
}

echo "\n=== SUMMARY ===\n";
echo "Fixed: $fixed\n";
echo "Skipped: $skipped\n";
if ($errors) {
    echo "Errors:\n";
    foreach ($errors as $e) echo "  - $e\n";
}
