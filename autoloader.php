<?php
spl_autoload_register(function(string $strClassName)
{
    $strInclude = '';
    if (strpos($strClassName, '\\') > 1) {
        // replace the namespace prefix with the base directory, replace namespace
        // separators with directory separators in the relative class name, append
        // with .php
        $strInclude = str_replace('\\', DIRECTORY_SEPARATOR, $strClassName) . '.php';
        $strFilename = dirname(__FILE__) . '/' . $strInclude;
        if (!file_exists($strFilename)) {
            $strInclude = 'Vendor' . DIRECTORY_SEPARATOR . $strInclude;
        }
    }

    // if the file exists, require it
    if (strlen($strInclude) > 0) {
        $strInclude = dirname(__FILE__) . '/' . $strInclude;
        if (file_exists($strInclude)) {
            require $strInclude;
        }
    }
});

