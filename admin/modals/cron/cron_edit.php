<?php

require_once '../../includes/modal_header.php'; // admin/includes - admins only
require_once '../../../includes/cron_jobs.php';

$cron_job_id = intval($_GET['id']);

$row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT cron_job_daily_at, cron_job_enabled, cron_job_interval_minutes, cron_job_name,
    cron_job_schedule FROM cron_jobs WHERE cron_job_id = $cron_job_id LIMIT 1"));

$registry = cronJobRegistryByName();
$job = $registry[$row['cron_job_name']] ?? null;
$cron_job_interval_safe = ($job['interval_safe'] ?? true);

$cron_job_name = escapeHtml($row['cron_job_name']);
$cron_job_label = escapeHtml(isset($job['label']) ? __($job['label']) : $row['cron_job_name']); // i18n-dynamic - includes/cron_jobs.php
$cron_job_enabled = intval($row['cron_job_enabled']);
$cron_job_schedule = escapeHtml($row['cron_job_schedule']);
$cron_job_interval_minutes = intval($row['cron_job_interval_minutes']);
$cron_job_daily_at = escapeHtml(substr((string)$row['cron_job_daily_at'], 0, 5));

if (empty($cron_job_daily_at)) {
    $cron_job_daily_at = '03:00';
}

// Generate the HTML form content using output buffering.
ob_start();
?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-clock me-2"></i><?= __('Editing:') ?> <strong><?= $cron_job_label ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="cron_job_id" value="<?= $cron_job_id ?>">
    <div class="modal-body">

        <div class="mb-3">
            <div class="form-check form-switch">
                <input type="checkbox" class="form-check-input" name="enabled" value="1" id="cronJobEnabledSwitch" <?= $cron_job_enabled == 1 ? 'checked' : '' ?>>
                <label class="form-check-label" for="cronJobEnabledSwitch"><?= __('Enabled') ?></label>
            </div>
            <small class="text-muted"><?= __('A disabled job never runs on its schedule, but Run Now still works.') ?></small>
        </div>

        <div class="mb-3">
            <label><?= __('Schedule') ?></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                <select class="form-select" name="schedule" id="cronJobSchedule">
                    <?php if ($cron_job_interval_safe) { ?>
                        <option value="Interval" <?= $cron_job_schedule === 'Interval' ? 'selected' : '' ?>><?= __('Every so many minutes') ?></option>
                    <?php } ?>
                    <option value="Daily" <?= ($cron_job_schedule === 'Daily' || !$cron_job_interval_safe) ? 'selected' : '' ?>><?= __('Once a day') ?></option>
                </select>
            </div>
            <?php if (!$cron_job_interval_safe) { ?>
                <small class="text-muted"><?= __('This job\'s work repeats if it runs twice in one day, so it only runs on the daily schedule.') ?></small>
            <?php } ?>
        </div>

        <div class="mb-3" id="cronJobIntervalGroup">
            <label><?= __('Run every') ?></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-redo"></i></span>
                <input type="number" class="form-control" name="interval_minutes" value="<?= $cron_job_interval_minutes ?>" min="1" max="1440">
                    <span class="input-group-text"><?= __('minutes') ?></span>
            </div>
            <small class="text-muted"><?= __('Cron wakes once a minute, so 1 is as often as anything can run.') ?></small>
        </div>

        <div class="mb-3" id="cronJobDailyGroup">
            <label><?= __('Run at') ?></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-clock"></i></span>
                <input type="time" class="form-control" name="daily_at" value="<?= $cron_job_daily_at ?>">
            </div>
            <small class="text-muted"><?= __('Your ITFlow timezone. A run missed because the server was off happens at the next opportunity instead of waiting a day.') ?></small>
        </div>

    </div>
    <div class="modal-footer">
        <button type="submit" name="edit_cron_job" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i><?= __('Save') ?></button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i><?= __('Cancel') ?></button>
    </div>
</form>

<script>
    function cronJobToggleScheduleFields() {
        var daily = document.getElementById('cronJobSchedule').value === 'Daily';
        document.getElementById('cronJobIntervalGroup').hidden = daily;
        document.getElementById('cronJobDailyGroup').hidden = !daily;
    }
    document.getElementById('cronJobSchedule').addEventListener('change', cronJobToggleScheduleFields);
    cronJobToggleScheduleFields();
</script>

<?php

require_once '../../../includes/modal_footer.php';
