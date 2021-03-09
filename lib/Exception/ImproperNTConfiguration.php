<?php

namespace OCA\Mail\Exception;

use Exception;

class ImproperNTConfiguration extends Exception {
	protected $message = 'Missing or invalid Newtech Configuration.';
}
