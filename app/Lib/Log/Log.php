<?php

namespace app\Lib\Log;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

/**
 * Helper class to log events
 */
class Log
{
    private $path;
    private $logger;
    private $line_formatter = "[%datetime%] %channel%.%level_name%: %message% \n";
    private $enable_logging = true;

    /**
     * Constructor
     *
     * @param string $log_name The log label identifier.
     * @param string $path The file name where the logs will be stored,
     *                     logs are stored in the laravel default storage.
     */
    public function __construct($log_name = '', $path = 'logs/laravel.log')
    {
        $this->path = storage_path($path);
        $log_name = ($log_name != '') ? $log_name : __FILE__;
        $logger = new Logger($log_name);
        // defining handler
        $handler = new StreamHandler($this->path);
        // line formater: "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n"
        $handler->setFormatter(new LineFormatter($this->line_formatter));
        $logger->pushHandler($handler);
        $this->logger = $logger;
    }

    /**
     * Info
     *
     * @param  string $message
     * @param  array  $context
     * @param  array  $extra
     */
    public function info($message, $context = [], $extra = [])
    {
        if ($this->enable_logging) {
            $this->logger->info($message, $context, $extra);
        }
    }

    /**
     * Warning
     *
     * @param  string $message
     */
    public function warning($message)
    {
        if ($this->enable_logging) {
            $this->logger->warning($message);
        }
    }

    /**
     * Error
     *
     * @param  string $message
     */
    public function error($message)
    {
        if ($this->enable_logging) {
            $this->logger->error($message);
        }
    }

    /**
     * Set logging
     *
     * @param boolean $enable
     */
    public function setLogging($enable = true)
    {
        if ($this->enable_logging) {
            $this->enable_logging = $enable;
        }
    }
}
