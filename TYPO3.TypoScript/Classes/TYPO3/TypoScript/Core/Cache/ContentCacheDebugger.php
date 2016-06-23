<?php
namespace TYPO3\TypoScript\Core\Cache;

use TYPO3\Flow\Annotations as Flow;

/**
 *
 * @Flow\Scope("singleton")
 */
class ContentCacheDebugger {

	const DEBUG_GET_PARAMETER = 'debug';

	/**
	 * @Flow\InjectConfiguration(path="contentCacheDebugger")
	 * @var array
	 */
	protected $settings;


	/**
	 * Overrides the debug settings
	 *
	 * @param array $settings
	 * @return void
	 */
	public function setSettings($settings) {

		$settings['enabled'] = $settings['enabled'] && isset($_GET[self::DEBUG_GET_PARAMETER]);
		$this->settings = $settings;
	}


	/**
	 * If debug enabled, this method, wraps the a given $entry with $identifier information as comments.
	 *
	 * @param $identifier
	 * @param $entry
	 *
	 * @return string
	 */
	public function wrapCacheEntry($identifier, $entry) {

		//abort, if debugging disabled OR 'HTTP/...' in cache segment
		if (!$this->settings['enabled'] || substr($entry, 0, 5) === 'HTTP/') return $entry;

		return sprintf('
		<!-- START: %1$s -->
		%2$s
		<!-- END: %1$s -->
		', $identifier, $entry);
	}

}
