<?php

/*
 * Unbilled Time - billable tickets that are done (resolved or closed) and on
 * no invoice yet: the time to bill. And, for agents who can see expenses,
 * what we paid for clients to bill on to them (billable expenses on no
 * invoice yet), at cost with its markup, and the trips out to them to bill
 * (billable trips on no invoice yet), at the trip charge and rate.
 *
 * Every client: one row each with what is waiting - tickets, hours, what it
 * comes to at their rate, the expenses, how long the oldest has waited.
 * Tick clients and invoice them all at once, one draft each (or onto each
 * one's newest draft).
 *
 * One client (?client_id=): every ticket, with its hours to bill filled in
 * from the time logged and the rounding in Admin > Settings > Invoice, ready
 * to change before it goes on, and every expense with its price. Tick the
 * ones to bill and make an invoice, or add them to one of the client's
 * drafts - or mark them not billable. A client with prepaid hours on a
 * contract has them drawn from each night (functions/contract_hours.php):
 * what is left shows here, with Draw now, and a ticket they covered part of
 * is here with the rest.
 *
 * functions/billing.php (Billing time) counts and bills the time,
 * functions/expenses.php (Billing expenses on) the expenses and
 * functions/trips.php (Mileage money) the trips; the handlers are in
 * agent/post/billing.php.
 */

if (isset($_GET['client_id'])) {
    require_once "includes/inc_all_client.php";
    $client_view = true;
} else {
    require_once "includes/inc_all.php";
    $client_view = false;
    $client_id = 0;
}

enforceUserPermission('module_sales');

$can_edit = lookupUserPermission('module_sales') >= 2;
$with_time = intval($config_module_enable_ticketing) === 1;
$with_expenses = lookupUserPermission('module_financial') >= 1;
$ready = intval($config_module_enable_accounting) === 1 && ($with_time || $with_expenses);
$can_bill_expenses = $can_edit && lookupUserPermission('module_financial') >= 2;
$rules = billingTimeRules();
$clients = $ready && $with_time ? billingUnbilledByClient($client_view ? $client_id : 0) : [];
$clients = $ready && $with_expenses ? tripUnbilledMerge(expenseUnbilledMerge($clients, $client_view ? $client_id : 0), $client_view ? $client_id : 0) : $clients;
$currency = (string) $session_company_currency;
$money = function ($amount) use ($currency) {
    return escapeHtml(billingMoney($amount, $currency));
};

$total = ['tickets' => 0, 'seconds' => 0, 'hours' => 0.0, 'prepaid' => 0.0, 'value' => 0.0, 'oldest' => 0, 'no_time' => 0, 'no_rate' => 0,
    'expenses' => 0, 'expense_value' => 0.0, 'expense_cost' => 0.0, 'trips' => 0, 'trip_value' => 0.0];
foreach ($clients as $c) {
    $total['tickets'] += count($c['tickets']);
    $total['seconds'] += $c['seconds'];
    $total['hours'] += $c['hours'];
    $total['prepaid'] += $c['prepaid'] ?? 0;
    $total['value'] += $c['value'];
    $total['oldest'] = max($total['oldest'], $c['oldest']);
    $total['no_time'] += $c['no_time'];
    $total['no_rate'] += $c['tickets'] && $c['rate'] <= 0 ? 1 : 0;
    $total['expenses'] += count($c['expenses'] ?? []);
    $total['expense_value'] += $c['expense_value'] ?? 0;
    $total['expense_cost'] += $c['expense_cost'] ?? 0;
    $total['trips'] += count($c['trips'] ?? []);
    $total['trip_value'] += $c['trip_value'] ?? 0;
}
// Expenses and trips together, where they share a column
$total['extras'] = $total['expenses'] + $total['trips'];
$total['extra_value'] = $total['expense_value'] + $total['trip_value'];
$extras_text = function ($expenses, $trips) {
    $parts = [];
    if ($expenses) {
        $parts[] = _n('%d expense', '%d expenses', $expenses);
    }
    if ($trips) {
        $parts[] = _n('%d trip', '%d trips', $trips);
    }
    return implode(', ', $parts);
};
$this_client = $client_view ? ($clients[$client_id] ?? null) : null;
// One rate for all the client's tickets, or each its own (a contract's after-hours rate, or a change of contract)
$one_rate = $this_client && count($this_client['rates'] ?? []) <= 1;
// The client's prepaid hours - drawn from each night, or now
$prepaid = $client_view && $with_time && $this_client && $this_client['tickets'] ? contractClientHours($client_id) : [];
$prepaid_left = round(array_sum(array_map(function ($c) { return $c['hours']['left']; }, $prepaid)), 2);
$show_time = $with_time && (!$client_view || ($this_client && $this_client['tickets']));
$markup_text = function ($markup) {
    return rtrim(rtrim(number_format(floatval($markup), 2, '.', ''), '0'), '.') . '%';
};

$taxes = billingTaxes();
$scope_options = [__('Support - %s', formatDate(date('Y-m-d'), 'month_year')), __('Support - %s', formatDate(date('Y-m-d', strtotime('first day of last month')), 'month_year'))];

$waiting_pill = function ($days) {
    $tone = $days > 30 ? 'danger' : ($days > 7 ? 'warning' : 'secondary');
    return '<span class="badge rounded-pill bg-' . $tone . '-subtle text-' . $tone . '-emphasis fw-normal">'
        . ($days <= 0 ? __('Today') : _n('%d day', '%d days', $days)) . '</span>';
};
$hours_text = function ($hours) {
    return billingQuantity($hours) . ' h';
};

?>

<!-- What is waiting to be billed -->
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
        <div class="itflow-billing-stat">
            <div class="itflow-billing-stat-label"><i class="fas fa-file-invoice-dollar me-1"></i><?= __('To bill') ?></div>
            <div class="itflow-billing-stat-value <?= $total['value'] + $total['extra_value'] > 0 ? 'text-info-emphasis' : '' ?>"><?= $money($total['value'] + $total['extra_value']) ?></div>
            <div class="itflow-billing-stat-note">
                <?php if ($total['extras']) { ?>
                    <?= $money($total['value']) ?> of time<?= $total['expenses'] ? ' &middot; ' . $money($total['expense_value']) . ' of expenses' : '' ?><?= $total['trips'] ? ' &middot; ' . $money($total['trip_value']) . ' of travel' : '' ?>
                <?php } else { ?>
                    <?= $client_view ? (!$one_rate ? __("At each ticket's rate") : ($this_client && $this_client['rate'] > 0 ? (in_array($this_client['rate_source'] ?? '', ['contract', 'after_hours'], true) ? __('At %1$s an hour, %2$s', $money($this_client['rate']), escapeHtml($this_client['rate_note'])) : __('At %s an hour', $money($this_client['rate']))) : __('No rate set'))) : __("At each client's hourly rate") ?>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="itflow-billing-stat">
            <div class="itflow-billing-stat-label"><i class="fas fa-stopwatch me-1"></i><?= __('Hours') ?></div>
            <div class="itflow-billing-stat-value"><?= $hours_text($total['hours']) ?></div>
            <div class="itflow-billing-stat-note"><?= billingDuration($total['seconds']) ?> logged, rounded <?= escapeHtml(billingTimeRulesText($rules)) ?><?= $total['prepaid'] > 0 ? ' &middot; less ' . escapeHtml(contractHoursText($total['prepaid'])) . ' prepaid' : '' ?></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="itflow-billing-stat">
            <div class="itflow-billing-stat-label"><i class="fas fa-life-ring me-1"></i><?= !$total['extras'] ? __('Tickets') : ($total['expenses'] && $total['trips'] ? __('Tickets &amp; more') : ($total['expenses'] ? __('Tickets &amp; expenses') : __('Tickets &amp; trips'))) ?></div>
            <div class="itflow-billing-stat-value"><?= $total['tickets'] ?><?= $total['extras'] ? ' <span class="text-body-secondary fw-normal">+</span> ' . $total['extras'] : '' ?></div>
            <div class="itflow-billing-stat-note">
                <?= $client_view ? ($total['extras'] ? _n('%d ticket', '%d tickets', $total['tickets']) . ', ' . $extras_text($total['expenses'], $total['trips']) : __('Resolved or closed, not invoiced')) : _n('%d client', '%d clients', count($clients)) ?>
                <?= $total['no_time'] ? ' &middot; <span class="text-warning-emphasis">' . $total['no_time'] . ' with no time logged</span>' : '' ?>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="itflow-billing-stat">
            <div class="itflow-billing-stat-label"><i class="fas fa-hourglass-half me-1"></i><?= __('Waiting longest') ?></div>
            <div class="itflow-billing-stat-value <?= $total['oldest'] > 30 ? 'text-danger-emphasis' : '' ?>"><?= $total['tickets'] || $total['extras'] ? $total['oldest'] . ' day' . ($total['oldest'] === 1 ? '' : 's') : '-' ?></div>
            <div class="itflow-billing-stat-note"><?= !$total['extras'] ? __('Since the ticket was done') : ($total['expenses'] && $total['trips'] ? __('Since the ticket was done, the expense paid, the trip driven') : ($total['expenses'] ? __('Since the ticket was done, the expense paid') : __('Since the ticket was done, the trip driven'))) ?></div>
        </div>
    </div>
</div>

<?php if (!$ready) { ?>
    <div class="card">
        <div class="itflow-asset-empty py-5">
            <i class="fas fa-user-clock fa-2x mb-2 opacity-50"></i>
            <div><?= $with_expenses ? __('Unbilled Time needs accounting turned on, in Admin &gt; Settings &gt; Modules.') : __('Unbilled Time needs accounting turned on, and ticketing, in Admin &gt; Settings &gt; Modules.') ?></div>
        </div>
    </div>
<?php } elseif (!$clients) { ?>
    <div class="card">
        <div class="card-header bg-dark py-2">
            <h3 class="card-title mt-2"><i class="fas fa-fw fa-user-clock me-2"></i><?= __('Unbilled Time') ?></h3>
        </div>
        <div class="itflow-asset-empty py-5">
            <i class="fas fa-check-circle fa-2x mb-2 opacity-50"></i>
            <div><?= $client_view ? ($with_expenses ? __('Nothing waiting to be billed for %s - billable tickets that are done, and billable expenses and trips, are all on an invoice.', escapeHtml($client_name)) : __('Nothing waiting to be billed for %s - billable tickets that are done are all on an invoice.', escapeHtml($client_name))) : ($with_expenses ? __('Nothing waiting to be billed - billable tickets that are done, and billable expenses and trips, are all on an invoice.') : __('Nothing waiting to be billed - billable tickets that are done are all on an invoice.')) ?></div>
            <div class="small mt-1"><?= $with_expenses ? __('A ticket shows here once it is billable and resolved or closed, an expense or a trip once it is marked billable to its client.') : __('A ticket shows here once it is billable and resolved or closed.') ?></div>
        </div>
    </div>
<?php } else { ?>

<form action="post.php" method="post" autocomplete="off" data-unbilled="<?= $client_view ? 'client' : 'clients' ?>">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <?php if ($client_view) { ?>
        <input type="hidden" name="client_id" value="<?= $client_id ?>">
    <?php } ?>

    <div class="row g-3">
        <div class="col-xl-8">
            <?php if ($client_view && !empty($this_client['expenses'])) { ?>
                <!-- What we paid for them, to bill on -->
                <div class="card mb-3">
                    <div class="card-header d-flex align-items-center">
                        <h3 class="card-title mb-0 me-auto"><i class="fas fa-fw fa-shopping-cart text-secondary me-2"></i><?= __('Expenses to bill') ?> <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis fw-normal ms-1"><?= count($this_client['expenses']) ?></span></h3>
                        <a class="btn btn-sm btn-outline-secondary" href="expenses.php?client=<?= $client_id ?>&amp;show=to_bill" title="<?= escapeHtml(__('In the expenses list')) ?>"><i class="fas fa-shopping-cart me-1"></i><?= __('Expenses') ?></a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 itflow-billing-table itflow-unbilled-table">
                            <thead class="text-nowrap table-light">
                            <tr>
                                <?php if ($can_bill_expenses) { ?>
                                    <th class="checkbox-column border-end">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" checked data-unbilled-expense-all title="<?= escapeHtml(__('Tick or untick them all')) ?>" aria-label="<?= escapeHtml(__('All expenses')) ?>">
                                        </div>
                                    </th>
                                <?php } ?>
                                <th><?= __('Expense') ?></th>
                                <th class="d-none d-md-table-cell"><?= __('Paid') ?></th>
                                <th class="text-end"><?= __('Cost') ?></th>
                                <th class="text-end"><?= __('Bill') ?></th>
                                <th class="text-end d-none d-sm-table-cell"><?= __('Margin') ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($this_client['expenses'] as $expense_id => $expense) {
                                [$line_name] = expenseBillLine($expense); ?>
                                <tr data-unbilled-expense-row data-cost="<?= $expense['cost'] ?>">
                                    <?php if ($can_bill_expenses) { ?>
                                        <td class="checkbox-column border-end">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="expense_ids[]" value="<?= $expense_id ?>" checked data-unbilled-expense-pick aria-label="<?= __('Bill %s', escapeHtml(expenseLabel($expense))) ?>">
                                            </div>
                                        </td>
                                    <?php } ?>
                                    <td class="min-w-0">
                                        <a href="#" class="ajax-modal" data-modal-size="lg" data-modal-url="modals/expense/expense.php?id=<?= $expense_id ?>"><?= escapeHtml($line_name) ?></a>
                                        <div class="small text-body-secondary"><?= escapeHtml(implode(' · ', array_filter([$expense['vendor_name'], $expense['category_name']]))) ?></div>
                                    </td>
                                    <td class="text-nowrap d-none d-md-table-cell">
                                        <div class="small"><?= escapeHtml($expense['expense_date']) ?></div>
                                        <?= $waiting_pill($expense['waiting']) ?>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <?= $money($expense['cost']) ?>
                                        <div class="small text-body-secondary">+ <?= $markup_text($expense['expense_markup']) ?></div>
                                    </td>
                                    <td class="text-end itflow-unbilled-price">
                                        <?php if ($can_bill_expenses) { ?>
                                            <input type="text" class="form-control form-control-sm text-end" name="expense_price[<?= $expense_id ?>]" value="<?= number_format($expense['price'], 2, '.', '') ?>" inputmode="decimal" aria-label="<?= escapeHtml(__('Price to bill')) ?>" data-unbilled-expense-price>
                                        <?php } else { ?>
                                            <?= $money($expense['price']) ?>
                                        <?php } ?>
                                    </td>
                                    <td class="text-end text-nowrap small text-body-secondary d-none d-sm-table-cell" data-unbilled-expense-margin><?= escapeHtml(expenseMarginText($expense['price'], $expense['cost'])) ?></td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer small text-body-secondary">
                        <?= __('At what it cost (less any refund) with its markup') ?><?= $session_is_admin ? ' - <a href="/admin/settings_invoice.php#billable-expenses">' . __('the default markup') . '</a>' : '' ?>. <?= __('Change a price before billing; each goes on as a line of its own.') ?>
                    </div>
                </div>
            <?php } ?>

            <?php if ($client_view && !empty($this_client['trips'])) { ?>
                <!-- The driving out to them, to bill -->
                <div class="card mb-3">
                    <div class="card-header d-flex align-items-center">
                        <h3 class="card-title mb-0 me-auto"><i class="fas fa-fw fa-route text-secondary me-2"></i><?= __('Travel to bill') ?> <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis fw-normal ms-1"><?= count($this_client['trips']) ?></span></h3>
                        <a class="btn btn-sm btn-outline-secondary" href="trips.php?client_id=<?= $client_id ?>&amp;show=to_bill" title="<?= escapeHtml(__('In the trips list')) ?>"><i class="fas fa-route me-1"></i><?= __('Trips') ?></a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 itflow-billing-table itflow-unbilled-table">
                            <thead class="text-nowrap table-light">
                            <tr>
                                <?php if ($can_bill_expenses) { ?>
                                    <th class="checkbox-column border-end">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" checked data-unbilled-trip-all title="<?= escapeHtml(__('Tick or untick them all')) ?>" aria-label="<?= escapeHtml(__('All trips')) ?>">
                                        </div>
                                    </th>
                                <?php } ?>
                                <th><?= __('Trip') ?></th>
                                <th class="d-none d-md-table-cell"><?= __('Driven') ?></th>
                                <th class="text-end"><?= ucfirst(tripUnit()) ?></th>
                                <th class="text-end"><?= __('Bill') ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($this_client['trips'] as $trip_id => $trip) { ?>
                                <tr data-unbilled-trip-row>
                                    <?php if ($can_bill_expenses) { ?>
                                        <td class="checkbox-column border-end">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="trip_ids[]" value="<?= $trip_id ?>" checked data-unbilled-trip-pick aria-label="<?= __('Bill %s', escapeHtml(tripLabel($trip))) ?>">
                                            </div>
                                        </td>
                                    <?php } ?>
                                    <td class="min-w-0 itflow-trip-route">
                                        <a href="#" class="ajax-modal d-block text-truncate" data-modal-size="lg" data-modal-url="modals/trip/trip.php?id=<?= $trip_id ?>"><?= escapeHtml($trip['trip_purpose']) ?></a>
                                        <div class="small text-body-secondary text-truncate">
                                            <?php if (intval($trip['trip_ticket_id']) && $trip['ticket_number'] !== null) { ?><span class="font-monospace me-1"><?= escapeHtml(ticketReference($trip)) ?></span><?php } ?>
                                            <?= escapeHtml($trip['location_name'] ?? $trip['trip_destination']) ?> &middot; <?= escapeHtml($trip['driver_name'] ?? '') ?>
                                        </div>
                                    </td>
                                    <td class="text-nowrap d-none d-md-table-cell">
                                        <div class="small"><?= escapeHtml($trip['trip_date']) ?></div>
                                        <?= $waiting_pill($trip['waiting']) ?>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <?= number_format(floatval($trip['trip_miles']), 1) ?>
                                        <?php if (intval($trip['round_trip'])) { ?><i class="fas fa-sync-alt text-body-tertiary small ms-1" title="<?= escapeHtml(__('Round trip')) ?>"></i><?php } ?>
                                    </td>
                                    <td class="text-end itflow-unbilled-price">
                                        <?php if ($can_bill_expenses) { ?>
                                            <input type="text" class="form-control form-control-sm text-end" name="trip_price[<?= $trip_id ?>]" value="<?= number_format($trip['price'], 2, '.', '') ?>" inputmode="decimal" aria-label="<?= escapeHtml(__('Price to bill')) ?>" data-unbilled-trip-price>
                                        <?php } else { ?>
                                            <?= $money($trip['price']) ?>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer small text-body-secondary">
                        <?= tripBillingOn() ? __('At %s', escapeHtml(tripBillRuleText())) : __('Trips have no charge or rate set, so they bill nothing') ?><?= $session_is_admin ? ' - <a href="/admin/settings_invoice.php#mileage">' . __('the trip rates') . '</a>' : '' ?>. <?= __('Change a price before billing; each goes on as a line of its own.') ?>
                    </div>
                </div>
            <?php } ?>

            <?php if ($prepaid) {
                $first = reset($prepaid); ?>
                <!-- Their prepaid hours, drawn from before billing -->
                <div class="alert alert-<?= $prepaid_left > 0 ? 'info' : 'warning' ?> d-flex flex-wrap align-items-center column-gap-3 row-gap-2 mb-3" data-unbilled-prepaid>
                    <i class="fas fa-fw fa-hourglass-half flex-shrink-0"></i>
                    <div class="min-w-0" style="flex: 1 1 14rem;">
                        <strong><?= $prepaid_left > 0 ? escapeHtml(__('%s of prepaid hours left', contractHoursText($prepaid_left))) : __('No prepaid hours left') ?></strong>
                        <?= __('on') ?> <a class="alert-link" href="contract.php?client_id=<?= $client_id ?>&contract_id=<?= intval($first['contract_id']) ?>"><?= escapeHtml($first['contract_name']) ?></a><?= count($prepaid) > 1 ? ' ' . __('and %d more', count($prepaid) - 1) : '' ?>.
                        <?= __('Done tickets are drawn from them each night - the tickets here are beyond them, done outside the days they cover, or put back.') ?>
                    </div>
                    <?php if ($can_edit && $prepaid_left > 0) { ?>
                        <button type="submit" name="draw_contract_hours" value="1" class="btn btn-sm btn-info flex-shrink-0 text-nowrap" formnovalidate title="<?= escapeHtml(__('Draw these tickets from the prepaid hours now')) ?>"><i class="fas fa-fw fa-arrow-down me-1"></i><?= __('Draw now') ?></button>
                    <?php } ?>
                </div>
            <?php } ?>

            <div class="card">
                <div class="card-header bg-dark py-2 d-flex align-items-center">
                    <h3 class="card-title mt-2 me-auto"><i class="fas fa-fw fa-user-clock me-2"></i><?= $client_view ? __('Unbilled Time') : __('Unbilled Time by client') ?></h3>
                    <?php if ($client_view) { ?>
                        <a class="btn btn-dark btn-sm me-1" href="unbilled_time.php" title="<?= escapeHtml(__('Every client\'s unbilled time')) ?>"><i class="fas fa-users me-1"></i><?= __('All clients') ?></a>
                    <?php } ?>
                    <?php if ($with_time && reportCanOpen('tickets_unbilled')) { ?>
                        <a class="btn btn-dark btn-sm" href="reports/tickets_unbilled.php<?= $client_view ? "?client_id=$client_id" : '' ?>" title="<?= escapeHtml(__('The Unbilled Work report')) ?>"><i class="fas fa-chart-bar me-1"></i><?= __('Report') ?></a>
                    <?php } ?>
                </div>
                <?php if ($client_view && !$show_time) { ?>
                    <div class="card-body small text-body-secondary"><i class="fas fa-check-circle me-1"></i><?= $with_time ? __('No unbilled tickets for %s - their billable tickets that are done are all on an invoice.', escapeHtml($client_name)) : __('No unbilled tickets for %s.', escapeHtml($client_name)) ?></div>
                <?php } else { ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 itflow-billing-table itflow-unbilled-table">
                        <thead class="text-nowrap table-light">
                        <tr>
                            <?php if ($can_edit) { ?>
                                <th class="checkbox-column border-end">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" checked data-unbilled-all title="<?= escapeHtml(__('Tick or untick them all')) ?>" aria-label="<?= escapeHtml(__('All')) ?>">
                                    </div>
                                </th>
                            <?php } ?>
                            <?php if ($client_view) { ?>
                                <th><?= __('Ticket') ?></th>
                                <th class="d-none d-md-table-cell"><?= __('Done') ?></th>
                                <th class="text-end"><?= __('Logged') ?></th>
                                <th class="text-end"><?= __('Bill') ?></th>
                                <th class="text-end d-none d-sm-table-cell"><?= __('Amount') ?></th>
                            <?php } else { ?>
                                <th><?= __('Client') ?></th>
                                <?php if ($with_time) { ?>
                                    <th class="text-end"><?= __('Tickets') ?></th>
                                    <th class="text-end d-none d-lg-table-cell"><?= __('Logged') ?></th>
                                    <th class="text-end d-none d-sm-table-cell"><?= __('Bill') ?></th>
                                    <th class="text-end d-none d-lg-table-cell"><?= __('Rate') ?></th>
                                <?php } ?>
                                <?php if ($with_expenses) { ?>
                                    <th class="text-end <?= $with_time ? 'd-none d-md-table-cell' : '' ?>"><?= $total['trips'] ? ($total['expenses'] ? 'Expenses &amp; travel' : 'Travel') : 'Expenses' ?></th>
                                <?php } ?>
                                <th class="text-end"><?= __('Amount') ?></th>
                                <th class="d-none d-md-table-cell"><?= __('Oldest') ?></th>
                                <th class="d-none d-sm-table-cell"></th>
                            <?php } ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($client_view) {
                            foreach ($this_client['tickets'] as $ticket_id => $ticket) { ?>
                                <tr data-unbilled-row data-rate="<?= floatval($ticket['rate']) ?>">
                                    <?php if ($can_edit) { ?>
                                        <td class="checkbox-column border-end">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="ticket_ids[]" value="<?= $ticket_id ?>" checked data-unbilled-pick aria-label="<?= __('Bill %s', escapeHtml($ticket['ticket_prefix'] . $ticket['ticket_number'])) ?>">
                                            </div>
                                        </td>
                                    <?php } ?>
                                    <td class="min-w-0">
                                        <a href="ticket.php?client_id=<?= $client_id ?>&ticket_id=<?= $ticket_id ?>"><span class="font-monospace small me-1 text-nowrap"><?= escapeHtml($ticket['ticket_prefix'] . $ticket['ticket_number']) ?></span><?= escapeHtml($ticket['ticket_subject']) ?></a>
                                        <div class="small text-body-secondary">
                                            <?= $ticket['user_name'] ? escapeHtml($ticket['user_name']) : 'Unassigned' ?>
                                            <?php if (intval($ticket['ticket_onsite'])) { ?>
                                                <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis fw-normal ms-1"><i class="fas fa-car-side me-1"></i><?= __('Onsite') ?></span>
                                            <?php } ?>
                                            <?php if (in_array($ticket['rate_source'] ?? '', ['after_hours', 'default_after_hours'], true)) { ?>
                                                <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis fw-normal ms-1" title="<?= escapeHtml(ucfirst($ticket['rate_note'])) ?>" data-unbilled-after-hours><i class="fas fa-moon me-1"></i><?= __('After hours %s', $money($ticket['rate'])) ?></span>
                                            <?php } elseif (!$one_rate) { ?>
                                                <span class="ms-1" title="<?= escapeHtml(ucfirst($ticket['rate_note'])) ?>"><?= $money($ticket['rate']) ?>/hr</span>
                                            <?php } ?>
                                        </div>
                                    </td>
                                    <td class="text-nowrap d-none d-md-table-cell">
                                        <div class="small"><?= escapeHtml(date('Y-m-d', strtotime((string) ($ticket['ticket_resolved_at'] ?: $ticket['ticket_closed_at'])))) ?></div>
                                        <?= $waiting_pill($ticket['waiting']) ?>
                                    </td>
                                    <td class="text-end text-nowrap <?= $ticket['seconds'] ? '' : 'text-warning-emphasis' ?>" <?= $ticket['seconds'] ? '' : 'title="' . escapeHtml(__('No time logged - type the hours to bill, or leave it at 0 to put it on the invoice with no charge')) . '"' ?>>
                                        <?= $ticket['seconds'] ? billingDuration($ticket['seconds']) : 'None' ?>
                                        <?php if ($ticket['prepaid'] > 0) { ?>
                                            <div class="small text-body-secondary" title="<?= escapeHtml(__('Prepaid hours covered part of it - the rest is to bill')) ?>"><?= escapeHtml(__('less %s prepaid', contractHoursText($ticket['prepaid']))) ?></div>
                                        <?php } ?>
                                    </td>
                                    <td class="text-end itflow-unbilled-hours">
                                        <?php if ($can_edit) { ?>
                                            <input type="text" class="form-control form-control-sm text-end" name="hours[<?= $ticket_id ?>]" value="<?= billingQuantity($ticket['hours']) ?>" inputmode="decimal" aria-label="<?= escapeHtml(__('Hours to bill')) ?>" data-unbilled-hours>
                                        <?php } else { ?>
                                            <?= $hours_text($ticket['hours']) ?>
                                        <?php } ?>
                                    </td>
                                    <td class="text-end text-nowrap fw-semibold d-none d-sm-table-cell" data-unbilled-amount><?= $money($ticket['value']) ?></td>
                                </tr>
                            <?php }
                        } else {
                            foreach ($clients as $id => $c) {
                                $draft = $c['drafts'] ? reset($c['drafts']) : null; ?>
                                <tr data-unbilled-row data-value="<?= round($c['value'] + ($c['expense_value'] ?? 0) + ($c['trip_value'] ?? 0), 2) ?>" data-hours="<?= round($c['hours'], 2) ?>" data-tickets="<?= count($c['tickets']) ?>" data-expenses="<?= count($c['expenses'] ?? []) ?>" data-trips="<?= count($c['trips'] ?? []) ?>">
                                    <?php if ($can_edit) { ?>
                                        <td class="checkbox-column border-end">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="client_ids[]" value="<?= $id ?>" checked data-unbilled-pick aria-label="<?= __('Bill %s', escapeHtml($c['client_name'])) ?>">
                                            </div>
                                        </td>
                                    <?php } ?>
                                    <td>
                                        <a class="fw-semibold" href="unbilled_time.php?client_id=<?= $id ?>"><?= escapeHtml($c['client_name']) ?></a>
                                        <?php if ($c['drafts']) { ?>
                                            <div class="small text-body-secondary" title="<?= escapeHtml(__('Newest draft')) ?>">
                                                <i class="fas fa-pencil-ruler me-1"></i><?= count($c['drafts']) ?> draft<?= count($c['drafts']) === 1 ? '' : 's' ?> &middot; <?= escapeHtml(billingNumber('invoice', $draft)) ?>
                                            </div>
                                        <?php } ?>
                                    </td>
                                    <?php if ($with_time) { ?>
                                        <td class="text-end">
                                            <?= count($c['tickets']) ?: '<span class="text-body-secondary">-</span>' ?>
                                            <?php if ($c['no_time']) { ?>
                                                <div class="small text-warning-emphasis text-nowrap" title="<?= escapeHtml(__('No time logged - they go on the invoice with no charge unless you open the client and type the hours')) ?>"><?= escapeHtml(_n('%d with no time', '%d with no time', intval($c['no_time']))) ?></div>
                                            <?php } ?>
                                        </td>
                                        <td class="text-end text-nowrap d-none d-lg-table-cell"><?= $c['tickets'] ? billingDuration($c['seconds']) : '<span class="text-body-secondary">-</span>' ?></td>
                                        <td class="text-end text-nowrap d-none d-sm-table-cell"><?= $c['tickets'] ? $hours_text($c['hours']) : '<span class="text-body-secondary">-</span>' ?></td>
                                        <td class="text-end text-nowrap d-none d-lg-table-cell">
                                            <?php if (!$c['tickets']) { ?>
                                                <span class="text-body-secondary">-</span>
                                            <?php } elseif (count($c['rates'] ?? []) > 1) { ?>
                                                <span class="small" title="<?= escapeHtml(__('An after-hours rate, or a change of contract - each ticket bills at its own')) ?>"><?= __('Each its own') ?></span>
                                            <?php } elseif ($c['rate'] > 0) { ?>
                                                <?= $money($c['rate']) ?><?= $c['default_rate'] ? '<div class="small text-body-tertiary" title="' . escapeHtml(__('No contract in force sets a rate - the default in Admin > Settings > Defaults')) . '">' . __('default') . '</div>' : (in_array($c['rate_source'] ?? '', ['contract', 'after_hours'], true) ? '<div class="small text-body-tertiary" title="' . escapeHtml(ucfirst($c['rate_note'])) . '">' . __('contract') . '</div>' : '') ?>
                                            <?php } else { ?>
                                                <span class="small text-warning-emphasis" title="<?= escapeHtml(__("Give the client's contract an hourly rate, or set a default in Admin > Settings > Defaults")) ?>"><?= __('No rate') ?></span>
                                            <?php } ?>
                                        </td>
                                    <?php } ?>
                                    <?php if ($with_expenses) { ?>
                                        <td class="text-end text-nowrap <?= $with_time ? 'd-none d-md-table-cell' : '' ?>">
                                            <?php if ($c['expenses'] || !empty($c['trips'])) { ?>
                                                <?= $money($c['expense_value'] + ($c['trip_value'] ?? 0)) ?>
                                                <div class="small text-body-secondary"><?= $extras_text(count($c['expenses']), count($c['trips'] ?? [])) ?></div>
                                            <?php } else { ?>
                                                <span class="text-body-secondary">-</span>
                                            <?php } ?>
                                        </td>
                                    <?php } ?>
                                    <td class="text-end text-nowrap fw-semibold"><?= $money($c['value'] + ($c['expense_value'] ?? 0) + ($c['trip_value'] ?? 0)) ?></td>
                                    <td class="text-nowrap d-none d-md-table-cell"><?= $waiting_pill($c['oldest']) ?></td>
                                    <td class="text-end d-none d-sm-table-cell">
                                        <a class="btn btn-sm btn-outline-secondary text-nowrap" href="unbilled_time.php?client_id=<?= $id ?>"><?= __('Review') ?></a>
                                    </td>
                                </tr>
                            <?php }
                        } ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($with_time) { ?>
                    <div class="card-footer small text-body-secondary">
                        <?= __('Time is rounded %s, per ticket', escapeHtml(billingTimeRulesText($rules))) ?><?= $session_is_admin ? ' - <a href="/admin/settings_invoice.php#billing-time">' . __('change how') . '</a>' : '' ?>.
                        <?= $client_view ? __('Change the hours before billing - a ticket left at 0 goes on the invoice with no charge.') : ($with_expenses ? __('Review a client to change the hours on its tickets and the prices of its expenses and trips first.') : __('Review a client to change the hours on its tickets first.')) ?>
                    </div>
                <?php } ?>
                <?php } ?>
            </div>

        </div>

        <?php if ($can_edit) { ?>
            <!-- How they go on -->
            <div class="col-xl-4">
                <div class="card itflow-unbilled-panel">
                    <div class="card-header">
                        <h3 class="card-title mb-0"><i class="fas fa-fw fa-file-invoice-dollar text-secondary me-2"></i><?= $client_view ? __('Bill them') : __('Bill these clients') ?></h3>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php if ($client_view) { ?>
                                <div class="col-12">
                                    <label class="form-label"><?= __('Invoice') ?></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa fa-fw fa-file-invoice"></i></span>
                                        <select class="form-select" name="invoice_id" data-unbilled-target>
                                            <option value="0"><?= __('A new draft invoice') ?></option>
                                            <?php foreach ($this_client['drafts'] as $draft_id => $draft) { ?>
                                                <option value="<?= $draft_id ?>"><?= __('Add to %s', escapeHtml(billingNumber('invoice', $draft))) ?><?= trim((string) $draft['invoice_scope']) !== '' ? ' - ' . escapeHtml($draft['invoice_scope']) : '' ?> (<?= $money($draft['invoice_amount']) ?>)</option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                            <?php } else { ?>
                                <div class="col-12">
                                    <label class="form-label"><?= __('Invoices') ?></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa fa-fw fa-file-invoice"></i></span>
                                        <select class="form-select" name="target" data-unbilled-target>
                                            <option value="new"><?= __('A new draft for each client') ?></option>
                                            <option value="draft"><?= __('Each client\'s newest draft, or a new one') ?></option>
                                        </select>
                                    </div>
                                </div>
                            <?php } ?>
                            <div class="col-sm-6 col-xl-12" data-unbilled-new>
                                <label class="form-label"><?= __('Invoice date') ?> <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-calendar"></i></span>
                                    <input type="date" class="form-control" name="date" max="2999-12-31" value="<?= date('Y-m-d') ?>" required>
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-12" data-unbilled-new>
                                <label class="form-label"><?= __('Category') ?> <strong class="text-danger">*</strong></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-tag"></i></span>
                                    <select class="form-select" name="category" required>
                                        <option value=""><?= __('- Category -') ?></option>
                                        <?= categoryOptionsHtml('Income') ?>
                                    </select>
                                    <?= categoryAddButtonHtml('Income', 'btn btn-secondary') ?>
                                </div>
                            </div>
                            <div class="col-12" data-unbilled-new>
                                <label class="form-label"><?= __('Scope') ?></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-comment"></i></span>
                                    <input type="text" class="form-control" name="scope" maxlength="255" value="<?= escapeHtml($scope_options[0]) ?>" list="unbilledScopes">
                                    <datalist id="unbilledScopes">
                                        <?php foreach ($scope_options as $scope_option) { ?>
                                            <option value="<?= escapeHtml($scope_option) ?>"></option>
                                        <?php } ?>
                                    </datalist>
                                </div>
                            </div>
                            <?php if ($show_time) { ?>
                            <div class="col-12">
                                <label class="form-label"><?= __('Line') ?></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-cube"></i></span>
                                    <input type="text" class="form-control" name="item_name" maxlength="200" value="<?= escapeHtml($rules['item_name']) ?>" required>
                                </div>
                            </div>
                            <?php } ?>
                            <?php if ($client_view && $show_time) { ?>
                                <div class="col-sm-6 col-xl-12">
                                    <label class="form-label"><?= __('Rate') ?><?= $one_rate ? ' <strong class="text-danger">*</strong>' : '' ?></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa fa-fw fa-dollar-sign"></i></span>
                                        <input type="text" class="form-control text-end" name="price" inputmode="decimal" value="<?= $one_rate ? number_format($this_client['rate'], 2, '.', '') : '' ?>" placeholder="<?= escapeHtml(__('Each its own')) ?>"<?= $one_rate ? ' required' : '' ?> data-unbilled-rate>
                                    </div>
                                    <div class="form-text"><?= !$one_rate ? __('Each ticket at its own rate - a contract\'s sets some. Type one to bill them all at it.') : __('An hour - %s', $this_client['default_rate'] ? __('the default, the client has no rate') : escapeHtml($this_client['rate_note'] ?: __('the client\'s rate'))) ?></div>
                                </div>
                            <?php } ?>
                            <div class="<?= $client_view ? 'col-sm-6 col-xl-12' : 'col-12' ?>">
                                <label class="form-label"><?= __('Tax') ?></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-percent"></i></span>
                                    <select class="form-select" name="tax_id" data-unbilled-tax>
                                        <?= taxOptionsHtml($taxes, TAX_USUAL, $client_view ? __('Each line\'s usual rate') : __('Each client\'s usual rates'), $client_view ? floatval(taxRow(taxDefaultFor($client_id, 'time'))['tax_percent'] ?? 0) : 0) ?>
                                    </select>
                                </div>
                            </div>
                            <?php if ($show_time) { ?>
                            <div class="col-12">
                                <label class="form-label"><?= __('Lines') ?></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-fw fa-list"></i></span>
                                    <select class="form-select" name="lines">
                                        <option value="each"><?= __('A line for each ticket') ?></option>
                                        <option value="one"><?= __('One line for all of them') ?></option>
                                    </select>
                                </div>
                                <?php if ($total['extras']) { ?>
                                    <div class="form-text"><?= $total['expenses'] && $total['trips'] ? __('Expenses and trips go on with a line each, at their price') : ($total['expenses'] ? __('Expenses go on with a line each, at their price') : __('Trips go on with a line each, at their price')) ?><?= $client_view ? '' : ($total['expenses'] ? ' - ' . __('an expense at cost with its markup') : '') ?>.</div>
                                <?php } ?>
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex justify-content-between align-items-end mb-3">
                            <div class="small text-body-secondary" data-unbilled-summary></div>
                            <div class="fs-4 fw-semibold itflow-money" data-unbilled-total></div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" name="<?= $client_view ? 'unbilled_invoice' : 'unbilled_invoice_clients' ?>" value="1" class="btn btn-primary text-bold flex-grow-1" data-unbilled-submit>
                                <i class="fas fa-check me-2"></i><span data-unbilled-submit-label><?= $client_view ? __('Make Invoice') : __('Make Invoices') ?></span>
                            </button>
                            <?php if ($client_view) { ?>
                                <button type="submit" name="unbilled_write_off" value="1" class="btn btn-outline-danger confirm-link" formnovalidate
                                    data-confirm-title="<?= escapeHtml(__('Mark them not billable?')) ?>" data-confirm-text="<?= escapeHtml((!$total['extras'] ? __('The ticked tickets stop being billable and leave this list.') : ($total['expenses'] && $total['trips'] ? __('The ticked tickets, expenses and trips stop being billable and leave this list.') : ($total['expenses'] ? __('The ticked tickets and expenses stop being billable and leave this list.') : __('The ticked tickets and trips stop being billable and leave this list.')))) . ' ' . ($total['tickets'] ? __("Their time stays logged on them, and each one's history says it.") : __('They stay as our cost.'))) ?>" data-confirm-button="<?= escapeHtml(__('Not billable')) ?>">
                                    <i class="fas fa-ban me-2"></i><?= __('Not billable') ?>
                                </button>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>
</form>

<?php } ?>

<script src="/js/billing_items.js"></script>
<script src="/js/unbilled_time.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    itflowUnbilledTime({
        currency: <?= json_encode($currency) ?>,
        locale: <?= json_encode(str_replace('_', '-', (string) $session_company_locale)) ?>
    });
});
</script>

<?php require_once "../includes/footer.php";
