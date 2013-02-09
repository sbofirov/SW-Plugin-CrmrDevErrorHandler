<?php
/**
 * Shopware Development ErrorHandler Plugin
 *
 * @author      Benjamin Cremer <crem0r@gmail.com>
 * @copyright   2013 Benjamin Cremer
 * @license     http://opensource.org/licenses/bsd-license.php New BSD License
 * @link        https://github.com/bcremer/
 */
class Shopware_Plugins_Core_CrmrDevErrorHandler_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{
    /**
     * Previously registered error handler
     *
     * @var null|array
     */
    protected $previousErrorHandler = null;

    /**
     * Whether or not the error handler is already registered
     *
     * @var boolean
     */
    protected $isRegistered = false;

    /**
     * Mapping of PHP Predefined numeric error constants to human readable string representations
     * See: http://www.php.net/manual/en/errorfunc.constants.php
     *
     * @var array
     */
    protected $errorNameMap = array(
        E_ERROR             => 'E_ERROR',             // Fatal run-time errors. These indicate errors that can not be recovered from, such as a memory allocation problem. Execution of the script is halted.
        E_WARNING           => 'E_WARNING',           // Run-time warnings (non-fatal errors). Execution of the script is not halted.
        E_PARSE             => 'E_PARSE',             // Compile-time parse errors. Parse errors should only be generated by the parser.
        E_NOTICE            => 'E_NOTICE',            // Run-time notices. Indicate that the script encountered something that could indicate an error, but could also happen in the normal course of running a script.
        E_CORE_ERROR        => 'E_CORE_ERROR',        // Fatal errors that occur during PHP's initial startup. This is like an E_ERROR, except it is generated by the core of PHP.
        E_CORE_WARNING      => 'E_CORE_WARNING',      // Warnings (non-fatal errors) that occur during PHP's initial startup. This is like an E_WARNING, except it is generated by the core of PHP.
        E_COMPILE_ERROR     => 'E_COMPILE_ERROR',     // Fatal compile-time errors. This is like an E_ERROR, except it is generated by the Zend Scripting Engine.
        E_COMPILE_WARNING   => 'E_COMPILE_WARNING',   // Compile-time warnings (non-fatal errors). This is like an E_WARNING, except it is generated by the Zend Scripting Engine.
        E_USER_ERROR        => 'E_USER_ERROR',        // User-generated error message. This is like an E_ERROR, except it is generated in PHP code by using the PHP function trigger_error().
        E_USER_WARNING      => 'E_USER_WARNING',      // User-generated warning message. This is like an E_WARNING, except it is generated in PHP code by using the PHP function trigger_error().
        E_USER_NOTICE       => 'E_USER_NOTICE',       // User-generated notice message. This is like an E_NOTICE, except it is generated in PHP code by using the PHP function trigger_error().
        E_STRICT            => 'E_STRICT',            // Enable to have PHP suggest changes to your code which will ensure the best interoperability and forward compatibility of your code.    Since PHP 5 but not included in E_ALL until PHP 5.4.0
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR', // Catchable fatal error. It indicates that a probably dangerous error occurred, but did not leave the Engine in an unstable state. If the error is not caught by a user defined handle (see also set_error_handler()), the application aborts as it was an E_ERROR.  Since PHP 5.2.0
        E_DEPRECATED        => 'E_DEPRECATED',        // Run-time notices. Enable this to receive warnings about code that will not work in future versions.    Since PHP 5.3.0
        E_USER_DEPRECATED   => 'E_USER_DEPRECATED',   // User-generated warning message. This is like an E_DEPRECATED, except it is generated in PHP code by using the PHP function trigger_error().    Since PHP 5.3.0
        E_ALL               => 'E_ALL',               // All errors and warnings, as supported, except of level E_STRICT prior to PHP 5.4.0.
    );

    /**
     * @return string
     */
    public function getVersion()
    {
       return '1.0.0-dev';
    }

    /**
     * @return array
     */
    public function getInfo()
    {
        return array(
            'version'     => $this->getVersion(),
            'autor'       => 'Benjamin Cremer',
            'copyright'   => 'Copyright (c) 2013, Benjamin Cremer',
            'label'       => 'Development ErrorHandler',
            'description' => 'Plugin intended for developers',
            'license'     => 'New BSD License (http://opensource.org/licenses/bsd-license.php)',
            'link'        => 'https://github.com/bcremer/Shopware-Plugin-CrmrDevErrorHandler',
        );
    }

    /**
     * Plugin install method
     */
    public function install()
    {
        $this->subscribeEvent(
            'Enlight_Controller_Front_StartDispatch',
            'onStartDispatch'
        );

        return true;
    }

    /**
     * Plugin event method
     *
     * @param Enlight_Event_EventArgs $args
     */
    public function onStartDispatch(\Enlight_Event_EventArgs $args)
    {
        $this->registerErrorHandler();
    }

    /**
     * Register error handler callback
     *
     * @link http://www.php.net/manual/en/function.set-error-handler.php Custom error handler
     */
    public function registerErrorHandler()
    {

        // Only register once. Avoids loop issues if it gets registered twice.
        if ($this->isRegistered) {
            return $this;
        }

        $this->previousErrorHandler = set_error_handler(array($this, 'handleError'));
        $this->isRegistered         = true;

        return $this;
    }

    /**
     * Error Handler will convert error into log message, and then call the original error handler
     *
     * @link http://www.php.net/manual/en/function.set-error-handler.php Custom error handler
     * @param  int     $errno      level of the error
     * @param  string  $errstr     error message
     * @param  string  $errfile    filename that the error was raised in
     * @param  int     $errline    line number the error was raised at
     * @param  array   $errcontext additional context
     * @throws ErrorException
     * @return boolean
     */
    public function handleError($errno, $errstr, $errfile, $errline, $errcontext)
    {
        $errorName = isset($this->errorNameMap[$errno]) ? $this->errorNameMap[$errno] : $errno;

        // Respect silent operator e.g. @unlink($possibleFile)
        // see: http://php.net/manual/en/language.operators.errorcontrol.php
        if (error_reporting() === 0) {
            return;
        }

        // Ignore access to not initialized variables in smarty templates
        if ($errno === E_NOTICE && stripos($errfile, 'cache/templates/compile/')) {
            return;
        }

        if (stripos($errfile, 'Library/Smarty/') !== false && stripos($errstr, 'filemtime(): stat failed for') !== false) {
            return;
        }

        if (stripos($errfile, 'cache/templates/compile/') !== false && stripos($errstr, 'Division by zero') !== false) {
            return;
        }

        if ($errno == E_STRICT) {
            return;
        }

        if ($errno == E_NOTICE) {
            return;
        }

        if ($errno == E_WARNING) {
            return;
        }

        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
}
