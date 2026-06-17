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
$method = $ref->getMethod('bootstrappers');
$method->setAccessible(true);
$bootstrappers = $method->invoke($kernel);

echo "Step 4: Running bootstrappers BEFORE BootProviders...\n";
foreach ($bootstrappers as $bootstrapper) {
    if ($bootstrapper === 'Illuminate\Foundation\Bootstrap\BootProviders') {
        continue;
    }
    echo "Running bootstrapper in Step 4: " . $bootstrapper . "...\n";
    $app->bootstrapWith([$bootstrapper]);
    echo "Completed bootstrapper in Step 4: " . $bootstrapper . "\n";
}

echo "Step 5: Booting providers one by one manually...\n";
$refApp = new ReflectionClass($app);
while ($refApp && !$refApp->hasProperty('serviceProviders')) {
    $refApp = $refApp->getParentClass();
}
$prop = $refApp->getProperty('serviceProviders');
$prop->setAccessible(true);
$providers = $prop->getValue($app);

$refAppBoot = new ReflectionClass($app);
while ($refAppBoot && !$refAppBoot->hasMethod('bootProvider')) {
    $refAppBoot = $refAppBoot->getParentClass();
}
$methodBoot = $refAppBoot->getMethod('bootProvider');
$methodBoot->setAccessible(true);

foreach ($providers as $provider) {
    $name = get_class($provider);
    echo "Booting provider: " . $name . "...\n";
    $methodBoot->invoke($app, $provider);
    echo "Completed provider: " . $name . "\n";
}
echo "Step 6: Done!\n";
