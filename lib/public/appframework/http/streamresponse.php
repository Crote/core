<?php
/**
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @author Lukas Reschke <lukas@owncloud.com>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCP\AppFramework\Http;

use OCP\AppFramework\Http;

/**
 * Class StreamResponse
 *
 * @package OCP\AppFramework\Http
 */
class StreamResponse extends Response implements ICallbackResponse {
	/** @var string */
	private $filePath;

	/**
	 * @param string $filePath the path to the file which should be streamed
	 */
	public function __construct ($filePath) {
		$this->filePath = $filePath;
	}


	/**
	 * Streams the file using readfile
	 *
	 * @param IOutput $output a small wrapper that handles output
	 */
	public function callback (IOutput $output) {
		// handle caching
		if ($output->getHttpResponseCode() !== Http::STATUS_NOT_MODIFIED) {
			if (!file_exists($this->filePath)) {
				$output->setHttpResponseCode(Http::STATUS_NOT_FOUND);
			} elseif ($output->setReadfile($this->filePath) === false) {
				$output->setHttpResponseCode(Http::STATUS_BAD_REQUEST);
			}
		}
	}

}
