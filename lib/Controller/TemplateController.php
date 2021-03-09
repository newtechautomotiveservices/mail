<?php

declare(strict_types=1);

/**
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Christoph Wurst <wurst.christoph@gmail.com>
 * @author Jan-Christoph Borchardt <hey@jancborchardt.net>
 * @author Lukas Reschke <lukas@owncloud.com>
 * @author Robin McCorkell <rmccorkell@karoshi.org.uk>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Matthias Rella <mrella@pisys.eu>
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

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use Newtech\NTAPIBridge\NTAPI\Inventory\Vehicles;
use Newtech\NTAPIBridge\NTAPI\Dealers\Template;
use OCA\NTSSO\Controller\NTUser;
use OCP\IConfig;
use OCP\IRequest;

class TemplateController extends Controller {

	/** @var string */
	private $uid;
	/** @var NTUser */
	private $ntuser;

	public function __construct(
        string $appName,
        IRequest $request,
        string $UserId,
        IConfig $config,
        NTUser $ntuser
    ) {
		parent::__construct($appName, $request);
		$this->uid = $UserId;
		$this->ntuser = $ntuser;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @return JSONResponse renders the index page
	 */
	public function all($type) {
		$profile = $this->ntuser->getProfile();
		try {
			$templates = Template::all(["type" => $type, "store_number" => $profile->store->store_number]);
			return $templates;
		} catch (Exception $e) {
			abort(500, $e);
		}
	}

	/**
	 * @PublicPage
	 * @UseSession
	 * @CSRFRequired
	 *
	 *
	 */
	public function render($id, $type, $vehicle_vin, $vehicle_stockNumber) {
		$profile = $this->ntuser->getProfile();
		try {
			$client = new \GuzzleHttp\Client();
			$response = $client->request('POST', getenv('TEMPLATE_API_ENDPOINT') . "/api/templates/export", [
				'headers' => [
					'Authorization' => 'Bearer ' .getenv('TEMPLATE_API_KEY'),
					'Accept'    => 'application/json',
					'Content-Type'    => 'application/json',
				],
				'json' => [
					"storeNumber" => $profile->store->store_number,
					"vehicle" => [
						"stockNumber" => $vehicle_stockNumber,
						"vin" => $vehicle_vin
					],
					"template" => [
						"type" => $type,
						"id" => $id,
						"output" => "html"
					]
				],
				'query' => [
					"replaceArrays" => true,
					"ignoreEmpty" => false,
					"ignoreNull" => false
				]
			]);
			$data = json_decode($response->getBody()->getContents(), true);
			return $data;
		} catch (Exception $e) {
			abort(500, $e);
		}
	}

}
