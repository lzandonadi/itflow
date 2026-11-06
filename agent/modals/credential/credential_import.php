<?php

require_once '../../../includes/modal_header.php';

$client_id = intval($_GET['client_id'] ?? 0);

ob_start();

?>

<div class="modal-header bg-dark">
	<h5 class="modal-title"><i class="fas fa-fw fa-key me-2"></i><?= __('Import Credentials') ?></h5>
	<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
	<input type="hidden" name="client_id" value="<?= $client_id ?>">
	<div class="modal-body">
		<p><strong><?= __('Format csv file with headings & data:') ?></strong><br><?= __('Name, Description, Username, Password, TOTP, URI') ?></p>
		<hr>
		<div class="mb-3 my-4">
			<input type="file" class="form-control" name="file" accept=".csv" required>
		</div>
		<hr>
		<div><?= __('Download') ?> <a class="text-bold" href="post.php?download_credentials_csv_template"><?= __('sample csv template') ?></a></div>
	</div>
	<div class="modal-footer">
		<button type="submit" name="import_credentials_csv" class="btn btn-primary"><i class="fa fa-upload me-2"></i><?= __('Import') ?></button>
		<button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i><?= __('Cancel') ?></button>
	</div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
