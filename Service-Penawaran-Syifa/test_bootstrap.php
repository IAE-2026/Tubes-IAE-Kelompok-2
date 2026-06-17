<?php
echo "Step 1: Autoloading...\n";
require 'vendor/autoload.php';
echo "Step 2: Requiring bootstrap/app.php...\n";
$app = require_once 'bootstrap/app.php';
echo "Step 3: Resolving console kernel...\n";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
echo "Step 4: Bootstrapping console kernel...\n";
$kernel->bootstrap();
echo "Step 5: Done!\n";
