<?php

/**
 * Add per-page format column and external source tracking table.
 *
 * page_format (NULL = use global config) enables per-page wiki format
 * overrides. The wicked_page_sources table stores the mapping between
 * pages and external source files (e.g. a markdown file in a GitHub repo).
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
class WickedPageFormatAndSources extends Horde_Db_Migration_Base
{
    public function up()
    {
        $this->addColumn('wicked_pages', 'page_format', 'string', [
            'limit' => 64,
            'null' => true,
        ]);
        $this->addColumn('wicked_history', 'page_format', 'string', [
            'limit' => 64,
            'null' => true,
        ]);

        $tableList = $this->tables();

        if (!in_array('wicked_page_sources', $tableList)) {
            $t = $this->createTable('wicked_page_sources', ['autoincrementKey' => false]);
            $t->column('page_uid', 'string', ['limit' => 255, 'null' => false]);
            $t->column('source_type', 'string', ['limit' => 32, 'null' => false]);
            $t->column('repository', 'string', ['limit' => 255, 'null' => false]);
            $t->column('file_path', 'string', ['limit' => 512, 'null' => false]);
            $t->column('ref', 'string', ['limit' => 255, 'null' => false, 'default' => 'main']);
            $t->column('last_synced_at', 'datetime', ['null' => true]);
            $t->column('last_commit_sha', 'string', ['limit' => 64, 'null' => true]);
            $t->primaryKey(['page_uid']);
            $t->end();

            $this->addIndex('wicked_page_sources', ['repository'], [
                'name' => 'wicked_page_sources_repo',
            ]);
        }
    }

    public function down()
    {
        $this->dropTable('wicked_page_sources');
        $this->removeColumn('wicked_history', 'page_format');
        $this->removeColumn('wicked_pages', 'page_format');
    }
}
