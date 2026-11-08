<?php

/*
 * The fields of New Service and Edit Service - included by service_add.php
 * and service_edit.php, which set $form (the values, raw), $form_client_id
 * (the service's client, 0 when not picked yet) and $is_new. A new service
 * picks its client here when opened outside one; opened inside one it can
 * link what it depends on straight away. Afterwards that is done from the
 * service page.
 */

isset($mysqli) || die("Direct file access is not allowed");

$form_importance = in_array($form['service_importance'] ?? '', SERVICE_IMPORTANCE, true) ? $form['service_importance'] : 'Medium';
$form_interval = intval($form['service_review_interval'] ?? 0);

// Categories to suggest - the usual ones and whatever this client (or anyone) already uses
$category_suggestions = SERVICE_CATEGORY_SUGGESTIONS;
$sql = mysqli_query($mysqli, "SELECT DISTINCT service_category FROM services WHERE service_category <> ''" . ($form_client_id ? " AND service_client_id = $form_client_id" : '') . " ORDER BY service_category LIMIT 50");
while ($row = mysqli_fetch_row($sql)) {
    if (!in_array($row[0], $category_suggestions, true)) {
        $category_suggestions[] = $row[0];
    }
}

$importance_styles = [
    'High' => ['danger', 'fa-thermometer-full', __('The client cannot work without it')],
    'Medium' => ['warning', 'fa-thermometer-half', __('It hurts, but there is a way round it')],
    'Low' => ['secondary', 'fa-thermometer-quarter', __('An inconvenience')],
];

?>

<?php if ($is_new && !$form_client_id) { ?>
    <div class="mb-3">
        <label class="form-label"><?= __('Client') ?> <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-user"></i></span>
            <select class="form-select select2" name="client_id" required>
                <option value=""><?= __('- Pick a client -') ?></option>
                <?php
                $sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation FROM clients WHERE client_archived_at IS NULL " . clientScopeSql('clients.client_id') . " ORDER BY client_name ASC");
                while ($row = mysqli_fetch_assoc($sql)) { ?>
                    <option value="<?= intval($row['client_id']) ?>"<?= selectOptionClient($row['client_id'], $row['client_name'], $row['client_abbreviation'] ?? '') ?>><?= escapeHtml($row['client_name']) ?></option>
                <?php } ?>
            </select>
        </div>
    </div>
<?php } elseif ($is_new) { ?>
    <input type="hidden" name="client_id" value="<?= $form_client_id ?>">
<?php } ?>

<div class="row g-3 mb-3">
    <div class="col-md-7">
        <label class="form-label"><?= __('Name') ?> <strong class="text-danger">*</strong></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-concierge-bell"></i></span>
            <input type="text" class="form-control" name="name" placeholder="<?= escapeHtml(__('Email, Backup, Practice management, Phones...')) ?>" maxlength="200" value="<?= escapeHtml($form['service_name'] ?? '') ?>" required autofocus>
        </div>
    </div>
    <div class="col-md-5">
        <label class="form-label"><?= __('Category') ?></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-folder"></i></span>
            <input type="text" class="form-control" name="category" list="serviceCategorySuggestions" placeholder="<?= escapeHtml(__('Email, Backup, Network...')) ?>" maxlength="50" value="<?= escapeHtml($form['service_category'] ?? '') ?>">
        </div>
        <datalist id="serviceCategorySuggestions">
            <?php foreach ($category_suggestions as $suggestion) { ?>
                <option value="<?= escapeHtml($suggestion) ?>"></option>
            <?php } ?>
        </datalist>
    </div>
</div>

<div class="mb-3">
    <label class="form-label"><?= __('Description') ?></label>
    <div class="input-group">
        <span class="input-group-text"><i class="fas fa-fw fa-align-left"></i></span>
        <input type="text" class="form-control" name="description" placeholder="<?= escapeHtml(__('Microsoft 365 mail for all staff, Veeam to the NAS and offsite...')) ?>" maxlength="200" value="<?= escapeHtml($form['service_description'] ?? '') ?>">
    </div>
</div>

<div class="mb-3">
    <?= tagFormFieldHtml('service', $is_new ? [] : tagIdsForRecord('service', $form['service_id']), __('Managed, Client-run, Business hours only')) ?>
</div>

<div class="mb-3">
    <label class="form-label d-block"><?= __('Importance') ?> <span class="text-body-secondary small fw-normal"><?= __('- how bad is it when it is down?') ?></span></label>
    <div class="btn-group w-100 itflow-service-importance" role="group">
        <?php foreach ($importance_styles as $importance => [$tone, $icon, $hint]) { ?>
            <input type="radio" class="btn-check" name="importance" id="serviceImportance<?= $importance ?>" value="<?= $importance ?>" <?= $form_importance === $importance ? 'checked' : '' ?> autocomplete="off">
            <label class="btn btn-outline-<?= $tone ?> text-start" for="serviceImportance<?= $importance ?>">
                <i class="fas <?= $icon ?> me-1"></i><span class="fw-semibold"><?= $importance ?></span>
                <span class="d-none d-md-block small opacity-75"><?= $hint ?></span>
            </label>
        <?php } ?>
    </div>
</div>

<div class="mb-3">
    <label class="form-label"><?= __('Backup and recovery') ?></label>
    <textarea class="form-control" name="backup" rows="2" maxlength="500" placeholder="<?= escapeHtml(__('How it is backed up, where to, and how it comes back - and how long that takes')) ?>"><?= escapeHtml($form['service_backup'] ?? '') ?></textarea>
</div>

<div class="mb-3">
    <label class="form-label"><?= __('Notes') ?></label>
    <textarea class="form-control" name="notes" rows="4" placeholder="<?= escapeHtml(__('How it fits together, the known quirks, what to check first when it breaks')) ?>"><?= escapeHtml($form['service_notes'] ?? '') ?></textarea>
</div>

<div class="row g-3 <?= $is_new && $form_client_id ? 'mb-3' : 'mb-0' ?>">
    <div class="col-md-6">
        <label class="form-label"><?= __('Review it') ?></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-redo"></i></span>
            <select class="form-select select2" name="review_interval" id="serviceReviewInterval">
                <?php foreach (SERVICE_REVIEW_INTERVALS as $months => $label) { ?>
                    <option value="<?= $months ?>" <?= $form_interval === $months ? 'selected' : '' ?> data-icon="far <?= $months ? 'fa-calendar-check' : 'fa-calendar' ?>"><?= serviceReviewIntervalLabel($months) ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="form-text"><?= __('Check it still matches what is out there - a QBR is a good time.') ?></div>
    </div>
    <div class="col-md-6">
        <label class="form-label"><?= __('Next review') ?></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-fw fa-calendar-check"></i></span>
            <input type="date" class="form-control" name="review_due" id="serviceReviewDue" value="<?= escapeHtml($form['service_review_due'] ?? '') ?>">
        </div>
        <?php if (!empty($form['service_reviewed_at'])) { ?>
            <div class="form-text"><?= escapeHtml(__('Last reviewed %s.', formatDate($form['service_reviewed_at'], 'date'))) ?></div>
        <?php } else { ?>
            <div class="form-text"><?= __('Leave it empty to follow the schedule.') ?></div>
        <?php } ?>
    </div>
</div>

<?php if ($is_new && $form_client_id) {
    // What it depends on - the client's own, unarchived records, the kinds this agent may link
    $pickers = [];
    foreach (SERVICE_LINK_TYPES as $type) {
        if (!linkCanEdit('service', $type)) {
            continue;
        }
        $candidates = linkCandidates('service', 0, $type, $form_client_id);
        if ($candidates) {
            $pickers[$type] = $candidates;
        }
    }
    $picker_hints = ['asset' => __('The servers, firewalls, NAS it runs on'), 'credential' => __('Admin consoles, portals'), 'domain' => __('Its mail or web domains'),
        'certificate' => __('What keeps it on https'), 'contact' => __('Who owns it at the client, who to tell'), 'vendor' => __('Who to call when it breaks'), 'document' => __('Runbooks, diagrams')];
    if ($pickers) { ?>
        <div class="border-top pt-3">
            <div class="small text-body-secondary fw-semibold text-uppercase mb-2"><?= __('What it depends on') ?></div>
            <div class="row g-3">
                <?php foreach ($pickers as $type => $candidates) {
                    $entity = LINK_ENTITIES[$type]; ?>
                    <div class="col-md-6">
                        <label class="form-label"><?= $type === 'credential' ? __('Logins') : ($type === 'contact' ? __('People') : linkEntityPlural($type)) ?></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-fw <?= $entity['icon'] ?>"></i></span>
                            <select class="form-select select2" name="<?= $entity['table'] ?>[]" multiple data-placeholder="<?= escapeHtml($picker_hints[$type]) ?>">
                                <?php foreach ($candidates as $candidate) { ?>
                                    <option value="<?= intval($candidate['id']) ?>"<?= linkOptionData($type, $candidate, $form_client_id) ?>><?= escapeHtml($candidate['name']) ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
    <?php }
} ?>

<script>
    (function () {
        // A schedule picked with no date - the date it falls on, from today
        var interval = document.getElementById('serviceReviewInterval');
        var due = document.getElementById('serviceReviewDue');
        if (!interval || !due || interval.dataset.wired) {
            return;
        }
        interval.dataset.wired = '1';
        interval.addEventListener('change', function () {
            var months = parseInt(interval.value, 10) || 0;
            if (!months) {
                return;
            }
            var date = new Date();
            var day = date.getDate();
            date.setDate(1);
            date.setMonth(date.getMonth() + months);
            var last = new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate();
            date.setDate(Math.min(day, last));
            var pad = function (n) { return (n < 10 ? '0' : '') + n; };
            if (!due.value || due.dataset.auto) {
                due.value = date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate());
                due.dataset.auto = '1';
            }
        });
        due.addEventListener('input', function () {
            delete due.dataset.auto;
        });
    })();
</script>
