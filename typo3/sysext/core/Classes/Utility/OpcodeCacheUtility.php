<?php
namespace TYPO3\CMS\Core\Utility;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

/**
 * Class with helper functions for clearing the PHP opcache.
 * It auto detects the opcache system and invalidates/resets it.
 * http://forge.typo3.org/issues/55252
 * Supported opcaches are: OPcache >= 7.0 (PHP 5.5), WinCache, XCache >= 3.0.1
 *
 * @author Alexander Opitz <opitz@pluspol-interactive.de>
 */
class OpcodeCacheUtility {

	/**
	 * All supported cache types
	 * @var array|null
	 */
	static protected $supportedCaches = NULL;

	/**
	 * Holds all currently active caches
	 * @var array|null
	 */
	static protected $activeCaches = NULL;

	/**
	 * Initialize the cache properties
	 */
	static protected function initialize() {
		$xcVersion = phpversion('xcache');

		static::$supportedCaches = array(
			// The ZendOpcache aka OPcache since PHP 5.5
			// http://php.net/manual/de/book.opcache.php
			'OPcache' => array(
				'active' => extension_loaded('Zend OPcache') && ini_get('opcache.enable') === '1',
				'version' => phpversion('Zend OPcache'),
				'canReset' => TRUE, // opcache_reset() ... it seems that it doesn't reset for current run.
				// From documentation this function exists since first version (7.0.0) but from Changelog
				// this function exists since 7.0.2
				// http://pecl.php.net/package-changelog.php?package=ZendOpcache&release=7.0.2
				'canInvalidate' => function_exists('opcache_invalidate'),
				'error' => FALSE,
				'clearCallback' => function ($fileAbsPath) {
					if ($fileAbsPath !== NULL && function_exists('opcache_invalidate')) {
						opcache_invalidate($fileAbsPath);
					} else {
						opcache_reset();
					}
				}
			),

			// http://www.php.net/manual/de/book.wincache.php
			'WinCache' => array(
				'active' => extension_loaded('wincache') && ini_get('wincache.ocenabled') === '1',
				'version' => phpversion('wincache'),
				'canReset' => TRUE,
				'canInvalidate' => TRUE, // wincache_refresh_if_changed()
				'error' => FALSE,
				'clearCallback' => function ($fileAbsPath) {
					if ($fileAbsPath !== NULL) {
						wincache_refresh_if_changed(array($fileAbsPath));
					} else {
						// No argument means refreshing all.
						wincache_refresh_if_changed();
					}
				}
			),

			// http://xcache.lighttpd.net/
			'XCache' => array(
				'active' => extension_loaded('xcache'),
				'version' => $xcVersion,
				'canReset' => !ini_get('xcache.admin.enable_auth'), // xcache_clear_cache()
				'canInvalidate' => FALSE,
				'error' => FALSE,
				'clearCallback' => function ($fileAbsPath) {
					if (!ini_get('xcache.admin.enable_auth')) {
						xcache_clear_cache(XC_TYPE_PHP);
					}
				}
			),
		);

		static::$activeCaches = array();
		// Cache the active ones
		foreach (static::$supportedCaches as $opcodeCache => $properties) {
			if ($properties['active']) {
				static::$activeCaches[$opcodeCache] = $properties;
			}
		}
	}

	/**
	 * Gets the state of canInvalidate for given cache system.
	 *
	 * @param string $system The cache system to test (APC, ...)
	 *
	 * @return bool The calculated value from array or FALSE if cache system not exists.
	 * @internal Do not rely on this function. Will be removed if PHP5.4 is minimum requirement.
	 */
	static public function getCanInvalidate($system) {
		return isset(static::$supportedCaches[$system])
			? static::$supportedCaches[$system]['canInvalidate']
			: FALSE;
	}

	/**
	 * Clears a file from an opcache, if one exists.
	 *
	 * @param string|NULL $fileAbsPath The file as absolute path to be cleared or NULL to clear completely.
	 *
	 * @return void
	 */
	static public function clearAllActive($fileAbsPath = NULL) {
		foreach (static::getAllActive() as $properties) {
			$callback = $properties['clearCallback'];
			$callback($fileAbsPath);
		}
	}

	/**
	 * Returns all supported and active opcaches
	 *
	 * @return array Array filled with supported and active opcaches
	 */
	static public function getAllActive() {
		if (static::$activeCaches === NULL) {
			static::initialize();
		}
		return static::$activeCaches;
	}

}
