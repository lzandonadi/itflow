<?php

/*
 * ITFlow - Database update to version 3.5.6 (from 3.5.5)
 * Included by admin/database_updates.php - do not access directly
 *
 * settings.config_language - the language ITFlow is shown in (Admin > Settings >
 * Localization, functions/i18n.php). en_US, as everything was before, until an
 * admin picks another; the number and currency format stays company_locale.
 *
 * Re-runnable: skipped where the column is already there.
 */

defined('FROM_DB_UPDATER') || die("Direct file access is not allowed");

    $itflow_column_exists = mysqli_query($mysqli, "SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings' AND COLUMN_NAME = 'config_language' LIMIT 1");

    if (!$itflow_column_exists || mysqli_num_rows($itflow_column_exists) === 0) {
        mysqli_query($mysqli, "ALTER TABLE `settings` ADD COLUMN `config_language` varchar(10) NOT NULL DEFAULT 'en_US' AFTER `config_timezone`");
    }

    unset($itflow_column_exists);
