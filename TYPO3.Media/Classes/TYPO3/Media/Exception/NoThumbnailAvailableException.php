<?php
namespace TYPO3\Media\Exception;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "TYPO3.Media".           *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3 of the   *
 * License, or (at your option) any later version.                        *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\Media\Exception;

/**
 * A TYPO3.Media exception for the thumbnail service if the given asset is not able to generate a thumbnail.
 *
 * @api
 */
class NoThumbnailAvailableException extends Exception
{
}
