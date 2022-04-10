<?php

namespace tp\TouchPointWP;

use Exception;
use Throwable;

/**
 * Settings class file.
 *
 * Class TouchPointWP_Exception
 * @package tp\TouchPointWP
 */

if ( ! defined('ABSPATH')) {
    exit;
}

/**
 * An Exception class.
 */
class TouchPointWP_Exception extends Exception
{
    /**
     * Create an exception, and log it where it can be reported to the TouchPoint-WP Developers.
     *
     * @param string     $message
     * @param int        $code
     * @param ?Throwable $previous
     */
    public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        error_log($message);
        if (TouchPointWP::DEBUG) {
            file_put_contents(
                TouchPointWP::$dir . '/TouchPointWP_ErrorLog.txt',
                time() . "\t" . TouchPointWP::VERSION . "\t" . $this->getFile() . "#" . $this->getLine() . "\t" . $this->getMessage() . "\n",
                FILE_APPEND | LOCK_EX
            );
        }
    }
}
