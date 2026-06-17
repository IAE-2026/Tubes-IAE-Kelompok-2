<?php
echo "Step 1: Autoloading...\n";
require 'vendor/autoload.php';
echo "Step 2: Requiring bootstrap/app.php...\n";
$app = require_once 'bootstrap/app.php';
echo "Step 3: Resolving console kernel...\n";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$ref = new ReflectionClass(get_class($kernel));
while ($ref && !$ref->hasMethod('bootstrappers')) {
    $ref = $ref->getParentClass();
}

if (!$ref) {
    echo "Method 'bootstrappers' not found\n";
    exit(1);
}

$method = $ref->getMethod('bootstrappers');
$method->setAccessible(true);
$bootstrappers = $method->invoke($kernel);

echo "Step 4: Running bootstrappers one by one...\n";
foreach ($bootstrappers as $bootstrapper) {
    echo "Running bootstrapper: " . $bootstrapper . "...\n";
    $app->bootstrapWith([$bootstrapper]);
    echo "Completed bootstrapper: " . $bootstrapper . "\n";
}
echo "Step 5: Done!\n";
