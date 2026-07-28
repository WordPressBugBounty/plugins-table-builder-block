<?php
/**
 * Generic singleton trait for per-class shared instances
 *
 * @package TableKit
 */

namespace TableBuilder\Traits;

/**
 * Generic singleton trait: gives any class a shared instance() accessor
 * keyed by class name, so subclasses don't each need to reimplement it.
 *
 * @package TableBuilder\Traits
 */
trait Singleton {
	/**
	 * Shared instances, keyed by class name.
	 *
	 * @var array
	 */
	private static $instances = array();

	/**
	 * Gets (and lazily creates) the shared instance of the using class.
	 *
	 * @return static The shared instance.
	 */
	public static function instance() {
		$class = get_called_class();
		if ( ! isset( self::$instances[ $class ] ) ) {
			self::$instances[ $class ] = new $class();
		}
		return self::$instances[ $class ];
	}
}
