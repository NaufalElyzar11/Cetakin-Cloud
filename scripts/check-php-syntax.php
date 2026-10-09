<?php

$root = dirname(__DIR__);
$files = [$root.'/artisan'];
foreach (['app', 'bootstrap', 'config', 'routes', 'scripts', 'tests'] as $directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php'
            && ! str_contains(str_replace('\\', '/', $file->getPathname()), '/bootstrap/cache/')) {
            $files[] = $file->getPathname();
        }
    }
}
sort($files);
foreach ($files as $file) {
    $process = proc_open([PHP_BINARY, '-l', $file], [STDIN, STDOUT, STDERR], $pipes);
    if (! is_resource($process) || proc_close($process) !== 0) {
        exit(1);
    }
}
echo 'PHP syntax passed for '.count($files)." source files.\n";
