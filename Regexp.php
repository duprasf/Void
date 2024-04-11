<?php

namespace Void;

class Regexp
{
    public static function isValid(string $pattern)
    {
        set_exception_handler('exception_handler_temp_');
        $old_error_handler = set_error_handler('error_handler_temp_');

        $return = !!preg_match($pattern, '');

        set_error_handler($old_error_handler);
        restore_error_handler();

        return $return;
    }
}

/**
* Temporary function to set as error handler
*
* @param mixed $errno
* @param mixed $errstr
* @param mixed $errfile
* @param mixed $errline
* @param array $errcontext
*/
function error_handler_temp_(
    int $errno,
    string $errstr,
    string $errfile = '',
    int $errline = '',
    array $errcontext = ''
) {
    return null;
}
/**
* Temporary function to set as exception handler
*/
function exception_handler_temp_($exception)
{
    return null;
}
