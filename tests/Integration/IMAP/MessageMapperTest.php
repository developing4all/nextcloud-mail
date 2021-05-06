<?php

declare(strict_types=1);

/**
 * @author Anna Larch <anna.larch@nextcloud.com>
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

namespace OCA\Mail\Tests\Integration\IMAP;

use ChristophWurst\Nextcloud\Testing\TestCase;
use Horde_Imap_Client;
use Horde_Imap_Client_Exception;
use OC;
use OCA\Mail\Account;
use OCA\Mail\Contracts\IMailManager;
use OCA\Mail\IMAP\MessageMapper as ImapMessageMapper;
use OCA\Mail\Db\MessageMapper;
use OCA\Mail\Service\Sync\SyncService;
use OCA\Mail\Tests\Integration\Framework\ImapTest;
use OCA\Mail\Tests\Integration\Framework\ImapTestAccount;

class MessageMapperTest extends TestCase {
	use ImapTest,
		ImapTestAccount;

	public function setUp():void {
		parent::setUp();
	}

	public function testTagging() {
		// First, set up account and retrieve sync token
		$this->resetImapAccount();

		$account = $this->createTestAccount();
		/** @var SyncService $syncService */
		$syncService = OC::$server->query(SyncService::class);
		/** @var ImapMessageMapper $messageMapper */
		$imapMessageMapper = OC::$server->query(ImapMessageMapper::class);
		/** @var MessageMapper $messageMapper */
		$messageMapper = OC::$server->query(MessageMapper::class);
		/** @var IMailManager $mailManager */
		$mailManager = OC::$server->query(IMailManager::class);
		$mailBoxes = $mailManager->getMailboxes(new Account($account));
		$inbox = null;
		foreach ($mailBoxes as $mailBox) {
			if ($mailBox->getName() === 'INBOX') {
				$inbox = $mailBox;
				break;
			}
		}

		// Second, put a new message into the mailbox
		$message = $this->getMessageBuilder()
			->from('buffington@domain.tld')
			->to('user@domain.tld')
			->finish();
		$newUid = $this->saveMessage($inbox->getName(), $message, $account);

		// now we tag this message!
		try {
			$imapMessageMapper->addFlag($this->getClient($account), $mailBox, [$newUid], '$label1');
		} catch (Horde_Imap_Client_Exception $e) {
			$this->fail('Could not tag message');
		}

		// sync
		$syncService->syncMailbox(
			new Account($account),
			$inbox,
			Horde_Imap_Client::SYNC_NEWMSGSUIDS | Horde_Imap_Client::SYNC_FLAGSUIDS | Horde_Imap_Client::SYNC_VANISHEDUIDS,
			null,
			false
		);

		// Let's retrieve the DB to see if we have this tag!
		$messages = $messageMapper->findByUids($mailBox, [$newUid]);
		$related = $messageMapper->findRelatedData($messages, $account->getUserId());
		foreach ($related as $message) {
			$tags = $message->getTags();
			$this->assertEquals('$label1', $tags[0]->getImapLabel());
		}


		// now we untag this message!
		try {
			$imapMessageMapper->removeFlag($this->getClient($account), $mailBox, [$newUid], '$label1');
		} catch (Horde_Imap_Client_Exception $e) {
			$this->fail('Could not untag message');
		}

		// sync again
		$syncService->syncMailbox(
			new Account($account),
			$inbox,
			Horde_Imap_Client::SYNC_NEWMSGSUIDS | Horde_Imap_Client::SYNC_FLAGSUIDS | Horde_Imap_Client::SYNC_VANISHEDUIDS,
			null,
			true
		);

		$messages = $messageMapper->findByUids($mailBox, [$newUid]);
		$related = $messageMapper->findRelatedData($messages, $account->getUserId());
		foreach ($related as $message) {
			$tags = $message->getTags();
			$this->assertEmpty($tags);
		}
	}

	public function testGetFlagged() {
		// First, set up account and retrieve sync token
		$this->resetImapAccount();

		$account = $this->createTestAccount();
		/** @var ImapMessageMapper $messageMapper */
		$imapMessageMapper = OC::$server->query(ImapMessageMapper::class);
		/** @var IMailManager $mailManager */
		$mailManager = OC::$server->query(IMailManager::class);
		$mailBoxes = $mailManager->getMailboxes(new Account($account));
		$inbox = null;
		foreach ($mailBoxes as $mailBox) {
			if ($mailBox->getName() === 'INBOX') {
				$inbox = $mailBox;
				break;
			}
		}

		// Put a second new message into the mailbox
		$message = $this->getMessageBuilder()
			->from('buffington@domain.tld')
			->to('user@domain.tld')
			->finish();
		$newUid = $this->saveMessage($inbox->getName(), $message, $account);


		// Put another new message into the mailbox
		$message = $this->getMessageBuilder()
			->from('fluffington@domain.tld')
			->to('user@domain.tld')
			->finish();
		$newUid2 = $this->saveMessage($inbox->getName(), $message, $account);

		// Thirdly, create a message that will not be tagged
		$message = $this->getMessageBuilder()
			->from('scruffington@domain.tld')
			->to('user@domain.tld')
			->finish();
		$this->saveMessage($inbox->getName(), $message, $account);

		// now we tag this message with $label1
		try {
			$imapMessageMapper->addFlag($this->getClient($account), $mailBox, [$newUid], '$label1');
		} catch (Horde_Imap_Client_Exception $e) {
			$this->fail('Could not tag message');
		}


		// now we tag this and the previous message with $label2
		try {
			$imapMessageMapper->addFlag($this->getClient($account), $mailBox, [$newUid, $newUid2], '$label2');
		} catch (Horde_Imap_Client_Exception $e) {
			$this->fail('Could not tag message');
		}

		// test for labels
		$tagged = $imapMessageMapper->getFlagged($this->getClient($account), $mailBox, '$label1');
		$this->assertNotEmpty($tagged);
		// are the counts correct?
		$this->assertCount(1, $tagged);

		$tagged = $imapMessageMapper->getFlagged($this->getClient($account), $mailBox, '$label2');
		$this->assertNotEmpty($tagged);
		$this->assertCount(2, $tagged);

		// test for labels that wasn't set
		$tagged = $imapMessageMapper->getFlagged($this->getClient($account), $mailBox, '$notAvailable');
		$this->assertEmpty($tagged);

		// test for regular flag - recent
		$tagged = $imapMessageMapper->getFlagged($this->getClient($account), $mailBox, Horde_Imap_Client::FLAG_RECENT);
		$this->assertNotEmpty($tagged);
		// should return all messages
		$this->assertCount(3, $tagged);
	}

	public function tearDown(): void {
		$this->resetImapAccount();
	}
}
