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

		if(!$this->settings['enabled']) return $entry;

		//workaround: remove 'HTTP/1.1 200 OK' in first cache segment
		return sprintf('
		<!-- START: %s -->
		%s
		<!-- END: %s -->
		', $identifier, str_replace('HTTP/1.1 200 OK', '', $entry), $identifier);
	}

}
