<?php

/**
 * Add page_uid and change_identity_id columns for stable page identity,
 * identity-aware user tracking, and Content_Tagger integration.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @category Horde
 * @license  http://www.horde.org/licenses/gpl GPL
 * @package  Wicked
 */
class WickedPageUidAndTagging extends Horde_Db_Migration_Base
{
    /**
     * Upgrade.
     */
    public function up()
    {
        $this->addColumn('wicked_pages', 'page_uid', 'string', [
            'limit' => 255,
            'null' => false,
            'default' => '',
        ]);
        $this->addColumn('wicked_pages', 'change_identity_id', 'string', [
            'limit' => 255,
            'null' => true,
        ]);

        $this->addColumn('wicked_history', 'page_uid', 'string', [
            'limit' => 255,
            'null' => false,
            'default' => '',
        ]);
        $this->addColumn('wicked_history', 'change_identity_id', 'string', [
            'limit' => 255,
            'null' => true,
        ]);

        $this->addColumn('wicked_attachments', 'change_identity_id', 'string', [
            'limit' => 255,
            'null' => true,
        ]);

        $this->addColumn('wicked_attachment_history', 'change_identity_id', 'string', [
            'limit' => 255,
            'null' => true,
        ]);

        $rows = $this->select('SELECT page_id FROM wicked_pages');
        foreach ($rows as $row) {
            $uid = $this->_generateUuid();
            $this->update(
                'UPDATE wicked_pages SET page_uid = ? WHERE page_id = ?',
                [$uid, $row['page_id']]
            );
            $this->update(
                'UPDATE wicked_history SET page_uid = ? WHERE page_id = ?',
                [$uid, $row['page_id']]
            );
        }

        $this->addIndex('wicked_pages', ['page_uid'], ['unique' => true]);
        $this->addIndex('wicked_history', ['page_uid']);
    }

    /**
     * Downgrade.
     */
    public function down()
    {
        $this->removeIndex('wicked_history', ['page_uid']);
        $this->removeIndex('wicked_pages', ['page_uid']);
        $this->removeColumn('wicked_attachment_history', 'change_identity_id');
        $this->removeColumn('wicked_attachments', 'change_identity_id');
        $this->removeColumn('wicked_history', 'change_identity_id');
        $this->removeColumn('wicked_history', 'page_uid');
        $this->removeColumn('wicked_pages', 'change_identity_id');
        $this->removeColumn('wicked_pages', 'page_uid');
    }

    /**
     * Generate a UUID v4 string without external dependencies.
     */
    private function _generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
