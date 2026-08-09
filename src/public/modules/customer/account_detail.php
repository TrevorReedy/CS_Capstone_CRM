<?php

require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use App\Core\Auth;
use App\Core\Permissions;
use App\Core\Database;

Auth::requireLogin();

// Reject state-changing (POST) requests without a valid CSRF token.
require_once __DIR__ . '/../../../app/Middleware/csrf.php';

// Viewing the page.
if (!Permissions::can('customers.view')) {
    layout_deny();
    exit;
}

/*
|--------------------------------------------------------------------------
| WRITE AUTHORIZATION
|--------------------------------------------------------------------------
| This page dispatches on a hidden POST marker rather than through the module
| controller, so each write needs its own gate. Anything that posts a marker not
| listed here is rejected outright — deny by default, so a new form can't ship
| unprotected by accident.
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $writePermissions = [
        'update_account'     => 'customers.edit',
        'delete_account'     => 'customers.delete',
        'add_contact'        => 'contacts.create',
        'update_contact'     => 'contacts.edit',
        'delete_contact'     => 'contacts.delete',
        'add_interaction'    => 'interactions.create',
        'update_interaction' => 'interactions.edit',
        'delete_interaction' => 'interactions.delete',
    ];

    $marker = null;
    foreach (array_keys($writePermissions) as $candidate) {
        if (isset($_POST[$candidate])) {
            $marker = $candidate;
            break;
        }
    }

    if ($marker === null || !Permissions::can($writePermissions[$marker])) {
        layout_deny();
        exit;
    }
}

$accountId = (int)($_GET['id'] ?? 0);
$editMode  = isset($_GET['edit']);

$db = Database::connection();

/*
|--------------------------------------------------------------------------
| UPDATE ACCOUNT
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_account'])) {

    $stmt = $db->prepare("
        UPDATE accounts
        SET account_name=:account_name,
            email=:email,
            phone=:phone,
            address=:address,
            industry=:industry,
            source=:source,
            tags=:tags
        WHERE id=:id
    ");

    $stmt->execute([
        'account_name' => trim($_POST['account_name']),
        'email'        => trim($_POST['email']),
        'phone'        => trim($_POST['phone']),
        'address'      => trim($_POST['address']),
        'industry'     => trim($_POST['industry']),
        'source'       => trim($_POST['source']),
        'tags'         => trim($_POST['tags']),
        'id'           => $accountId
    ]);

    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Account updated.'];
    header("Location: account_detail.php?id=$accountId");
    exit;
}

/*
|--------------------------------------------------------------------------
| ADD CONTACT
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_contact'])) {

    $stmt = $db->prepare("
        INSERT INTO contacts (
            account_id,
            first_name,
            last_name,
            email,
            phone
        )
        VALUES (
            :account_id,
            :first_name,
            :last_name,
            :email,
            :phone
        )
    ");

    $stmt->execute([
        'account_id' => $accountId,
        'first_name' => trim($_POST['first_name']),
        'last_name'  => trim($_POST['last_name']),
        'email'      => trim($_POST['email']),
        'phone'      => trim($_POST['phone'])
    ]);

    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Contact added.'];
    header("Location: account_detail.php?id=$accountId");
    exit;
}

/*
|--------------------------------------------------------------------------
| UPDATE CONTACT
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_contact'])) {

    $stmt = $db->prepare("
        UPDATE contacts
        SET first_name=:first_name,
            last_name=:last_name,
            email=:email,
            phone=:phone
        WHERE id=:id AND account_id=:account_id
    ");

    $stmt->execute([
        'id'         => $_POST['contact_id'],
        'account_id' => $accountId,
        'first_name' => trim($_POST['first_name']),
        'last_name'  => trim($_POST['last_name']),
        'email'      => trim($_POST['email']),
        'phone'      => trim($_POST['phone'])
    ]);

    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Contact updated.'];
    header("Location: account_detail.php?id=$accountId");
    exit;
}

/*
|--------------------------------------------------------------------------
| DELETE CONTACT
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_contact'])) {

    try {
        $stmt = $db->prepare("
            DELETE FROM contacts
            WHERE id=:id AND account_id=:account_id
        ");

        $stmt->execute([
            'id' => $_POST['contact_id'],
            'account_id' => $accountId
        ]);

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Contact deleted.'];
    } catch (\PDOException $e) {
        // Blocked by a foreign key — the contact is still used by a campaign audience.
        $_SESSION['flash'] = ['type' => 'error', 'message' =>
            'This contact cannot be deleted because it is still part of a campaign audience. '
            . 'Remove it from that campaign first.'];
    }

    header("Location: account_detail.php?id=$accountId");
    exit;
}

/*
|--------------------------------------------------------------------------
| ADD INTERACTION
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_interaction'])) {

    $stmt = $db->prepare("
        INSERT INTO interactions (
            account_id,
            user_id,
            interaction_type,
            interaction_date,
            interaction_subject,
            notes
        )
        VALUES (
            :account_id,
            :user_id,
            :interaction_type,
            NOW(),
            :interaction_subject,
            :notes
        )
    ");

    $stmt->execute([
        'account_id' => $accountId,
        'user_id' => $_SESSION['user']['id'],
        'interaction_type' => strtolower(trim($_POST['interaction_type'] ?? '')), // match ENUM('call','email','note','meeting')
        'interaction_subject' => $_POST['subject'] ?? '',
        'notes' => $_POST['notes'] ?? ''
    ]);

    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Interaction logged.'];
    header("Location: account_detail.php?id=$accountId");
    exit;
}

/*
|--------------------------------------------------------------------------
| UPDATE INTERACTION
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_interaction'])) {

    $stmt = $db->prepare("
        UPDATE interactions
        SET interaction_type=:type,
            interaction_subject=:subject,
            notes=:notes
        WHERE id=:id AND account_id=:account_id
    ");

    $stmt->execute([
        'type' => strtolower(trim($_POST['interaction_type'])), // match ENUM casing
        'subject' => $_POST['subject'],
        'notes' => $_POST['notes'],
        'id' => $_POST['interaction_id'],
        'account_id' => $accountId
    ]);

    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Interaction updated.'];
    header("Location: account_detail.php?id=$accountId");
    exit;
}

/*
|--------------------------------------------------------------------------
| DELETE INTERACTION
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_interaction'])) {

    $stmt = $db->prepare("
        DELETE FROM interactions
        WHERE id=:id AND account_id=:account_id
    ");

    $stmt->execute([
        'id' => $_POST['interaction_id'],
        'account_id' => $accountId
    ]);

    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Interaction deleted.'];
    header("Location: account_detail.php?id=$accountId");
    exit;
}

/*
|--------------------------------------------------------------------------
| DELETE ACCOUNT
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {

    // Contacts, interactions and campaign-audience rows all cascade from accounts
    // (see schema.sql). This used to hand-delete interactions first, which meant a
    // delete blocked by the RFQ foreign key had already destroyed the interaction
    // history — with nothing to roll it back. One statement in one transaction
    // now either removes the whole customer or leaves it untouched.
    $db->beginTransaction();
    try {
        $db->prepare("DELETE FROM accounts WHERE id=:id")
           ->execute(['id' => $accountId]);

        $db->commit();
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Customer deleted.'];
        header("Location: accounts.php");
        exit;

    } catch (\PDOException $e) {
        $db->rollBack();
        // Blocked by a foreign key — the account still has linked RFQs.
        error_log('Account delete blocked (id ' . $accountId . '): ' . $e->getMessage());
        $_SESSION['flash'] = ['type' => 'error', 'message' =>
            'This customer cannot be deleted because it still has linked RFQs. '
            . 'Remove or reassign those first.'];
    }
}

/*
|--------------------------------------------------------------------------
| LOAD DATA
|--------------------------------------------------------------------------
*/
$stmt = $db->prepare("SELECT * FROM accounts WHERE id=:id");
$stmt->execute(['id'=>$accountId]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) die("Account not found");

$stmt = $db->prepare("SELECT * FROM contacts WHERE account_id=:id ORDER BY last_name");
$stmt->execute(['id'=>$accountId]);
$contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->prepare("SELECT * FROM interactions WHERE account_id=:id ORDER BY interaction_date DESC");
$stmt->execute(['id'=>$accountId]);
$interactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

layout_open();
?>


<section class="card">

<div class="page-header">
    <h1><?= htmlspecialchars($account['account_name']) ?></h1>
    <div class="header-actions">
        <a href="accounts.php" class="button btn-ghost">
            &#8592; Back
        </a>
        <a href="account_pdf.php?id=<?= $accountId ?>"
        class="button button-primary">
        Download PDF
        </a>
        <?php if (!$editMode): ?>
        <a class="button button-primary" href="?id=<?= $accountId ?>&edit=1">Edit Account</a>
        <form method="POST" style="display:inline;margin:0;"
              onsubmit="return confirm('Delete this customer and all its contacts and interactions? This cannot be undone.');">
            <?= App\Core\Csrf::field() ?>
            <button type="submit" name="delete_account" class="button button-danger">Delete</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<form method="POST">
<?= App\Core\Csrf::field() ?>

<table class="table">

<?php
function field($label,$name,$value,$editMode){
    $value = (string)($value ?? '');
    echo "<tr><th>$label</th><td>";

    if ($editMode) {
        echo "<input name='$name' class='form-control' value='".htmlspecialchars($value)."'>";
    } else {
        echo htmlspecialchars($value);
    }

    echo "</td></tr>";
}

field("Account Name","account_name",$account['account_name'],$editMode);
field("Email","email",$account['email'],$editMode);
field("Phone","phone",$account['phone'],$editMode);
field("Address","address",$account['address'],$editMode);
field("Industry","industry",$account['industry'],$editMode);
field("Source","source",$account['source'],$editMode);
field("Tags","tags",$account['tags'],$editMode);
?>

</table>

<?php if ($editMode): ?>
<div class="form-actions">
    <button name="update_account" class="button button-primary">Save</button>
    <a href="?id=<?= $accountId ?>" class="button btn-ghost">Cancel</a>
</div>
<?php endif; ?>

</form>

</section>

<!-- CONTACTS -->
<section class="card">

<h2>Contacts</h2>

<!-- ADD CONTACT -->
<form method="POST" class="form">
    <?= App\Core\Csrf::field() ?>
    <div class="form-row">
        <div class="form-group">
            <label for="c-first" class="form-label">First Name <span class="form-required">*</span></label>
            <input id="c-first" name="first_name" class="form-control" placeholder="First Name" required>
        </div>
        <div class="form-group">
            <label for="c-last" class="form-label">Last Name <span class="form-required">*</span></label>
            <input id="c-last" name="last_name" class="form-control" placeholder="Last Name" required>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label for="c-email" class="form-label">Email</label>
            <input id="c-email" name="email" class="form-control" placeholder="Email">
        </div>
        <div class="form-group">
            <label for="c-phone" class="form-label">Phone</label>
            <input id="c-phone" name="phone" class="form-control" placeholder="Phone">
        </div>
    </div>
    <div class="form-actions">
        <button name="add_contact" class="button button-primary">Add Contact</button>
    </div>
</form>

<br>

<?php foreach ($contacts as $c): ?>

<div class="card-box">

<?php if (isset($_GET['edit_contact']) && $_GET['edit_contact'] == $c['id']): ?>

<form method="POST" class="form">
    <?= App\Core\Csrf::field() ?>
    <input type="hidden" name="contact_id" value="<?= $c['id'] ?>">

    <div class="form-row">
        <div class="form-group">
            <label class="form-label">First Name</label>
            <input name="first_name" class="form-control" value="<?= htmlspecialchars($c['first_name']) ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Last Name</label>
            <input name="last_name" class="form-control" value="<?= htmlspecialchars($c['last_name']) ?>">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Email</label>
            <input name="email" class="form-control" value="<?= htmlspecialchars($c['email']) ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Phone</label>
            <input name="phone" class="form-control" value="<?= htmlspecialchars($c['phone']) ?>">
        </div>
    </div>

    <div class="form-actions">
        <button name="update_contact" class="button button-primary">Save</button>
        <a href="?id=<?= $accountId ?>" class="button btn-ghost">Cancel</a>
    </div>
</form>

<?php else: ?>

<strong><?= htmlspecialchars($c['first_name'].' '.$c['last_name']) ?></strong>
<div class="text-muted"><?= htmlspecialchars($c['email']) ?></div>
<div class="text-muted"><?= htmlspecialchars($c['phone']) ?></div>

<div class="inline-actions">

    <a href="?id=<?= $accountId ?>&edit_contact=<?= $c['id'] ?>"
       class="button btn-ghost" style="font-size:0.78rem;padding:3px 10px;">Edit</a>

    <form method="POST" style="display:inline;">
        <?= App\Core\Csrf::field() ?>
        <input type="hidden" name="contact_id" value="<?= $c['id'] ?>">
        <button name="delete_contact" class="button button-danger" style="font-size:0.78rem;padding:3px 10px;"
                onclick="return confirm('Delete contact?')">Delete</button>
    </form>

</div>

<?php endif; ?>

</div>

<?php endforeach; ?>

</section>

<!-- INTERACTIONS -->
<section class="card">

<h2>Log Interaction</h2>

<form method="POST" class="form">
    <?= App\Core\Csrf::field() ?>

    <div class="form-group">
        <label for="i-subject" class="form-label">Subject</label>
        <input id="i-subject" name="subject" class="form-control" placeholder="Subject">
    </div>

    <div class="form-group">
        <label for="i-type" class="form-label">Type</label>
        <select id="i-type" name="interaction_type" class="form-control">
            <option value="call">Call</option>
            <option value="email">Email</option>
            <option value="meeting">Meeting</option>
            <option value="note">Note</option>
        </select>
    </div>

    <div class="form-group">
        <label for="i-notes" class="form-label">Notes</label>
        <textarea id="i-notes" name="notes" rows="5" class="form-control"></textarea>
    </div>

    <div class="form-actions">
        <button name="add_interaction" class="button button-primary">Save Interaction</button>
    </div>

</form>

<br>

<h2>Interaction History</h2>

<?php foreach ($interactions as $i): ?>

<div class="card-box">

<?php if (isset($_GET['edit_interaction']) && $_GET['edit_interaction'] == $i['id']): ?>

<form method="POST" class="form">
    <?= App\Core\Csrf::field() ?>
    <input type="hidden" name="interaction_id" value="<?= $i['id'] ?>">

    <div class="form-group">
        <label class="form-label">Subject</label>
        <input name="subject" class="form-control" value="<?= htmlspecialchars($i['interaction_subject']) ?>">
    </div>

    <div class="form-group">
        <label class="form-label">Type</label>
        <select name="interaction_type" class="form-control">
            <option value="call"    <?= $i['interaction_type']=='call'?'selected':'' ?>>Call</option>
            <option value="email"   <?= $i['interaction_type']=='email'?'selected':'' ?>>Email</option>
            <option value="meeting" <?= $i['interaction_type']=='meeting'?'selected':'' ?>>Meeting</option>
            <option value="note"    <?= $i['interaction_type']=='note'?'selected':'' ?>>Note</option>
        </select>
    </div>

    <div class="form-group">
        <label class="form-label">Notes</label>
        <textarea name="notes" rows="4" class="form-control"><?= htmlspecialchars($i['notes']) ?></textarea>
    </div>

    <div class="form-actions">
        <button name="update_interaction" class="button button-primary">Save</button>
        <a href="?id=<?= $accountId ?>" class="button btn-ghost">Cancel</a>
    </div>
</form>

<?php else: ?>

<strong><?= htmlspecialchars(ucfirst($i['interaction_type'])) ?></strong>
<div class="text-muted"><?= htmlspecialchars($i['interaction_subject']) ?></div>
<div><?= nl2br(htmlspecialchars($i['notes'])) ?></div>

<div class="inline-actions">

    <a href="?id=<?= $accountId ?>&edit_interaction=<?= $i['id'] ?>"
       class="button btn-ghost" style="font-size:0.78rem;padding:3px 10px;">Edit</a>

    <form method="POST" style="display:inline;">
        <?= App\Core\Csrf::field() ?>
        <input type="hidden" name="interaction_id" value="<?= $i['id'] ?>">
        <button name="delete_interaction" class="button button-danger" style="font-size:0.78rem;padding:3px 10px;"
                onclick="return confirm('Delete interaction?')">Delete</button>
    </form>

</div>

<?php endif; ?>

</div>

<?php endforeach; ?>

</section>

<?php layout_close(); ?>