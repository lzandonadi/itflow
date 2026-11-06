<?php

/*
 * Import interfaces onto an asset from a CSV - checked like New Interface,
 * the network matched by name or subnet.
 */

require_once '../../../includes/modal_header.php';

$asset = interfaceAssetForModal($_GET['asset_id'] ?? 0);
$asset_id = intval($asset['asset_id']);

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title text-truncate"><i class="fa fa-fw fa-upload me-2"></i><?= __('Import interfaces to') ?> <strong><?= escapeHtml($asset['asset_name']) ?></strong></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="asset_id" value="<?= $asset_id ?>">
    <div class="modal-body">
        <p><strong><?= __('Format csv file with headings &amp; data:') ?></strong><br><?= __('Name, Description, Type, MAC, IP, NAT IP, IPv6, Network, VLANs') ?></p>
        <p class="text-secondary mb-0"><?= __('An export opens straight back in. VLANs may be left off. IP is an address, DHCP or empty; Network is the network\'s name or subnet. Rows with an address outside their network, or a MAC that is not one, are skipped and listed; names the asset has already are skipped.') ?></p>
        <hr>
        <div class="mb-3 my-4">
            <input type="file" class="form-control" name="file" accept=".csv" required>
        </div>
        <hr>
        <div><?= __('Download:') ?> <a class="text-bold" href="post.php?download_client_asset_interfaces_csv_template=<?= $asset_id ?>"><?= __('sample csv template') ?></a></div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="import_client_asset_interfaces_csv" class="btn btn-primary text-bold"><i class="fa fa-upload me-2"></i><?= __('Import') ?></button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i><?= __('Cancel') ?></button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
