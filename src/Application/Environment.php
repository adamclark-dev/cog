<?php

namespace Message\Cog\Application;

/**
 * Determines the environment the application is running in.
 *
 * An environment is made up of two things:
 *  - A name
 *  - A context
 *  - An installation name (optional)
 *
 * Environments define the 'place' where a Cog app is running. The environment
 * names are predefined and can't be changed. The allowed options are
 * currently: live, local, dev, staging and test.
 *
 * An installation name can defined. This is a way to distinguish a specific
 * installation of the application in a given environment. For example, a
 * specific development testing site called "dev2", or on a load-balanced set
 * up with two front-end servers, you might have two installations in the live
 * environment called "live1" and "live2".
 *
 * This class also determines what context the request is running in: 'web' if
 * the app is being access via HTTP or 'console' if it's being run via the
 * command line.
 *
 * @author James Moss <james@message.co.uk>
 *
 * @todo security protocol for live
 */
class Environment implements EnvironmentInterface
{
	const ENV_SEPARATOR = '-';

	protected $_flagEnv = 'COG_ENV';
	protected $_context;
	protected $_name;
	protected $_installation;
	protected $_allowedEnvironments = array(
		'local', 	// Developers machine
		'test', 	// When test suite is running
		'dev',		// A test site on a client server
		'staging',	// Deployed code ready to go live
		'live',		// Live public facing site
	);
	protected $_allowedContexts = array(
		'web',		// Running in fcgi or as mod_php
		'console',	// Run from the command line
	);

	/**
	 * Constructor.
	 *
	 * @param string $environmentVarFlag A custom flagname to use to detect the
	 *                                   environment name. If null, self::COG_ENV
	 *                                   is used as a default.
	 */
	public function __construct($environmentVarFlag = null)
	{
		if (!empty($environmentVarFlag)) {
			$this->_flagEnv = $environmentVarFlag;
		}

		$this->_detectContext();
		$this->_detectEnvironment();
	}

	/**
	 * Gets the current environment name.
	 *
	 * @return string The current environment name
	 */
	public function get()
	{
		return $this->_name;
	}

	public function getWithInstallation()
	{
		if ($inst = $this->installation()) {
			return $this->get() . '-' . $inst;
		}

		return $this->get();
	}

	/**
	 * Sets the current environment, overriding the detected environment.
	 *
	 * @param string $name A valid environment name to change to
	 */
	public function set($name)
	{
		$this->_validate('environment', $name, $this->_allowedEnvironments);
		$this->_name = $name;
	}

	/**
	 * Sets the current environment with the installation name.
	 *
	 * @param string $name A valid environment and installation name
	 */
	public function setWithInstallation($name)
	{
		if (false !== strpos($name, self::ENV_SEPARATOR)) {
			list($name, $installation) = explode(self::ENV_SEPARATOR, $name);
			$this->_installation = $installation;
		}

		return $this->set($name);
	}

	/**
	 * Useful accessor to check if we're running on a developers machine.
	 *
	 * @return boolean True if on local development machine
	 */
	public function isLocal()
	{
		return $this->get() === 'local';
	}

	/**
	 * Gets the name of the current context.
	 *
	 * @return string The current context name
	 */
	public function context()
	{
		return $this->_context;
	}

	/**
	 * Gets the name of the current installation.
	 *
	 * @return string The current installation name
	 */
	public function installation()
	{
		return $this->_installation;
	}

	/**
	 * Manually set the context of the environment. This method should very
	 * rarely need to be used, most likely only for testing purposes.
	 *
	 * @todo Remove this. It seems to only be used by unit tests which is bad.
	 *       We should mock or extend the class instead for testing purposes.
	 *
	 * @param string $context A valid context name
	 */
	public function setContext($context)
	{
		$this->_validate('context', $context, $this->_allowedContexts);
		$this->_context = $context;
	}

	/**
	 * Searches the global environment and then the $_SERVER superglobal for a
	 * variable.
	 *
	 * Normally these have been set in a vhost config or .profile file. It falls
	 * back to the $_SERVER superglobal as $_ENV can't be populated when PHP
	 * is running via PHP-FPM.
	 *
	 * @param  string $varName Name of the variable to search for
	 *
	 * @return string|false    Value of the found variable (as a string) or
	 *                         false if it doesnt exist
	 */
	public function getEnvironmentVar($varName)
	{
		$definedVar = getenv($varName);
		if ($definedVar === false && isset($_SERVER[$varName])) {
			$definedVar = $_SERVER[$varName];
		}

		return $definedVar;
	}

	/**
	 * Validates an environment or context name.
	 *
	 * @param  string $type    The type of variable to validate
	 * @param  string $value   The value that will be changed to
	 * @param  array  $allowed An array of allowed values
	 *
	 * @return boolean         Returns true if $value is in the allowed list
	 *
	 * @throws \InvalidArgumentException If the value is not valid
	 */
	protected function _validate($type, $value, array $allowed)
	{
		if (!in_array($value, $allowed)) {
			$msg = '`%s` is not a valid %s. Allowed values: `%s`';
			throw new \InvalidArgumentException(
				sprintf($msg, $value, $type, implode('`, `', $allowed))
			);
		}

		return true;
	}

	/**
	 * Tries to detect the current environment name automatically.
	 *
	 * If the environment includes the character defined as `self::ENV_SEPARATOR`
	 * then the string after the separator is set as the installation name.
	 */
	protected function _detectEnvironment()
	{
		if ($env = $this->getEnvironmentVar($this->_flagEnv)) {
			return $this->setWithInstallation($env);
		}

		// Compatibility for how environment variables are set on Apache2 PHP-FPM
		if ($env = $this->getEnvironmentVar('REDIRECT_' . $this->_flagEnv)) {
			return $this->setWithInstallation($env);
		}

		return $this->set($this->_allowedEnvironments[0]);
	}

	/**
	 * Tries to detect the current context name automatically.
	 */
	protected function _detectContext()
	{
		$this->setContext(php_sapi_name() == 'cli' ? 'console' : 'web');
	}
}