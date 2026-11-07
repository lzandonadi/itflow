<?php 

require_once "includes/inc_all.php";

?>
	
<!-- Breadcrumbs-->
<ol class="breadcrumb">
  <li class="breadcrumb-item">
    <a href="index.php"><?= __('Dashboard') ?></a>
  </li>
  <li class="breadcrumb-item active"><?= __('Blank Page') ?></li>
</ol>

<!-- Page Content -->
<h1><?= __('Blank Page') ?></h1>
<hr>

<meta http-equiv="refresh" content="0;url=<?= escapeHtml($config_start_page) ?>">

<?php
require_once "../includes/footer.php";