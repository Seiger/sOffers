<?php
/**
 * Compile Blade to strings and parse the resulting PHP without rendering or CMS boot.
 *
 * Pass the path to an installed Illuminate vendor directory as the first argument.
 * Only its PSR-4 map and Illuminate helper definitions are loaded; Composer's
 * autoload entry point and its application-specific files are not executed.
 */

$vendor = isset($argv[1]) ? realpath($argv[1]) : false;
if ($vendor === false || !is_file($vendor . '/composer/autoload_psr4.php')) {
    fwrite(STDERR, "Usage: php tests/compile-views.php /path/to/cms/core/vendor\n");
    exit(1);
}
$prefixes = require $vendor . '/composer/autoload_psr4.php';
spl_autoload_register(function ($class) use ($prefixes) {
    foreach ($prefixes as $prefix => $directories) {
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            continue;
        }
        foreach ($directories as $directory) {
            $file = $directory . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
                return;
            }
        }
    }
});
require_once $vendor . '/illuminate/collections/helpers.php';
require_once $vendor . '/illuminate/support/helpers.php';
if (is_file($vendor . '/symfony/polyfill-php85/bootstrap.php')) {
    require_once $vendor . '/symfony/polyfill-php85/bootstrap.php';
}
$compiler = new Illuminate\View\Compilers\BladeCompiler(new Illuminate\Filesystem\Filesystem(), sys_get_temp_dir());
$files = glob(dirname(__DIR__) . '/views/*.blade.php');
foreach ($files as $file) {
    $compiled = $compiler->compileString(file_get_contents($file));
    token_get_all($compiled, TOKEN_PARSE);
}
echo 'PASS: ' . count($files) . " Blade templates compiled and parsed (not rendered)\n";
