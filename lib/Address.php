<?php

declare(strict_types=1);

/**
 * @copyright 2017 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2017 Christoph Wurst <christoph@winzerhof-wurst.at>
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
 *
 */

namespace OCA\Mail;

use Horde_Mail_Rfc822_Address;
use JsonSerializable;

/**
 * @psalm-immutable
 */
class Address implements JsonSerializable {
	public const TYPE_FROM = 0;
	public const TYPE_TO = 1;
	public const TYPE_CC = 2;
	public const TYPE_BCC = 3;

	/** @var Horde_Mail_Rfc822_Address */
	private $wrapped;

	private function __construct(Horde_Mail_Rfc822_Address $wrapped) {
		$this->wrapped = $wrapped;
	}

	public static function fromHorde(Horde_Mail_Rfc822_Address $horde): self {
		return new self($horde);
	}

	public static function fromRaw(string $label, string $email): self {
		$wrapped = new Horde_Mail_Rfc822_Address($email);
		// If no label is set we use the email
		if ($label !== $email) {
			$wrapped->personal = $label;
		}
		return new self($wrapped);
	}

	/**
	 * @return string|null
	 */
	public function getLabel(): ?string {
		$personal = $this->wrapped->personal;
		if ($personal === null) {
			// Fallback
			return $this->getEmail();
		}
		return $personal;
	}

	/**
	 * @return string|null
	 */
	public function getEmail(): ?string {
		$email = $this->wrapped->bare_address;
		if ($email === null) {
			return null;
		}
		// Lets make sure the e-mail is valid UTF-8 at all times
		return $this->valid_utf8_bytes($email);
	}

	/**
	 * @return Horde_Mail_Rfc822_Address
	 */
	public function toHorde(): Horde_Mail_Rfc822_Address {
		return $this->wrapped;
	}

	/**
	 * @return array
	 */
	public function jsonSerialize(): array {
		return [
			'label' => $this->getLabel(),
			'email' => $this->getEmail(),
		];
	}

	/**
	 * @param Address $object
	 * @return boolean
	 */
	public function equals($object): bool {
		return $this->getEmail() === $object->getEmail()
			&& $this->getLabel() === $object->getLabel();
	}

	private function valid_utf8_bytes($str)
	{
		$return = '';
		$length = strlen($str);
		$invalid = array_flip(array("\xEF\xBF\xBF" /* U-FFFF */, "\xEF\xBF\xBE" /* U-FFFE */));

		for ($i=0; $i < $length; $i++)
		{
			$c = ord($str[$o=$i]);

			if ($c < 0x80) $n=0; # 0bbbbbbb
			elseif (($c & 0xE0) === 0xC0) $n=1; # 110bbbbb
			elseif (($c & 0xF0) === 0xE0) $n=2; # 1110bbbb
			elseif (($c & 0xF8) === 0xF0) $n=3; # 11110bbb
			elseif (($c & 0xFC) === 0xF8) $n=4; # 111110bb
			else continue; # Does not match

			for ($j=++$n; --$j;) # n bytes matching 10bbbbbb follow ?
				if ((++$i === $length) || ((ord($str[$i]) & 0xC0) != 0x80))
					continue 2
			;

			$match = substr($str, $o, $n);

			if ($n === 3 && isset($invalid[$match])) # test invalid sequences
				continue;

			$return .= $match;
		}
		return $return;
	}
}
