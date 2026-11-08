<?php

/*
 * A trip's quick look - where from and where to with a directions link, the
 * miles (each way for a round trip), the client and their site, the ticket
 * it was for, what it was for, who drove and who logged it, and the
 * odometer readings. The money: driven in their own vehicle and paid back
 * for (or to be, with Pay Mileage), billed to the client (or to be, with
 * Take off for one billed). Edit, Copy, Return Trip and Delete. Opened from
 * the trips list, a ticket, a site, an invoice and the Mileage report.
 */

require_once '../../../includes/modal_header.php';

$row = tripRow(intval($_GET['id'] ?? 0));
if (!$row) {
    echo json_encode(['error' => __('That trip no longer exists, or is not one you can see.')]);
    exit;
}

$trip_id = intval($row['trip_id']);
$client_id = intval($row['trip_client_id']);
$miles = floatval($row['trip_miles']);
$round = intval($row['round_trip']) === 1;
$can_edit = tripCanEdit($row);
$can_delete = tripCanDelete($row) && $row['claim_expense_id'] === null && $row['invoice_number'] === null;
$rules = tripRules();
$own = intval($row['trip_own_vehicle']) === 1;
$claimed = $row['claim_expense_id'] !== null;
$billed = $row['invoice_number'] !== null;
$billable = intval($row['trip_billable']) === 1;
$can_claim = $own && !$claimed && $miles > 0 && $rules['rate'] > 0 && tripCanManage();
$can_unbill = $billed && tripCanUnbill($row['invoice_status']);

ob_start();

?>

<div class="modal-header bg-dark" data-bs-theme="dark">
    <div class="d-flex align-items-center gap-3 min-w-0 flex-grow-1">
        <span class="itflow-asset-icon itflow-asset-icon-lg bg-primary-subtle text-primary-emphasis" aria-hidden="true"><i class="fas fa-route"></i></span>
        <div class="min-w-0 flex-grow-1">
            <h5 class="modal-title fw-semibold text-break"><?= escapeHtml($row['trip_destination']) ?></h5>
            <div class="small text-body-secondary mt-1 text-truncate"><?= escapeHtml(formatDate($row['trip_date'], 'date_weekday')) ?> &middot; <?= escapeHtml(__('from %s', $row['trip_source'])) ?></div>
        </div>
        <div class="text-end">
            <div class="fs-4 fw-semibold text-white text-nowrap"><?= escapeHtml(tripMilesText($miles)) ?></div>
            <div class="small text-body-secondary"><?= $round ? __('round trip') : __('one way') ?></div>
        </div>
    </div>
    <button type="button" class="btn-close btn-close-white ms-3" data-bs-dismiss="modal" aria-label="<?= escapeHtml(__('Close')) ?>"></button>
</div>

<div class="modal-body">

    <!-- The route -->
    <div class="d-flex align-items-stretch gap-3 mb-3 p-3 rounded border bg-body-tertiary">
        <div class="d-flex flex-column align-items-center pt-1" aria-hidden="true">
            <i class="fas fa-circle text-body-secondary small"></i>
            <div class="flex-grow-1 border-start border-2 my-1" style="border-style: dashed !important;"></div>
            <i class="fas fa-map-marker-alt text-danger"></i>
        </div>
        <div class="flex-grow-1 min-w-0 d-flex flex-column gap-3">
            <div>
                <div class="small text-body-secondary"><?= __('From') ?></div>
                <div class="text-break"><?= escapeHtml($row['trip_source']) ?></div>
            </div>
            <div>
                <div class="small text-body-secondary"><?= $row['location_name'] !== null ? escapeHtml(__('To - %s', $row['location_name'])) : __('To') ?></div>
                <div class="text-break"><?= escapeHtml($row['trip_destination']) ?></div>
                <?php if ($round) { ?>
                    <div class="small text-body-secondary mt-1"><i class="fas fa-sync-alt me-1"></i><?= escapeHtml(__('and back - %s each way', tripMilesText(tripOneWay($row)))) ?></div>
                <?php } ?>
            </div>
        </div>
        <div class="align-self-center">
            <a class="btn btn-outline-secondary" href="<?= escapeHtml(tripMapUrl($row)) ?>" target="_blank" rel="noopener"><i class="fas fa-map-marked-alt me-2"></i><?= __('Directions') ?></a>
        </div>
    </div>

    <!-- At a glance -->
    <div class="row g-2 itflow-asset-glance">
        <div class="col-sm-6">
            <div class="itflow-glance-item" title="<?= escapeHtml(__('Driver')) ?>">
                <i class="fas fa-fw fa-user"></i>
                <span><?= formatAssignee($row['trip_user_id'], $row['driver_name'] ?? '', $row['driver_avatar'] ?? '', false, __('No driver')) ?></span>
            </div>
            <div class="itflow-glance-item" title="<?= escapeHtml(__('Client')) ?>">
                <i class="fas fa-fw fa-building"></i>
                <?php if ($client_id) { ?>
                    <a class="text-truncate" href="client_overview.php?client_id=<?= $client_id ?>"><?= escapeHtml($row['client_name']) ?></a>
                <?php } else { ?>
                    <span class="text-body-secondary"><?= __('Our own - not for a client') ?></span>
                <?php } ?>
            </div>
            <?php if ($row['location_name'] !== null) { ?>
                <div class="itflow-glance-item" title="<?= escapeHtml(__('Their site')) ?>">
                    <i class="fas fa-fw fa-map-marker-alt"></i>
                    <a class="text-truncate" href="location.php?client_id=<?= $client_id ?>&amp;location_id=<?= intval($row['trip_location_id']) ?>"><?= escapeHtml($row['location_name']) ?></a>
                </div>
            <?php } ?>
            <?php if (intval($row['trip_ticket_id'])) { ?>
                <div class="itflow-glance-item" title="<?= escapeHtml(__('Ticket')) ?>">
                    <i class="fas fa-fw fa-life-ring"></i>
                    <span class="text-truncate"><?= $row['ticket_number'] !== null ? ticketLink(['ticket_id' => $row['trip_ticket_id'], 'ticket_client_id' => $row['ticket_client_id'], 'ticket_prefix' => $row['ticket_prefix'], 'ticket_number' => $row['ticket_number'], 'ticket_subject' => $row['ticket_subject'], 'ticket_resolved_at' => $row['ticket_resolved_at'], 'ticket_closed_at' => $row['ticket_closed_at']]) : '<span class="text-body-secondary">' . escapeHtml(__('A ticket since deleted')) . '</span>' ?></span>
                </div>
            <?php } ?>
        </div>
        <div class="col-sm-6">
            <?php if ($row['trip_start_odometer'] !== null || $row['trip_end_odometer'] !== null) { ?>
                <div class="itflow-glance-item" title="<?= escapeHtml(__('Odometer')) ?>">
                    <i class="fas fa-fw fa-tachometer-alt"></i>
                    <span class="font-monospace"><?= $row['trip_start_odometer'] !== null ? number_format(intval($row['trip_start_odometer'])) : '?' ?> &rarr; <?= $row['trip_end_odometer'] !== null ? number_format(intval($row['trip_end_odometer'])) : '?' ?></span>
                </div>
            <?php } ?>
            <div class="itflow-glance-item" title="<?= escapeHtml(__('Logged')) ?>">
                <i class="fas fa-fw fa-clock"></i>
                <span><span title="<?= escapeHtml($row['trip_created_at']) ?>"><?= timeAgo($row['trip_created_at']) ?></span><?php if ($row['created_by_name'] && intval($row['trip_created_by']) !== intval($row['trip_user_id'])) { ?> <span class="text-body-secondary">by <?= escapeHtml($row['created_by_name']) ?></span><?php } ?></span>
            </div>
            <?php if ($client_id || intval($row['trip_location_id'])) {
                [$site_trips, $site_miles] = intval($row['trip_location_id']) ? tripTotalsFor('trip_location_id', $row['trip_location_id']) : tripTotalsFor('trip_client_id', $client_id); ?>
                <div class="itflow-glance-item" title="<?= escapeHtml(__('Trips there')) ?>">
                    <i class="fas fa-fw fa-route"></i>
                    <a class="text-truncate" href="trips.php?client_id=<?= $client_id ?><?= intval($row['trip_location_id']) ? '&amp;location_id=' . intval($row['trip_location_id']) : '' ?>"><?= escapeHtml(intval($row['trip_location_id']) ? _n('%d trip to this site', '%d trips to this site', $site_trips) : _n('%d trip to them', '%d trips to them', $site_trips)) ?></a>
                </div>
            <?php } ?>
        </div>
    </div>

    <!-- The money - paid back for their own vehicle, billed to the client -->
    <div class="row g-2 mt-1">
        <div class="col-sm-6">
            <div class="itflow-glance-item" title="<?= escapeHtml(__('Vehicle')) ?>">
                <i class="fas fa-fw fa-car-side"></i>
                <?php if (!$own) { ?>
                    <span class="text-body-secondary"><?= __('A company vehicle') ?></span>
                <?php } elseif ($claimed) { ?>
                    <span><?= __('Their own - %s', '<a href="#" class="ajax-modal" data-modal-size="lg" data-modal-url="modals/expense/expense.php?id=' . intval($row['claim_expense_id']) . '">' . escapeHtml(__('paid on mileage')) . '</a>' . escapeHtml($row['reimbursement_date'] !== null ? __(', paid back %s', formatDate($row['reimbursement_date'], 'date_short')) : __(', to pay back'))) ?></span>
                <?php } elseif ($rules['rate'] > 0) { ?>
                    <span><?= __('Their own - %s', '<strong>' . escapeHtml(billingMoney(round($miles * $rules['rate'], 2))) . '</strong> ' . escapeHtml(__('to pay back'))) ?> <span class="text-body-secondary"><?= escapeHtml(__('at %s', tripRateText($rules['rate']))) ?></span></span>
                <?php } else { ?>
                    <span><?= __('Their own') ?> <span class="text-body-secondary">- no mileage rate set</span></span>
                <?php } ?>
            </div>
        </div>
        <?php if ($client_id) { ?>
            <div class="col-sm-6">
                <div class="itflow-glance-item" title="<?= escapeHtml(__('Billing')) ?>">
                    <i class="fas fa-fw fa-file-invoice-dollar"></i>
                    <?php if ($billed) { ?>
                        <span><?= __('Billed on') ?> <a href="invoice.php?invoice_id=<?= intval($row['trip_invoice_id']) ?>"><?= escapeHtml($row['invoice_prefix'] . $row['invoice_number']) ?></a><?= $row['billed_subtotal'] !== null ? ' ' . escapeHtml(__('at %s', billingMoney($row['billed_subtotal']))) : '' ?></span>
                    <?php } elseif ($billable) { ?>
                        <span><?= __('%s to bill', '<strong>' . escapeHtml(billingMoney(tripBillPrice($row))) . '</strong>') ?><?= tripCanManage() && lookupUserPermission('module_sales') >= 1 ? ' - <a href="unbilled_time.php?client_id=' . $client_id . '">' . escapeHtml(__('Unbilled Time')) . '</a>' : '' ?></span>
                    <?php } else { ?>
                        <span class="text-body-secondary"><?= __('Not billed - our cost') ?></span>
                    <?php } ?>
                </div>
            </div>
        <?php } ?>
    </div>

    <div class="mt-3">
        <div class="small fw-semibold text-uppercase text-body-secondary mb-1"><?= __('What for') ?></div>
        <div class="text-break" style="white-space: pre-line;"><?= escapeHtml($row['trip_purpose']) ?></div>
    </div>

    <!-- Its history, from the audit log -->
    <?= auditLogHistorySection(auditLogHistory('Trip', $trip_id, 10), ['client_id' => $client_id]) ?>

</div>

<div class="modal-footer">
    <?php if ($can_edit) { ?>
        <button type="button" class="btn btn-primary text-bold ajax-modal" data-modal-size="lg" data-modal-url="modals/trip/trip_edit.php?id=<?= $trip_id ?>"><i class="fas fa-edit me-2"></i><?= __('Edit') ?></button>
    <?php } ?>
    <?php if ($can_claim) { ?>
        <button type="button" class="btn btn-outline-success ajax-modal" data-modal-size="lg" data-modal-url="modals/trip/trip_claim.php?trip_ids[]=<?= $trip_id ?>"><i class="fas fa-hand-holding-usd"></i><span class="d-none d-sm-inline ms-2"><?= __('Pay Mileage') ?></span></button>
    <?php } ?>
    <?php if ($can_unbill) { ?>
        <a class="btn btn-outline-secondary confirm-link" href="post.php?trip_unbill=<?= $trip_id ?>&amp;csrf_token=<?= $_SESSION['csrf_token'] ?>"
            data-confirm-text="<?= escapeHtml($row['invoice_status'] === 'Cancelled' ? __('The invoice is cancelled, so its line stays on it and the trip waits to be billed again.') : __('Its line comes off %s and the trip waits to be billed again.', $row['invoice_prefix'] . $row['invoice_number'])) ?>"><i class="fas fa-unlink"></i><span class="d-none d-sm-inline ms-2"><?= __('Take off Invoice') ?></span></a>
    <?php } ?>
    <div class="btn-group">
        <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-size="lg" data-modal-url="modals/trip/trip_copy.php?id=<?= $trip_id ?>&amp;return=1"><i class="fas fa-undo-alt me-2"></i><?= __('Return Trip') ?></button>
        <button type="button" class="btn btn-outline-secondary ajax-modal" data-modal-size="lg" data-modal-url="modals/trip/trip_copy.php?id=<?= $trip_id ?>"><i class="fas fa-copy me-2"></i><?= __('Copy') ?></button>
    </div>
    <?php if ($can_delete) { ?>
        <a class="btn btn-outline-danger confirm-link" href="post.php?delete_trip=<?= $trip_id ?>&amp;csrf_token=<?= $_SESSION['csrf_token'] ?>"
            data-confirm-text="<?= escapeHtml(__('%s goes for good.', tripLabel($row))) ?>"><i class="fas fa-trash"></i><span class="d-none d-sm-inline ms-2"><?= __('Delete') ?></span></a>
    <?php } ?>
    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i><?= __('Close') ?></button>
</div>

<?php
require_once '../../../includes/modal_footer.php';
