<?php

namespace Message\Cog\DB;

use Message\Cog\DB\Adapter\ConnectionInterface;

/**
* Query class
*
* Responsible for turning SQL queries into Result datasets.
*/
class Query implements QueryableInterface
{
	protected $_connection;
	protected $_params;
	protected $_query;
	protected $_parsedQuery;

	protected $_typeTokens = array(
		's'	=> 'string',
		'i'	=> 'integer',
		'f' => 'float',
		'd'	=> 'datetime',
		'b'	=> 'boolean',
	);

	const TOKEN_REGEX = '/((\:[a-zA-Z0-9_\-\.]*)\??([a-z]*)?)|(\?([a-z]*))/us';

	public function __construct(ConnectionInterface $connection)
	{
		$this->setConnection($connection);
	}

	/**
	 * Runs a query against the data store
	 *
	 * Params to be interpolated in the query can be passed in via the second
	 * parameter. See readme for more info.
	 *
	 * @param  string $query  The query to run against the datastore.
	 * @param  mixed  $params Parameters to be interpolated in the query.
	 * @return Result         The data generated by the query.
	 */
	public function run($query, $params = array())
	{
		$this->_query  = $query;
		$this->_params = (array)$params;

		$this->_parseParams();
		$result = $this->_connection->query($this->_parsedQuery);

		if($result === false) {
			throw new Exception($this->_connection->getLastError(), $this->_query);
		}

		return new Result($result, clone $this);
	}

	/**
	 * Set the connection to use for this query. Useful if you want to run the
	 * same query against multiple connections.
	 *
	 * @param ConnectionInterface $connection
	 */
	public function setConnection(ConnectionInterface $connection)
	{
		$this->_connection = $connection;
	}

	/**
	 * Get the parsed query in its current state
	 *
	 * @return string       The query
	 */
	public function getParsedQuery()
	{
		return $this->_parsedQuery;
	}

	/**
	 * Replaces placeholders in the query with safe, escaped parameters. Used
	 * to prevent SQL injection attacks.
	 *
	 * @todo Move this into it's own class.
	 *
	 * @return boolean Indicates if any parsing had to be performed.
	 */
	private function _parseParams()
	{
		if(!count($this->_params)) {
			$this->_parsedQuery = $this->_query;

			return false;
		}

		$counter = 0;

		// PHP 5.3 hack
		$connection = $this->_connection;
		$fields     = $this->_params;
		$types      = $this->_typeTokens;
		$self       = $this;
		$query 		= $this->_query;

		$this->_parsedQuery = preg_replace_callback(
			self::TOKEN_REGEX,
			function($matches) use($self, $fields, $types, &$counter, $connection, $query) {

				// parse and validate the token
				$full  = $matches[0];
				$param = substr($full, 0, 1) == ':' ? substr($matches[2], 1) : false; // The var after the colon.
				$flagIndex = $param === false ? 5 : 3;
				$flags = $matches[$flagIndex] ?: 'sn'; // data casting flags
				$type  = str_replace('n', '', $flags, $useNull);
				$type  = str_replace('j', '', $type, $useJoin);

				if(!isset($types[$type])) {
					throw new Exception(sprintf('Unknown type `%s` in token `%s`', $type, $full), $query);
				}

				// decide what data to use
				$data = null;
				if($param !== false && isset($fields[$param])) {
					$data = $fields[$param];
				} else if($param === false  && $counter < count($fields)) {
					$data = array_slice($fields, $counter, 1);
					$data = reset($data);
				}
				$counter++;

				if ($useJoin) {
					if (!is_array($data)) {
						throw new Exception(
							sprintf('Cannot use join in token `%s` as it is not an array.', $full),
							$query
						);
					}

					foreach ($data as $key => $value) {
						$data[$key] = $self->castValue($value, $type, $useNull);
					}

					return implode(', ', $data);
				}

				return $self->castValue($data, $type, $useNull);
			},
		$this->_query);

		return true;
	}

	public function castValue($value, $type, $useNull)
	{
		// check for nullness
		if (is_null($value) && $useNull) {
			return 'NULL';
		}

		if ($value instanceof \DateTime) {
			$value = $value->getTimestamp();
		}

		// If a type is set to date then cast it to an int
		if ($type == 'd') {
		    $safe = (int) $value;
		} else {
			// Don't cast type if type is integer and value starts with @ (as it is an ID variable)
			if (!('i' === $type && '@' === substr($value, 0, 1))) {
				settype($value, $this->_typeTokens[$type]);
			}
			$safe = $this->_connection->escape($value);
		}
		// Floats are quotes to support all locales.
		// See: http://stackoverflow.com/questions/2030684/which-mysql-data-types-should-i-not-be-quoting-during-an-insert"
		if ($type == 's' || $type == 'f') {
			$safe = "'".$safe."'";
		}

		if ('b' === $type) {
			$safe = $value ? 1 : 0;
		}

		return $safe;
	}
}