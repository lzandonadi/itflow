<?php

require_once '../../../includes/modal_header.php';

enforceUserPermission('module_support', 2);

$task_id = intval($_GET['id']);

$sql = mysqli_query($mysqli, "SELECT task_name, ticket_client_id FROM tasks
    LEFT JOIN tickets ON task_ticket_id = ticket_id
    WHERE task_id = $task_id
    LIMIT 1"
);

$row = mysqli_fetch_assoc($sql);
if (!$row) {
    echo json_encode(['error' => __('That task no longer exists.')]);
    exit;
}
$task_name = escapeHtml($row['task_name']);
$client_id = intval($row['ticket_client_id']);

if ($client_id) {
    enforceClientAccess();
}

ob_start();

?>

<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-shield-alt me-2"></i><?= __('Require approval') ?> <small class="ms-1 opacity-75"><?= $task_name ?></small></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="task_id" value="<?= $task_id ?>">

    <div class="modal-body">

        <div class="mb-3">
            <label><?= __('Approval comes from') ?> <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-layer-group"></i></span>
                <select class="form-select" name="approval_scope" id="approval_scope" required>
                    <option value=""><?= __('- Pick one -') ?></option>
                    <option value="internal"><?= __('Our team') ?></option>
                    <option value="client"><?= __('The client') ?></option>
                </select>
            </div>
        </div>


        <div class="mb-3 d-none" id="approval_type_wrapper">
            <label><?= __('Who can approve?') ?> <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-user-check"></i></span>
                <select class="form-select" name="approval_type" id="approval_type" required>
                    <!-- JS -->
                </select>
            </div>
        </div>


        <div class="mb-3 d-none" id="specific_user_wrapper">
            <label><?= __('Technician') ?> <strong class="text-danger">*</strong></label>
            <div class="input-group">
                    <span class="input-group-text"><i class="fa fa-fw fa-user-circle"></i></span>
                <select class="form-select select2" name="approval_required_user_id" id="specific_user_select">
                    <option value=""><?= __('Select user...') ?></option>
                </select>
            </div>
        </div>


    </div>

    <div class="modal-footer">
        <button type="submit" name="add_ticket_task_approver" class="btn btn-primary text-bold"><i class="fa fa-check me-2"></i><?= __('Require approval') ?></button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i><?= __('Cancel') ?></button>
    </div>

</form>


<!-- JS to make the correct boxes appear depending on if internal/client approval) -->
<script>
    (function () {
        var scopeSelect = document.getElementById('approval_scope');
        var typeSelect = document.getElementById('approval_type');
        var typeWrapper = document.getElementById('approval_type_wrapper');
        var userWrapper = document.getElementById('specific_user_wrapper');
        var userSelect = document.getElementById('specific_user_select');

        if (!scopeSelect || !typeSelect) {
            return;
        }

        function setOptions(select, pairs) {
            select.innerHTML = '';
            pairs.forEach(function (pair) {
                // new Option() assigns text, so nothing here is parsed as markup
                select.appendChild(new Option(pair[1], pair[0]));
            });
        }

        scopeSelect.addEventListener('change', function () {
            var scope = this.value;

            setOptions(typeSelect, []);
            userWrapper.classList.add('d-none');

            if (!scope) {
                typeWrapper.classList.add('d-none');
                return;
            }

            typeWrapper.classList.remove('d-none');

            if (scope === 'internal') {
                setOptions(typeSelect, [
                    ['', __('Select...')],
                    ['any', __('Any other technician')],
                    ['specific', __('A specific technician')]
                ]);
            }

            if (scope === 'client') {
                setOptions(typeSelect, [
                    ['', __('Select...')],
                    ['any', __('Ticket contact')],
                    ['technical', __('Technical contacts')],
                    ['billing', __('Billing contacts')]
                ]);
            }
        });

        // Specific user (internal only for now)
        typeSelect.addEventListener('change', function () {
            var type = this.value;
            var scope = scopeSelect.value;

            if (type !== 'specific' || scope !== 'internal') {
                userWrapper.classList.add('d-none');
                return;
            }

            userWrapper.classList.remove('d-none');
            setOptions(userSelect, [['', 'Loading...']]);

            fetch('ajax.php?get_internal_users=true', {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            })
                .then(function (res) {
                    if (!res.ok) {
                        throw new Error('HTTP ' + res.status);
                    }
                    return res.json();
                })
                .then(function (data) {
                    var pairs = [['', __('Select user...')]];
                    data.users.forEach(function (u) {
                        pairs.push([u.user_id, u.user_name]);
                    });
                    setOptions(userSelect, pairs);
                })
                .catch(function () {
                    setOptions(userSelect, [['', __('Failed to load users')]]);
                });
        });
    })();
</script>

<?php

require_once '../../../includes/modal_footer.php';
