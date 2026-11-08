<?php

/*
 * Customer Satisfaction - how contacts rated their tickets once closed (the
 * Good / Bad buttons in the client portal), for tickets closed in the period:
 * the share rated good, how many get rated at all, the trend, by agent and by
 * client, what the rating follows (a late first reply, a slow fix) and every
 * bad rating so someone can follow up.
 */

require_once "includes/inc_report.php";
reportEnforce('satisfaction');

$period = reportPeriod('last_90');
$client_id = reportClientId();
$agent_id = reportAgentId();
$closed_sql = reportTicketSql($client_id, $agent_id) . " AND tickets.ticket_closed_at IS NOT NULL";
$filter = $closed_sql . " AND " . reportRangeSql('tickets.ticket_closed_at', $period);

const SATISFACTION_COLUMNS = "COUNT(tickets.ticket_id) AS closed,
    SUM(tickets.ticket_feedback IS NOT NULL AND tickets.ticket_feedback != '') AS rated,
    SUM(tickets.ticket_feedback = 'Good') AS good, SUM(tickets.ticket_feedback = 'Bad') AS bad";

/** Counts and shares from a row of SATISFACTION_COLUMNS. */
function satisfactionFigures($row) {
    $out = [];
    foreach (['closed', 'rated', 'good', 'bad'] as $key) {
        $out[$key] = intval($row[$key] ?? 0);
    }
    $out['score'] = reportShare($out['good'], $out['good'] + $out['bad'], 1);
    $out['rated_percent'] = reportShare($out['rated'], $out['closed'], 1);
    return $out;
}

$grouped = function ($group_sql, $where, $join = '') {
    global $mysqli;
    $rows = [];
    $sql = mysqli_query($mysqli, "SELECT $group_sql AS k, " . SATISFACTION_COLUMNS . " FROM tickets $join WHERE $where GROUP BY k");
    while ($row = mysqli_fetch_assoc($sql)) {
        $rows[(string) $row['k']] = satisfactionFigures($row);
    }
    return $rows;
};

$overall = satisfactionFigures(mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT " . SATISFACTION_COLUMNS . " FROM tickets WHERE $filter")));
$previous = satisfactionFigures(mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT " . SATISFACTION_COLUMNS . " FROM tickets
    WHERE $closed_sql AND " . reportRangeSql('tickets.ticket_closed_at', $period, true))));
$other = $overall['rated'] - $overall['good'] - $overall['bad'];

// Over the period - a day's two or three ratings make a jumpy line, so weeks at the least
$buckets = reportBuckets($period);
if ($buckets['unit'] === 'day') {
    $buckets = reportBuckets(['start' => $period['start'], 'end' => $period['end'], 'days' => 60]);
}
$by_bucket = $grouped(reportBucketSql('tickets.ticket_closed_at', $buckets['unit']), $filter);

// By agent
$names = [];
$sql = mysqli_query($mysqli, "SELECT user_id, user_name, user_avatar FROM users WHERE user_type = 1");
while ($row = mysqli_fetch_assoc($sql)) {
    $names[intval($row['user_id'])] = $row;
}
$agent_rows = [];
foreach ($grouped('tickets.ticket_assigned_to', $filter) as $user_id => $figures) {
    $user_id = intval($user_id);
    $agent_rows[] = ['user_id' => $user_id, 'agent' => $names[$user_id]['user_name'] ?? '', 'avatar' => $names[$user_id]['user_avatar'] ?? ''] + $figures;
}
usort($agent_rows, function ($a, $b) { return $b['rated'] <=> $a['rated'] ?: $b['closed'] <=> $a['closed']; });

// By client - the most bad ratings first, as those are the calls to make
$client_rows = [];
$sql = mysqli_query($mysqli, "SELECT client_id, client_name, client_abbreviation, " . SATISFACTION_COLUMNS . "
    FROM tickets JOIN clients ON tickets.ticket_client_id = client_id WHERE $filter GROUP BY client_id, client_name, client_abbreviation");
while ($row = mysqli_fetch_assoc($sql)) {
    $client_rows[] = ['client_id' => intval($row['client_id']), 'client' => $row['client_name'], 'abbr' => $row['client_abbreviation']] + satisfactionFigures($row);
}
usort($client_rows, function ($a, $b) {
    return $b['bad'] <=> $a['bad'] ?: ($a['score'] ?? 101) <=> ($b['score'] ?? 101) ?: $b['rated'] <=> $a['rated'];
});

// What the rating follows - the first reply against its SLA, and how long the fix took
$drivers = [];
$reply = $grouped('tickets.ticket_response_sla_met', $filter . " AND tickets.ticket_sla_id > 0");
foreach (['1' => __('First reply in time'), '0' => __('First reply late')] as $key => $label) {
    if (isset($reply[$key])) {
        $drivers[] = ['label' => $label, 'group' => __('First reply against the SLA')] + $reply[$key];
    }
}
$fix = $grouped("CASE WHEN tickets.ticket_resolved_at IS NULL THEN 'none'
    WHEN TIMESTAMPDIFF(HOUR, tickets.ticket_created_at, tickets.ticket_resolved_at) < 24 THEN 'day'
    WHEN TIMESTAMPDIFF(HOUR, tickets.ticket_created_at, tickets.ticket_resolved_at) < 72 THEN 'three'
    WHEN TIMESTAMPDIFF(HOUR, tickets.ticket_created_at, tickets.ticket_resolved_at) < 168 THEN 'week' ELSE 'longer' END", $filter);
foreach (['day' => __('Resolved within a day'), 'three' => __('Resolved in 1 to 3 days'), 'week' => __('Resolved in 3 to 7 days'), 'longer' => __('Resolved after a week')] as $key => $label) {
    if (isset($fix[$key])) {
        $drivers[] = ['label' => $label, 'group' => __('Time to resolve')] + $fix[$key];
    }
}

$driver_rows = [];
foreach ($drivers as $driver) {
    if (!$driver_rows || end($driver_rows)['group'] !== $driver['group']) {
        $driver_rows[] = ['_heading' => $driver['group'], 'group' => $driver['group']];
    }
    $driver_rows[] = $driver;
}

// Every bad rating, newest first
$bad_rows = [];
$sql = mysqli_query($mysqli, "SELECT tickets.ticket_id, ticket_prefix, ticket_number, ticket_subject, ticket_priority, ticket_client_id, ticket_assigned_to,
        ticket_created_at, ticket_first_response_at, ticket_resolved_at, ticket_closed_at, ticket_sla_id, ticket_response_sla_met, ticket_resolution_sla_met,
        client_name, client_abbreviation, contact_name, user_name, user_avatar,
        TIMESTAMPDIFF(SECOND, ticket_created_at, ticket_first_response_at) AS reply_seconds,
        TIMESTAMPDIFF(SECOND, ticket_created_at, COALESCE(ticket_resolved_at, ticket_closed_at)) AS resolve_seconds,
        (SELECT SUM(TIME_TO_SEC(ticket_reply_time_worked)) FROM ticket_replies WHERE ticket_reply_ticket_id = tickets.ticket_id AND ticket_reply_archived_at IS NULL) AS worked_seconds
    FROM tickets
    LEFT JOIN clients ON ticket_client_id = client_id
    LEFT JOIN contacts ON ticket_contact_id = contact_id
    LEFT JOIN users ON ticket_assigned_to = user_id
    WHERE $filter AND tickets.ticket_feedback = 'Bad'
    ORDER BY ticket_closed_at DESC");
while ($row = mysqli_fetch_assoc($sql)) {
    $bad_rows[] = $row;
}

$figure_columns = [
    'closed' => ['label' => __('Closed'), 'type' => 'count'],
    'rated' => ['label' => __('Rated'), 'type' => 'count'],
    'rated_percent' => ['label' => __('Rated share'), 'type' => 'percent', 'class' => 'text-body-secondary', 'title' => __('Closed tickets the contact rated')],
    'good' => ['label' => __('Good'), 'type' => 'count'],
    'bad' => ['label' => __('Bad'), 'type' => 'count', 'html' => function ($row) {
        return $row['bad'] ? '<span class="text-danger-emphasis fw-semibold">' . $row['bad'] . '</span>' : '<span class="itflow-report-zero">–</span>';
    }],
    'score' => ['label' => __('Satisfaction'), 'type' => 'percent', 'title' => __('Good ratings, of the good and bad'), 'html' => function ($row) {
        return reportRateHtml($row['score']);
    }],
];
$agent_columns = ['agent' => ['label' => __('Assigned to'), 'csv' => function ($row) { return $row['user_id'] ? $row['agent'] : 'Unassigned'; }, 'html' => function ($row) {
    return formatAssignee($row['user_id'], $row['agent'], $row['avatar'], true);
}]] + $figure_columns;
$client_columns = ['client' => ['label' => __('Client'), 'html' => function ($row) {
    return reportClientCell($row['client_id'], $row['client'], $row['abbr'], 'tickets.php');
}]] + $figure_columns;
$driver_columns = [
    'label' => ['label' => __('How the ticket went'), 'sort' => false],
    'rated' => ['label' => __('Rated'), 'type' => 'count', 'sort' => false],
    'good' => ['label' => __('Good'), 'type' => 'count', 'sort' => false],
    'bad' => $figure_columns['bad'] + ['sort' => false],
    'score' => $figure_columns['score'] + ['sort' => false],
    'meter' => ['label' => '', 'sort' => false, 'csv' => false, 'class' => 'w-25', 'html' => function ($row) {
        return $row['score'] === null ? '' : '<div class="progress itflow-report-meter" role="presentation"><div class="progress-bar bg-success" style="width: ' . round($row['score']) . '%"></div>'
            . '<div class="progress-bar bg-danger" style="width: ' . (100 - round($row['score'])) . '%"></div></div>';
    }],
];
$bad_columns = [
    'ticket' => ['label' => __('Ticket'), 'class' => 'itflow-report-text', 'csv' => function ($row) { return ticketReference($row) . ' ' . $row['ticket_subject']; }, 'html' => function ($row) {
        return reportPriorityDot($row['ticket_priority']) . reportTicketLink($row);
    }],
    'client_name' => ['label' => __('Client'), 'html' => function ($row) {
        return reportClientCell($row['ticket_client_id'], $row['client_name'] ?? '', $row['client_abbreviation'] ?? '');
    }],
    'contact_name' => ['label' => __('Rated by'), 'html' => function ($row) {
        return $row['contact_name'] ? '<span class="text-nowrap">' . escapeHtml($row['contact_name']) . '</span>' : '<span class="text-body-tertiary">-</span>';
    }],
    'user_name' => ['label' => __('Assigned to'), 'csv' => function ($row) { return $row['user_name'] ?: 'Unassigned'; }, 'html' => function ($row) {
        return formatAssignee($row['ticket_assigned_to'], $row['user_name'] ?? '', $row['user_avatar'] ?? '', true);
    }],
    'reply_seconds' => ['label' => __('First reply'), 'type' => 'duration', 'html' => function ($row) {
        if ($row['reply_seconds'] === null) {
            return '<span class="text-body-tertiary">-</span>';
        }
        $late = $row['ticket_response_sla_met'] !== null && intval($row['ticket_response_sla_met']) === 0;
        return '<span class="' . ($late ? 'text-danger-emphasis fw-semibold' : '') . '"' . ($late ? ' title="Missed the response target"' : '') . '>'
            . reportDuration($row['reply_seconds']) . ($late ? ' <i class="fas fa-fw fa-exclamation-circle small"></i>' : '') . '</span>';
    }],
    'resolve_seconds' => ['label' => __('To resolve'), 'type' => 'duration', 'html' => function ($row) {
        $late = $row['ticket_resolution_sla_met'] !== null && intval($row['ticket_resolution_sla_met']) === 0;
        return '<span class="' . ($late ? 'text-danger-emphasis fw-semibold' : '') . '"' . ($late ? ' title="Missed the resolution target"' : '') . '>'
            . reportDuration($row['resolve_seconds']) . ($late ? ' <i class="fas fa-fw fa-exclamation-circle small"></i>' : '') . '</span>';
    }],
    'worked_seconds' => ['label' => __('Time logged'), 'type' => 'hours', 'decimals' => 1],
    'ticket_closed_at' => ['label' => __('Closed'), 'type' => 'date'],
];
$month_columns = ['when' => ['label' => $buckets['unit_title'], 'sort' => false]] + $figure_columns;
$month_rows = [];
foreach ($buckets['keys'] as $i => $key) {
    if (isset($by_bucket[$key])) {
        $month_rows[] = ['when' => $buckets['labels'][$i]] + $by_bucket[$key];
    }
}

if (reportExporting('agents')) {
    reportCsv('Satisfaction by Agent', $agent_columns, $agent_rows, ['agent' => __('Total')] + $overall);
}
if (reportExporting('clients')) {
    reportCsv('Satisfaction by Client', $client_columns, $client_rows, ['client' => __('Total')] + $overall);
}
if (reportExporting('bad')) {
    reportCsv('Bad Ratings', $bad_columns, $bad_rows);
}
if (reportExporting()) {
    reportCsv('Satisfaction by ' . ucfirst($buckets['unit']), $month_columns, $month_rows, ['when' => __('Total')] + $overall);
}

require_once "includes/inc_report_layout.php";

$bad_clients = count(array_unique(array_column($bad_rows, 'ticket_client_id')));
$was = function ($value) use ($period) {
    return $value === null ? '' : __('Was %1$s %2$s', '<span class="text-body">' . reportPercent($value) . '</span>', '<span class="text-body-tertiary">' . escapeHtml($period['compare']) . '</span>');
};

?>

<div class="itflow-report">

    <?php reportHeader('satisfaction', [
        'period' => $period,
        'years' => reportPastYears("SELECT DISTINCT YEAR(ticket_closed_at) AS y FROM tickets WHERE ticket_feedback IS NOT NULL AND ticket_closed_at IS NOT NULL"),
        'client' => $client_id,
        'agent' => $agent_id,
        'export' => ['months' => $buckets['by'], 'agents' => __('By agent'), 'clients' => __('By client'), 'bad' => __('Bad ratings')],
    ]); ?>

    <?= reportStats([
        ['label' => __('Satisfaction'), 'value_html' => reportRateHtml($overall['score']), 'title' => __('Good ratings, of the good and bad'), 'sub' => $was($previous['score'])],
        ['label' => __('Ratings'), 'value' => reportCount($overall['rated']),
            'sub' => $overall['closed'] ? reportPercent($overall['rated_percent']) . ' <span class="text-body-tertiary">' . _n('of %s closed ticket', 'of %s closed tickets', $overall['closed'], reportCount($overall['closed'])) . '</span>' : ''],
        ['label' => __('Rated good'), 'value' => reportCount($overall['good']), 'tone' => $overall['good'] ? 'success' : '',
            'sub' => $previous['rated'] ? reportChangeHtml($overall['good'], $previous['good'], 'up', $period['compare']) : ''],
        ['label' => __('Rated bad'), 'value' => reportCount($overall['bad']), 'tone' => $overall['bad'] ? 'danger' : 'success',
            'sub' => $overall['bad'] ? reportCount($bad_clients) . ' <span class="text-body-tertiary">' . _n('client to follow up', 'clients to follow up', $bad_clients) . '</span>' : '<span class="text-body-tertiary">' . __('Nothing to follow up') . '</span>'],
    ]) ?>

    <?php if (!$overall['rated']) { ?>
        <div class="card"><div class="card-body">
            <?= reportEmpty(__('No ticket closed in this period was rated. Contacts rate a ticket Good or Bad in the client portal once it is closed.'), 'fa-smile', 'secondary') ?>
        </div></div>
    <?php } else { ?>

        <?= reportSectionOpen(__('Ratings over the period'), __('Bars are ratings, the line the share rated good')) ?>
            <div class="card-body">
                <?php
                $keys = $buckets['keys'];
                echo reportChart([
                    'type' => 'bar',
                    'labels' => $buckets['labels'],
                    'stacked' => true,
                    'series' => [
                        ['label' => __('Good'), 'data' => array_map(function ($k) use ($by_bucket) { return $by_bucket[$k]['good'] ?? 0; }, $keys), 'tone' => 'success', 'stack' => 'ratings'],
                        ['label' => __('Bad'), 'data' => array_map(function ($k) use ($by_bucket) { return $by_bucket[$k]['bad'] ?? 0; }, $keys), 'tone' => 'danger', 'stack' => 'ratings'],
                        ['label' => __('Satisfaction'), 'data' => array_map(function ($k) use ($by_bucket) { return $by_bucket[$k]['score'] ?? null; }, $keys), 'tone' => 'accent', 'type' => 'line', 'axis' => 'y2'],
                    ],
                    'format' => 'count',
                    'format2' => 'percent',
                    'max2' => 100,
                ], '', __('Good and bad ratings and the share rated good over the period'));
                ?>
            </div>
        </div>

        <?php if ($drivers) { ?>
            <?= reportSectionOpen(__('What the rating follows'), __('Satisfaction by how the ticket went')) ?>
                <?= reportTable($driver_columns, $driver_rows, ['sortable' => false]) ?>
            </div>
        <?php } ?>

        <?php if (!$agent_id) { ?>
            <?= reportSectionOpen(__('By agent'), __('Who the ticket was assigned to')) ?>
                <?= reportTable($agent_columns, $agent_rows, ['totals' => ['agent' => __('Total')] + $overall, 'totals_html' => ['score' => reportRateHtml($overall['score'])]]) ?>
            </div>
        <?php } ?>

        <?php if (!$client_id) { ?>
            <?= reportSectionOpen(__('By client'), __('The most bad ratings first')) ?>
                <?= reportTable($client_columns, $client_rows, ['limit' => 50, 'export_table' => 'clients', 'totals' => ['client' => __('Total')] + $overall, 'totals_html' => ['score' => reportRateHtml($overall['score'])]]) ?>
            </div>
        <?php } ?>

        <?= reportSectionOpen(__('Bad ratings'), $bad_rows ? __('Newest first - worth a call') : '') ?>
            <?= reportTable($bad_columns, $bad_rows, ['limit' => 100, 'export_table' => 'bad', 'empty' => reportEmpty(__('No bad ratings in this period.'), 'fa-smile', 'success')]) ?>
            <div class="card-footer small text-body-secondary">
                <?= __('Tickets closed in the period. Contacts rate a ticket Good or Bad in the client portal once it is closed; satisfaction is the share rated good.') ?>
                <?= __('Times run on the clock from when the ticket was raised; a red time missed its SLA target.') ?>
                <?php if ($other > 0) { ?>
                    <?= _n('%s rating has another value and is counted as rated, but neither good nor bad.', '%s ratings have another value and are counted as rated, but neither good nor bad.', $other, reportCount($other)) ?>
                <?php } ?>
            </div>
        </div>

    <?php } ?>

</div>

<?php require_once "../../includes/footer.php"; ?>
<script src="/js/reports.js"></script>
