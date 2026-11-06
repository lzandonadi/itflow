<?php

/*
 * A quote as the client opens it from its link - the quote on the left, as
 * it prints; on the right what it comes to (up front, and each month,
 * quarter or year for its recurring lines), how long it is good for, and
 * Accept and Decline while it waits for an answer. Once accepted, the
 * client can send back a signed copy or a purchase order. Opening it marks
 * a sent quote viewed and tells the agents.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/guest/includes/inc_all_guest.php';

$quote = billingRow('quote', intval($_GET['quote_id'] ?? 0), false);
$url_key = (string) ($_GET['url_key'] ?? '');

if (!$quote || (string) $quote['quote_url_key'] === '' || !hash_equals((string) $quote['quote_url_key'], $url_key)) {
    echo '<div class="itflow-asset-empty mt-5"><i class="fas fa-comment-dollar fa-3x mb-3 opacity-50"></i><h1 class="h4">' . __("This quote link isn't working") . '</h1><p>' . __('It may have been mistyped or replaced. Ask whoever sent it for a new link.') . '</p></div>';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php';
    exit();
}

$quote_id = intval($quote['quote_id']);
$number = billingNumber('quote', $quote);
$status = (string) $quote['quote_status'];
$currency = (string) $quote['quote_currency_code'];

billingGuestViewed('quote', $quote);

$items = billingItems('quote', $quote_id);
$totals = billingTotals('quote', $quote, $items);
$company = billingCompany();
$expired = quoteExpired($quote);
$awaiting = in_array($status, ['Sent', 'Viewed'], true) && !$expired;
$query_key = rawurlencode($url_key);
$days_left = intval(round((strtotime((string) $quote['quote_expire']) - strtotime(date('Y-m-d'))) / 86400));

$paper = [
    'type' => 'quote',
    'row' => $quote,
    'items' => $items,
    'totals' => $totals,
    'logo_url' => $company['logo_url'],
];

?>

<div class="row g-3 my-1">

    <div class="col-lg-8 itflow-paper-col">
        <?php require $_SERVER['DOCUMENT_ROOT'] . '/includes/billing_paper.php'; ?>
    </div>

    <div class="col-lg-4 d-print-none">

        <div class="card mb-3">
            <div class="card-body">
                <?php if ($totals['recurring']) { ?>
                    <div class="d-flex flex-wrap gap-4 mb-3">
                        <?php foreach ($totals['groups'] as $frequency => $group) {
                            if ($frequency === '' && !$group['items'] && $group['discount'] == 0) {
                                continue;
                            } ?>
                            <div>
                                <div class="itflow-billing-stat-label"><?= $frequency === '' ? __('Up front') : escapeHtml($group['label']) ?></div>
                                <div class="itflow-billing-stat-value"><?= escapeHtml(billingMoney($group['total'], $currency)) ?></div>
                            </div>
                        <?php } ?>
                    </div>
                <?php } else { ?>
                    <div class="itflow-billing-stat-label"><?= __('Quote total') ?></div>
                    <div class="itflow-billing-stat-value mb-3"><?= escapeHtml(billingMoney($totals['total'], $currency)) ?></div>
                <?php } ?>

                <?php if ($awaiting) { ?>
                    <p class="small text-body-secondary mb-3">
                        <?= escapeHtml($days_left === 0 ? __('Good until %s - today is the last day.', formatDate($quote['quote_expire'], 'date')) : _n('Good until %2$s - %1$d day left.', 'Good until %2$s - %1$d days left.', $days_left, $days_left, formatDate($quote['quote_expire'], 'date'))) ?>
                        <?= __('Let us know whether to go ahead.') ?>
                    </p>
                    <?php /* Buttons that post, not links - a mail scanner or link preview opening links on the page must not answer the quote */ ?>
                    <form class="d-grid gap-2 mb-3" action="guest_post.php" method="post">
                        <input type="hidden" name="quote_id" value="<?= $quote_id ?>">
                        <input type="hidden" name="url_key" value="<?= escapeHtml($url_key) ?>">
                        <button type="submit" name="accept_quote" value="1" class="btn btn-success btn-lg confirm-link"
                            data-confirm-title="<?= escapeHtml(__('Accept %s?', $number)) ?>" data-confirm-text="<?= escapeHtml(($totals['recurring'] ? __('That is %s.', quoteAmountText($totals['groups'], $currency)) . ' ' : '') . __("We'll be told straight away and be in touch to get started.")) ?>" data-confirm-button="<?= escapeHtml(__('Accept')) ?>">
                            <i class="fas fa-thumbs-up me-2"></i><?= __('Accept') ?>
                        </button>
                        <button type="submit" name="decline_quote" value="1" class="btn btn-outline-danger confirm-link"
                            data-confirm-title="<?= escapeHtml(__('Decline %s?', $number)) ?>" data-confirm-text="<?= escapeHtml(__("We'll be told. If something needs changing, a reply to our email gets you a new quote.")) ?>" data-confirm-button="<?= escapeHtml(__('Decline')) ?>">
                            <i class="fas fa-thumbs-down me-2"></i><?= __('Decline') ?>
                        </button>
                    </form>
                <?php } elseif ($expired) { ?>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="itflow-asset-icon bg-warning-subtle text-warning-emphasis" aria-hidden="true"><i class="fas fa-hourglass-end"></i></span>
                        <div>
                            <div class="fw-semibold"><?= __('This quote has run out') ?></div>
                            <div class="small text-body-secondary"><?= escapeHtml(__('It was good until %s - ask us for an updated one.', formatDate($quote['quote_expire'], 'date'))) ?></div>
                        </div>
                    </div>
                <?php } elseif (in_array($status, ['Accepted', 'Invoiced'], true)) { ?>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="itflow-asset-icon bg-success-subtle text-success-emphasis" aria-hidden="true"><i class="fas fa-check"></i></span>
                        <div>
                            <div class="fw-semibold"><?= __('Accepted - thank you') ?></div>
                            <div class="small text-body-secondary"><?= __("We'll be in touch to get started.") ?></div>
                        </div>
                    </div>
                    <?php if ($status === 'Accepted') { ?>
                        <button type="button" class="btn btn-outline-success w-100 mb-3" data-bs-toggle="modal" data-bs-target="#uploadFileModal">
                            <i class="fas fa-file-upload me-2"></i><?= __('Send a Signed Copy or PO') ?>
                        </button>
                    <?php } ?>
                <?php } elseif ($status === 'Declined') { ?>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="itflow-asset-icon bg-secondary-subtle text-secondary-emphasis" aria-hidden="true"><i class="fas fa-times"></i></span>
                        <div>
                            <div class="fw-semibold"><?= _x('Declined', 'quote status') ?></div>
                            <div class="small text-body-secondary"><?= __("If something needs changing, let us know and we'll send a new one.") ?></div>
                        </div>
                    </div>
                <?php } else { ?>
                    <p class="small text-body-secondary mb-3"><?= __('This quote is still being put together and may change.') ?></p>
                <?php } ?>

                <div class="d-flex gap-2">
                    <a class="btn btn-outline-primary flex-fill" href="guest_post.php?export_quote_pdf=<?= $quote_id ?>&url_key=<?= $query_key ?>"><i class="fas fa-file-pdf me-2"></i><?= __('Download PDF') ?></a>
                    <button type="button" class="btn btn-outline-secondary" onclick="window.print();" title="<?= escapeHtml(__('Print')) ?>"><i class="fas fa-print"></i></button>
                </div>
            </div>
        </div>

        <?php if (!empty($company['company_email']) || !empty($company['phone_display'])) { ?>
            <div class="small text-body-secondary px-1">
                <?php
                $contact_ways = [];
                if (!empty($company['company_email'])) {
                    $contact_ways[] = '<a href="mailto:' . escapeHtml($company['company_email']) . '?subject=' . rawurlencode(__('Quote %s', $number)) . '">' . escapeHtml($company['company_email']) . '</a>';
                }
                if (!empty($company['phone_display'])) {
                    $contact_ways[] = escapeHtml($company['phone_display']);
                }
                ?>
                <?= __('Questions about this quote? Contact %1$s at %2$s.', escapeHtml($company['company_name']), implode(' ' . __('or') . ' ', $contact_ways)) ?>
            </div>
        <?php } ?>

    </div>
</div>

<?php if ($status === 'Accepted') { ?>
    <!-- A signed copy or purchase order, back to us -->
    <div class="modal fade" id="uploadFileModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-dark">
                    <h5 class="modal-title"><i class="fas fa-fw fa-file-upload me-2"></i><?= __('Send a Signed Copy or PO') ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="guest_post.php" method="post" enctype="multipart/form-data" autocomplete="off">
                    <input type="hidden" name="quote_id" value="<?= $quote_id ?>">
                    <input type="hidden" name="url_key" value="<?= escapeHtml($url_key) ?>">
                    <div class="modal-body">
                        <label class="form-label" for="quoteUploadFile"><?= __('PDF file') ?></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-fw fa-file-pdf"></i></span>
                            <input type="file" class="form-control" name="file[]" id="quoteUploadFile" accept=".pdf" required>
                        </div>
                        <div class="form-text"><?= escapeHtml(__('The signed quote, a purchase order - goes straight to us with %s.', $number)) ?></div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="guest_quote_upload_file" class="btn btn-primary text-bold"><i class="fas fa-upload me-2"></i><?= __('Send') ?></button>
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i><?= __('Cancel') ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php } ?>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php';
