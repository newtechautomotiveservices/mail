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

use OCP\IDBConnection;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\AppFramework\Utility\ITimeFactory;

/**
 * @template-extends QBMapper<Message>
 */
class TagMapper extends QBMapper {

	/** @var ITimeFactory */
	private $timeFactory;

	public function __construct(IDBConnection $db,
								ITimeFactory $timeFactory) {
		parent::__construct($db, 'mail_tags');
		$this->timeFactory = $timeFactory;
	}

	// public function getTagsForMessage(Message $message): array {
	// 	$qb1 = $this->db->getQueryBuilder();
	// 	$qb2 = $this->db->getQueryBuilder();
	// 	$qb2->select('id')
	// 		->from('mail_message_tags')
	// 		->where('imap_message_id', $qb1->createNamedParameter($message->getMessageId()));

	// 	$qb1->select('*')
	// 		->from($this->getTableName())
	// 		->where(
	// 			$qb1->expr()->in('id', $qb1->createFunction($qb2->getSQL()), IQueryBuilder::PARAM_INT_ARRAY)
	// 		);

	// 	return $this->findEntities($qb1);
	// }

	// public function getTagsForUser(string $userId) {
	// 	$qb = $this->db->getQueryBuilder();
	// 	$qb->select()
	// 		->from('mail_tags')
	// 		->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
	// 	$qb->execute();

	// 	return $this->findEntities($qb);
	// }

	public function getTagByImapLabel(string $imapLabel): Entity {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('mail_tags')
			->where($qb->expr()->eq('imap_keyword', $qb->createNamedParameter($imapLabel)));
		$result = $this->findEntity($qb);
		return $result;
	}

	public function tagMessage(Tag $tag, string $messageId) {
		/** @var Tag $exists */
		$exists = $this->getTagByImapLabel($tag->getImapKeyword());
		if ($exists === false) {
			$tag = $this->insert($tag);
		} else {
			$tag->setId($exists->getId());
		}
		$qb = $this->db->getQueryBuilder();
		$qb->insert('mail_message_tags');
		$qb->setValue('message_id', $messageId);
		$qb->setValue('tag_id', $tag->getId());
		$qb->execute();
	}
}
