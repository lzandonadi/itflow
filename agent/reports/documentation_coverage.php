<?php

/*
 * Documentation Coverage - for each active client, whether the basics an
 * agent reaches for are written down: who to call and who pays, where they
 * are, their hardware (placed and with an owner), networks, logins,
 * documents, domains, services and licenses. A check that cannot apply (no
 * user devices to give owners) is left out of that client's score. Worst
 * covered first, with what is missing linking to where to add it.
 */

require_once "includes/inc_report.php";
reportEnforce('documentation_coverage');

$gaps_only = !empty($_GET['gaps']);
$checks = reportCoverageChecks();
$clients = reportCoverage();

$all = array_values($clients);
usort($all, function ($a, $b) { return $a['score'] <=> $b['score'] ?: strcasecmp($a['client'], $b['client']); });
$rows = $gaps_only ? array_values(array_filter($all, function ($c) { return $c['missing']; })) : $all;

// How many clients miss each check
$missing_counts = array_fill_keys(array_keys($checks), 0);
foreach ($all as $client) {
    foreach ($client['missing'] as $key) {
        $missing_counts[$key]++;
    }
}
arsort($missing_counts);

$columns = [
    'client' => ['label' => __('Client'), 'html' => function ($row) {
        return reportClientCell($row['client_id'], $row['client'], $row['abbr']);
    }],
    'score' => ['label' => __('Coverage'), 'type' => 'percent', 'html' => function ($row) {
        $tone = $row['score'] >= 90 ? 'success' : ($row['score'] >= 70 ? 'warning' : 'danger');
        return '<div class="d-flex align-items-center justify-content-end gap-2"><div class="progress itflow-report-meter" style="width: 5rem" role="presentation">'
            . '<div class="progress-bar bg-' . $tone . '" style="width: ' . round($row['score']) . '%"></div></div>'
            . '<span class="fw-semibold text-' . $tone . '-emphasis" title="' . escapeHtml(_n('%1$d of %2$d check', '%1$d of %2$d checks', $row['applies'], $row['passed'], $row['applies'])) . '">' . reportPercent($row['score']) . '</span></div>';
    }],
    'missing' => ['label' => __('Missing'), 'sort' => false, 'class' => 'itflow-report-text', 'csv' => function ($row) use ($checks) {
        return implode(', ', array_map(function ($key) use ($checks) { return $checks[$key]['label']; }, $row['missing']));
    }, 'html' => function ($row) {
        return reportCoverageMissingHtml($row);
    }],
    'contacts' => ['label' => __('Contacts'), 'type' => 'count'],
    'locations' => ['label' => __('Locations'), 'type' => 'count', 'screen' => false],
    'assets' => ['label' => __('Assets'), 'type' => 'count'],
    'placed_share' => ['label' => __('Placed'), 'type' => 'percent', 'title' => __('Assets with a location'), 'html' => function ($row) {
        return $row['placed_share'] === null ? '<span class="itflow-report-zero">–</span>' : reportRateHtml($row['placed_share'], 95, REPORT_COVERAGE_SHARE);
    }],
    'owned_share' => ['label' => __('Owned'), 'type' => 'percent', 'title' => __('Laptops, desktops, phones and tablets with a contact'), 'html' => function ($row) {
        return $row['owned_share'] === null ? '<span class="itflow-report-zero">–</span>' : reportRateHtml($row['owned_share'], 95, REPORT_COVERAGE_SHARE);
    }],
    'documents' => ['label' => __('Documents'), 'type' => 'count'],
];
// The export carries every check as Yes / No / blank (does not apply)
foreach ($checks as $key => $check) {
    $columns["check_$key"] = ['label' => $check['label'], 'csv_label' => __('Check: %s', $check['label']), 'screen' => false, 'csv' => function ($row) use ($key) {
        return $row['checks'][$key] === null ? '' : ($row['checks'][$key] ? 'Yes' : 'No');
    }];
}

if (reportExporting()) {
    reportCsv('Documentation Coverage', $columns, $rows);
}

require_once "includes/inc_report_layout.php";

$scores = array_column($all, 'score');
$average = $scores ? array_sum($scores) / count($scores) : null;
$complete = count(array_filter($all, function ($c) { return !$c['missing']; }));
$top_gap = array_key_first(array_filter($missing_counts));

?>

<div class="itflow-report">

    <?php reportHeader('documentation_coverage', [
        'subtitle' => __('As of %s', formatDate('now', 'date')),
        'controls' => reportSwitchControl('gaps', $gaps_only, __('Only clients with gaps')),
    ]); ?>

    <?= reportStats([
        ['label' => __('Active clients'), 'value' => reportCount(count($all)), 'sub' => reportCount(count($checks)) . ' <span class="text-body-tertiary">' . __('checks each') . '</span>'],
        ['label' => __('Average coverage'), 'value_html' => $average === null ? '-' : reportRateHtml($average, 90, 70)],
        ['label' => __('Fully documented'), 'value' => reportCount($complete), 'tone' => $complete ? 'success' : '',
            'sub' => count($all) ? reportPercent(reportShare($complete, count($all), 0)) . ' <span class="text-body-tertiary">' . __('of clients') . '</span>' : ''],
        ['label' => __('Most often missing'), 'value' => $top_gap ? $checks[$top_gap]['label'] : 'Nothing', 'tone' => $top_gap ? 'warning' : 'success',
            'sub' => $top_gap ? reportCount($missing_counts[$top_gap]) . ' <span class="text-body-tertiary">' . _n('client', 'clients', $missing_counts[$top_gap]) . '</span>' : ''],
    ]) ?>

    <?php if (array_filter($missing_counts)) { ?>
        <?= reportSectionOpen(__('What is missing most'), __('Clients without it')) ?>
            <div class="card-body">
                <?php $gap_rows = array_filter($missing_counts); ?>
                <?= reportChart([
                    'type' => 'bar',
                    'horizontal' => true,
                    'labels' => array_map(function ($key) use ($checks) { return $checks[$key]['label']; }, array_keys($gap_rows)),
                    'series' => [['label' => __('Clients without it'), 'data' => array_values($gap_rows), 'tone' => 'warning']],
                    'format' => 'count',
                    'legend' => false,
                ], '', __('How many clients are missing each item')) ?>
            </div>
        </div>
    <?php } ?>

    <?= reportSectionOpen(__('By client'), __('Least covered first')) ?>
        <?= reportTable($columns, $rows, ['empty' => reportEmpty($gaps_only ? __('Every client has everything documented.') : __('No active clients.'))]) ?>
        <div class="card-footer small text-body-secondary">
            <?= __('Active clients, counting only what is not archived. Placed and owned pass at %d%%: assets with a location, and laptops, desktops, phones and tablets with a contact; a client without any is not scored on them. Each missing item opens the page to add it on.', REPORT_COVERAGE_SHARE) ?>
        </div>
    </div>

</div>

<?php require_once "../../includes/footer.php"; ?>
<script src="/js/reports.js"></script>
