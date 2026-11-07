<?php

/*
 * ITFlow - Database update to version 3.5.7 (from 3.5.6)
 * Included by admin/database_updates.php - do not access directly
 *
 * settings.config_client_portal_footer_text - the text the client portal's footer shows
 * next to the company name (Admin > Settings > Client Portal, functions/portal.php).
 * Empty, as it arrives, the footer stays as it was: "Powered by ITFlow" unless white-label
 * is on. Each install fills in its own wording.
 *
 * Re-runnable: skipped where the column is already there.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column_exists = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings' AND COLUMN_NAME = 'config_client_portal_footer_text' LIMIT 1");

    if (!$itflow_column_exists || mysqli_num_rows($itflow_column_exists) === 0) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD COLUMN `config_client_portal_footer_text` varchar(200) NOT NULL DEFAULT '' AFTER `config_client_portal_signup`");
    }

    unset($itflow_column_exists);
