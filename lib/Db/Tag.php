<?php

declare(strict_types=1);

/**
 * @copyright 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Mail\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getDisplayName()
 * @method void setNamsetDisplayName(string $displayNames)
 * @method string getImapKeyword()
 * @method void setImapKeyword(string $imapKeyword)
 * @method string getColor()
 * @method void setColor(string $color)
 */
class Tag extends Entity implements JsonSerializable {
	protected $userId;
	protected $displayName;
	protected $imapKeyword;
	protected $color;

	public function __construct() {
	}

	public function jsonSerialize() {
		return [
			'databaseId' => $this->getId(),
			'userId' => $this->getUserId(),
			'displayName' => $this->getDisplayName(),
			'imapKeyword' => $this->getImapKeyword(),
			'color' => $this->getColor(),
		];
	}
}
