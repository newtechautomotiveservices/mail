<?php

declare(strict_types=1);

namespace OCA\Mail\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version1100Date20210304143008 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		// @link https://github.com/nextcloud/mail/issues/4466
		// $messageTable = $schema->getTable('mail_messages');
		// $messageTable->addIndex(['thread_root_id'], 'mail_msgs_thread_root_id_index');

		// @link https://github.com/nextcloud/mail/issues/25
		if (!$schema->hasTable('mail_tags')) {
			$tagsTable = $schema->createTable('mail_tags');
			$tagsTable->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$tagsTable->addColumn('user_id', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$tagsTable->addColumn('imap_label', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$tagsTable->addColumn('display_name', 'string', [
				'notnull' => true,
				'length' => 128,
			]);
			// hex code plus transparency = #ffffffab
			$tagsTable->addColumn('color', 'string', [
				'notnull' => false,
				'length' => 9,
			]);
			$tagsTable->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('mail_message_tags')) {
			$tagsMessageTable = $schema->createTable('mail_message_tags');
			$tagsMessageTable->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$tagsMessageTable->addColumn('imap_message_id', 'string', [
				'notnull' => true,
				'length' => 1023,
			]);
			$tagsMessageTable->addColumn('tag_id', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$tagsMessageTable->setPrimaryKey(['id']);
		}
		return $schema;

	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// add default labels here?
		// if server doesn't support IMAP PERFLAGS move all flags tagged as important to tags table here
		// reset cache
		// rewrite $important tag for each message after cache has been resynced
	}
}
