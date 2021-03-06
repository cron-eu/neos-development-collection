<?php
namespace TYPO3\TypoScript\Core\Cache;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "TYPO3.TypoScript".      *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3 of the   *
 * License, or (at your option) any later version.                        *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Cache\CacheAwareInterface;
use TYPO3\TypoScript\Core\Runtime;
use TYPO3\TypoScript\Exception;

/**
 * Integrate the ContentCache into the TypoScript Runtime
 *
 * Holds cache related runtime state.
 */
class RuntimeContentCache
{
    /**
     * @var Runtime
     */
    protected $runtime;

    /**
     * @var boolean
     */
    protected $enableContentCache = false;

    /**
     * @var boolean
     */
    protected $inCacheEntryPoint = null;

    /**
     * @var boolean
     */
    protected $addCacheSegmentMarkersToPlaceholders = false;

    /**
     * Stack of cached segment metadata (lifetime)
     *
     * @var array
     */
    protected $cacheMetadata = array();

    /**
     * @Flow\Inject
     * @var \TYPO3\TypoScript\Core\Cache\ContentCache
     */
    protected $contentCache;

    /**
     * @param Runtime $runtime
     */
    public function __construct(Runtime $runtime)
    {
        $this->runtime = $runtime;
    }

    /**
     * Enter an evaluation
     *
     * Needs to be called right before evaluation of a path starts to check the cache mode and set internal state
     * like the cache entry point.
     *
     * @param array $configuration
     * @param string $typoScriptPath
     * @return array An evaluate context array that needs to be passed to subsequent calls to pass the current state
     * @throws \TYPO3\TypoScript\Exception
     */
    public function enter(array $configuration, $typoScriptPath)
    {
        $cacheForPathEnabled = isset($configuration['mode']) && $configuration['mode'] === 'cached';
        $cacheForPathDisabled = isset($configuration['mode']) && $configuration['mode'] === 'uncached';

        if ($cacheForPathDisabled && (!isset($configuration['context']) || $configuration['context'] === array())) {
            throw new Exception(sprintf('Missing @cache.context configuration for path "%s". An uncached segment must have one or more context variable names configured.', $typoScriptPath), 1395922119);
        }

        $currentPathIsEntryPoint = false;
        if ($this->enableContentCache && $cacheForPathEnabled) {
            if ($this->inCacheEntryPoint === null) {
                $this->inCacheEntryPoint = true;
                $currentPathIsEntryPoint = true;
            }
        }

        return array(
            'configuration' => $configuration,
            'typoScriptPath' => $typoScriptPath,
            'cacheForPathEnabled' => $cacheForPathEnabled,
            'cacheForPathDisabled' => $cacheForPathDisabled,
            'currentPathIsEntryPoint' => $currentPathIsEntryPoint
        );
    }

    /**
     * Check for cached evaluation and or collect metadata for evaluation
     *
     * Try to get a cached segment for the current path and return that with all uncached segments evaluated if it
     * exists. Otherwise metadata for the cache lifetime is collected (if configured) for nested evaluations (to find the
     * minimum maximumLifetime).
     *
     * @param array $evaluateContext The current evaluation context
     * @param object $tsObject The current TypoScript object (for "this" in evaluations)
     * @return array Cache hit state as boolean and value as mixed
     */
    public function preEvaluate(array &$evaluateContext, $tsObject)
    {
        if ($this->enableContentCache) {
            if ($evaluateContext['cacheForPathEnabled']) {
                $evaluateContext['cacheIdentifierValues'] = $this->buildCacheIdentifierValues($evaluateContext['configuration'], $evaluateContext['typoScriptPath'], $tsObject);

                $self = $this;
                $segment = $this->contentCache->getCachedSegment(function ($command, $unserializedContext) use ($self) {
                    if (strpos($command, 'eval=') === 0) {
                        $path = substr($command, 5);
                        $result = $self->evaluateUncached($path, $unserializedContext);
                        return $result;
                    } else {
                        throw new Exception(sprintf('Unknown uncached command "%s"', $command), 1392837596);
                    }
                }, $evaluateContext['typoScriptPath'], $evaluateContext['cacheIdentifierValues'], $this->addCacheSegmentMarkersToPlaceholders);
                if ($segment !== false) {
                    return array(true, $segment);
                } else {
                    $this->addCacheSegmentMarkersToPlaceholders = true;
                }

                $this->cacheMetadata[] = array(
                    'lifetime' => null
                );
            }

            if (isset($evaluateContext['configuration']['maximumLifetime'])) {
                $maximumLifetime = $this->runtime->evaluate($evaluateContext['typoScriptPath'] . '/__meta/cache/maximumLifetime', $tsObject);

                if ($maximumLifetime !== null && $this->cacheMetadata !== array()) {
                    $cacheMetadata = &$this->cacheMetadata[count($this->cacheMetadata) - 1];
                    $cacheMetadata['lifetime'] = $cacheMetadata['lifetime'] !== null ? min($cacheMetadata['lifetime'], $maximumLifetime) : $maximumLifetime;
                }
            }
        }
        return array(false, null);
    }

    /**
     * Post process output for caching information
     *
     * The content cache stores cache segments with markers inside the generated content. This method creates cache
     * segments and will process the final outer result (currentPathIsEntryPoint) to remove all cache markers and
     * store cache entries.
     *
     * @param array $evaluateContext The current evaluation context
     * @param object $tsObject The current TypoScript object (for "this" in evaluations)
     * @param mixed $output The generated output after caching information was removed
     * @return mixed The post-processed output with cache segment markers or cleaned for the entry point
     */
    public function postProcess(array $evaluateContext, $tsObject, $output)
    {
        if ($this->enableContentCache && $evaluateContext['cacheForPathEnabled']) {
            $cacheTags = $this->buildCacheTags($evaluateContext['configuration'], $evaluateContext['typoScriptPath'], $tsObject);
            $cacheMetadata = array_pop($this->cacheMetadata);
            $output = $this->contentCache->createCacheSegment($output, $evaluateContext['typoScriptPath'], $evaluateContext['cacheIdentifierValues'], $cacheTags, $cacheMetadata['lifetime']);
        } elseif ($this->enableContentCache && $evaluateContext['cacheForPathDisabled'] && $this->inCacheEntryPoint) {
            $contextArray = $this->runtime->getCurrentContext();
            if (isset($evaluateContext['configuration']['context'])) {
                $contextVariables = array();
                foreach ($evaluateContext['configuration']['context'] as $contextVariableName) {
                    if (isset($contextArray[$contextVariableName])) {
                        $contextVariables[$contextVariableName] = $contextArray[$contextVariableName];
                    } else {
                        $contextVariables[$contextVariableName] = null;
                    }
                }
            } else {
                $contextVariables = $contextArray;
            }
            $output = $this->contentCache->createUncachedSegment($output, $evaluateContext['typoScriptPath'], $contextVariables);
        }

        if ($evaluateContext['cacheForPathEnabled'] && $evaluateContext['currentPathIsEntryPoint']) {
            $output = $this->contentCache->processCacheSegments($output, $this->enableContentCache);
            $this->inCacheEntryPoint = null;
            $this->addCacheSegmentMarkersToPlaceholders = false;
        }

        return $output;
    }

    /**
     * Leave the evaluation of a path
     *
     * Has to be called in the same function calling enter() for every return path.
     *
     * @param array $evaluateContext The current evaluation context
     * @return void
     */
    public function leave(array $evaluateContext)
    {
        if ($evaluateContext['currentPathIsEntryPoint']) {
            $this->inCacheEntryPoint = null;
        }
    }

    /**
     * Evaluate a TypoScript path with a given context without content caching
     *
     * This is used to render uncached segments "out of band" in getCachedSegment of ContentCache.
     *
     * @param string $path
     * @param array $contextArray
     * @return mixed
     *
     * TODO Find another way of disabling the cache (especially to allow cached content inside uncached content)
     */
    public function evaluateUncached($path, array $contextArray)
    {
        $previousEnableContentCache = $this->enableContentCache;
        $this->enableContentCache = false;
        $this->runtime->pushContextArray($contextArray);
        $result = $this->runtime->evaluate($path);
        $this->runtime->popContext();
        $this->enableContentCache = $previousEnableContentCache;
        return $result;
    }

    /**
     * Builds an array of additional key / values which must go into the calculation of the cache entry identifier for
     * a cached content segment.
     *
     * @param array $configuration
     * @param string $typoScriptPath
     * @param object $tsObject The actual TypoScript object
     * @return array
     */
    protected function buildCacheIdentifierValues($configuration, $typoScriptPath, $tsObject)
    {
        $cacheIdentifierValues = array();
        if (isset($configuration['entryIdentifier'])) {
            if (isset($configuration['entryIdentifier']['__objectType'])) {
                $cacheIdentifierValues = $this->runtime->evaluate($typoScriptPath . '/__meta/cache/entryIdentifier', $tsObject);
            } else {
                $cacheIdentifierValues = $this->runtime->evaluate($typoScriptPath . '/__meta/cache/entryIdentifier<TYPO3.TypoScript:GlobalCacheIdentifiers>', $tsObject);
            }
        } else {
            foreach ($this->runtime->getCurrentContext() as $key => $value) {
                if (is_string($value) || is_bool($value) || is_integer($value) || $value instanceof CacheAwareInterface) {
                    $cacheIdentifierValues[$key] = $value;
                }
            }
        }
        return $cacheIdentifierValues;
    }

    /**
     * Builds an array of string which must be used as tags for the cache entry identifier of a specific cached content segment.
     *
     * @param array $configuration
     * @param string $typoScriptPath
     * @param object $tsObject The actual TypoScript object
     * @return array
     */
    protected function buildCacheTags($configuration, $typoScriptPath, $tsObject)
    {
        $cacheTags = array();
        if (isset($configuration['entryTags'])) {
            foreach ($configuration['entryTags'] as $tagKey => $tagValue) {
                $tagValue = $this->runtime->evaluate($typoScriptPath . '/__meta/cache/entryTags/' . $tagKey, $tsObject);
                if (is_array($tagValue)) {
                    $cacheTags = array_merge($cacheTags, $tagValue);
                } elseif ((string)$tagValue !== '') {
                    $cacheTags[] = $tagValue;
                }
            }
        } else {
            $cacheTags = array(ContentCache::TAG_EVERYTHING);
        }
        return $cacheTags;
    }

    /**
     * @param boolean $enableContentCache
     * @return void
     */
    public function setEnableContentCache($enableContentCache)
    {
        $this->enableContentCache = $enableContentCache;
    }

    /**
     * @return boolean
     */
    public function getEnableContentCache()
    {
        return $this->enableContentCache;
    }
}
