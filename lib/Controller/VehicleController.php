<?php

declare(strict_types=1);

/**
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Jakob Sack <mail@jakobsack.de>
 *
 * Mail
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

namespace OCA\Mail\Controller;

use OCA\Mail\Contracts\IAvatarService;
use OCA\Mail\Http\AvatarDownloadResponse;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use Newtech\NTAPIBridge\NTAPI\Inventory\Vehicles;
use Newtech\NTAPIBridge\NTAPI\Dealers\Template;
use OCA\NTSSO\Controller\NTUser;
use OCP\IConfig;
use OCP\IRequest;

class VehicleController extends Controller {
	/** @var string */
	private $uid;
	/** @var NTUser */
	private $ntuser;

	public function __construct(
        string $appName,
        IRequest $request,
        string $UserId,
        IConfig $config,
        NTUser $user
    ) {
		parent::__construct($appName, $request);
		$this->uid = $UserId;
		$this->ntuser = $ntuser;
	}

}
