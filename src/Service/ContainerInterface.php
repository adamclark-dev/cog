<?php

namespace Message\Cog\Service;

use Closure;

/**
 * Interface that defines our service container.
 *
 * @author Joe Holdcroft <joe@message.co.uk>
 */
interface ContainerInterface extends \ArrayAccess
{
	/**
	 * Singleton accessor
	 *
	 * @return Container The instance of self
	 */
	static public function instance();

	/**
	 * Gets a service.
	 *
	 * @param  string $id Unique identifier for the service
	 * @return mixed
	 */
	static public function get($id);

	/**
	 * Get an array of all the defined services.
	 *
	 * @return array The full list of services, where the ID is the key
	 */
	public function getAll();
}
