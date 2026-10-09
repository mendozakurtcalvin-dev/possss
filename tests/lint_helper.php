<?php
// Temporary diagnostic helper (safe to delete): reports parse errors with exact lines.
$files = array_slice($argv, 1);
if (!count($files)) {
    $files = [__DIR__ . '/../views/pages/purchase_requests.php'];
}
foreach ($files as $file) {
    $src = file_get_contents($file);
    try {
        token_get_all($src, TOKEN_PARSE);
        echo basename($file) . ": parse ok\n";
    } catch (ParseError $e) {
        echo basename($file) . ': ' . $e->getMessage() . ' @ line ' . $e->getLine() . "\n";
        $lines = preg_split('/\r\n|\n|\r/', $src);
        $start = max(0, $e->getLine() - 4);
        for ($i = $start; $i < min(count($lines), $e->getLine() + 2); $i++) {
            echo ($i + 1) . ': ' . $lines[$i] . "\n";
        }
    }
}
