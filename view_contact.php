<?php
require 'config.php';
requireLogin();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: dashboard.php');
    exit();
}

$contact_id = (int) $_GET['id'];


if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $response = ['success' => false];

    switch ($_GET['ajax']) {
        case 'assign':
            $stmt = $pdo->prepare(
                "UPDATE contacts SET assigned_to = ?, updated_at = NOW() WHERE id = ?"
            );
            $stmt->execute([$_SESSION['user_id'], $contact_id]);
            $response['success'] = true;
            break;

        case 'change_type':
            $stmt = $pdo->prepare("SELECT type FROM contacts WHERE id = ?");
            $stmt->execute([$contact_id]);
            $contact = $stmt->fetch();

            if ($contact) {
                $new_type = ($contact['type'] === 'Sales Lead') ? 'Support' : 'Sales Lead';
                $pdo->prepare(
                    "UPDATE contacts SET type = ?, updated_at = NOW() WHERE id = ?"
                )->execute([$new_type, $contact_id]);

                $response['success'] = true;
                $response['new_type'] = htmlspecialchars($new_type, ENT_QUOTES, 'UTF-8');
                $response['badge_class'] = htmlspecialchars(
                    $new_type === 'Sales Lead' ? 'badge-sales' : 'badge-support',
                    ENT_QUOTES,
                    'UTF-8'
                );
            }
            break;

        case 'add_note':
            if (!empty(trim($_POST['comment']))) {
                $comment = trim($_POST['comment']);
                
                
                $comment_safe = htmlspecialchars($comment, ENT_QUOTES, 'UTF-8');
                
                $stmt = $pdo->prepare(
                    "INSERT INTO notes (contact_id, comment, created_by) VALUES (?, ?, ?)"
                );
                $stmt->execute([$contact_id, $comment_safe, $_SESSION['user_id']]);

                $pdo->prepare("UPDATE contacts SET updated_at = NOW() WHERE id = ?")
                    ->execute([$contact_id]);

                $note_id = $pdo->lastInsertId();
                $stmt = $pdo->prepare("
                    SELECT n.*, u.firstname, u.lastname
                    FROM notes n
                    JOIN users u ON n.created_by = u.id
                    WHERE n.id = ?
                ");
                $stmt->execute([$note_id]);
                $note = $stmt->fetch();

                $response['success'] = true;
                $response['note_html'] = '
                    <div class="note-item">
                        <div class="note-header">
                            <div class="note-author">' 
                                . htmlspecialchars($note['firstname'] . ' ' . $note['lastname'], ENT_QUOTES, 'UTF-8') . 
                            '</div>
                            <div class="note-date">'
                                . htmlspecialchars(date('F j, Y \a\t g:i A', strtotime($note['created_at'])), ENT_QUOTES, 'UTF-8') .
                            '</div>
                        </div>
                        <div class="note-content">'
                            . nl2br(htmlspecialchars($note['comment'], ENT_QUOTES, 'UTF-8')) .
                        '</div>
                    </div>
                ';
            }
            break;
    }

    echo json_encode($response);
    exit();
}


$stmt = $pdo->prepare("
    SELECT c.*,
           creator.firstname AS creator_firstname,
           creator.lastname AS creator_lastname,
           assigned.firstname AS assigned_firstname,
           assigned.lastname AS assigned_lastname
    FROM contacts c
    LEFT JOIN users creator ON c.created_by = creator.id
    LEFT JOIN users assigned ON c.assigned_to = assigned.id
    WHERE c.id = ?
");
$stmt->execute([$contact_id]);
$contact = $stmt->fetch();

if (!$contact) {
    header('Location: dashboard.php');
    exit();
}

$notes_stmt = $pdo->prepare("
    SELECT n.*, u.firstname, u.lastname
    FROM notes n
    JOIN users u ON n.created_by = u.id
    WHERE n.contact_id = ?
    ORDER BY n.created_at DESC
");
$notes_stmt->execute([$contact_id]);
$notes = $notes_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>
<?php echo htmlspecialchars(
    $contact['firstname'] . ' ' . $contact['lastname'] . ' - Dolphin CRM',
    ENT_QUOTES,
    'UTF-8'
); ?>
</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">

<h1>
<?php echo htmlspecialchars(
    $contact['firstname'] . ' ' . $contact['lastname'],
    ENT_QUOTES,
    'UTF-8'
); ?>
</h1>

<span id="type-badge" 
      class="badge <?php echo htmlspecialchars(
          $contact['type'] === 'Sales Lead' ? 'badge-sales' : 'badge-support',
          ENT_QUOTES,
          'UTF-8'
      ); ?>">
<?php echo htmlspecialchars($contact['type'], ENT_QUOTES, 'UTF-8'); ?>
</span>

<div class="contact-actions">
    <button id="assign-btn" class="btn">Assign to Me</button>
    <button id="type-btn" class="btn">
        Change to <?php echo htmlspecialchars(
            $contact['type'] === 'Sales Lead' ? 'Support' : 'Sales Lead',
            ENT_QUOTES,
            'UTF-8'
        ); ?>
    </button>
</div>

<p><strong>Email:</strong>
<?php echo htmlspecialchars($contact['email'], ENT_QUOTES, 'UTF-8'); ?>
</p>

<p><strong>Company:</strong>
<?php echo htmlspecialchars($contact['company'] ?: 'N/A', ENT_QUOTES, 'UTF-8'); ?>
</p>

<p><strong>Assigned To:</strong>
<span id="assigned-name">
<?php
echo $contact['assigned_firstname']
    ? htmlspecialchars(
        $contact['assigned_firstname'] . ' ' . $contact['assigned_lastname'],
        ENT_QUOTES,
        'UTF-8'
      )
    : 'Not assigned';
?>
</span>
</p>

<hr>

<h3>Notes</h3>

<div id="notes-container">
<?php if (empty($notes)): ?>
    <p>No notes yet.</p>
<?php else: ?>
    <?php foreach ($notes as $note): ?>
        <div class="note-item">
            <div class="note-header">
                <div class="note-author">
                    <?php echo htmlspecialchars(
                        $note['firstname'] . ' ' . $note['lastname'],
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>
                </div>
                <div class="note-date">
                    <?php echo htmlspecialchars(
                        date('F j, Y \a\t g:i A', strtotime($note['created_at'])),
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>
                </div>
            </div>
            <div class="note-content">
                <?php echo nl2br(htmlspecialchars($note['comment'], ENT_QUOTES, 'UTF-8')); ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
</div>

<h4>Add Note</h4>
<textarea id="note-comment" class="note-textarea"></textarea>
<button id="save-note" class="btn btn-success">Save Note</button>

</div>

<script>
const contactId = <?php echo (int) $contact_id; ?>;

document.getElementById('assign-btn').addEventListener('click', () => {
    fetch(`view_contact.php?id=${contactId}&ajax=assign`)
        .then(() => {
            document.getElementById('assigned-name').textContent = 'You';
        });
});

document.getElementById('type-btn').addEventListener('click', () => {
    fetch(`view_contact.php?id=${contactId}&ajax=change_type`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const badge = document.getElementById('type-badge');
                badge.textContent = data.new_type;
                badge.className = 'badge ' + data.badge_class;

                document.getElementById('type-btn').textContent =
                    'Change to ' + (data.new_type === 'Sales Lead' ? 'Support' : 'Sales Lead');
            }
        });
});

document.getElementById('save-note').addEventListener('click', () => {
    const comment = document.getElementById('note-comment').value.trim();
    if (!comment) return;

    const data = new FormData();
    data.append('comment', comment);

    fetch(`view_contact.php?id=${contactId}&ajax=add_note`, {
        method: 'POST',
        body: data
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('notes-container')
                .insertAdjacentHTML('afterbegin', data.note_html);
            document.getElementById('note-comment').value = '';
        }
    });
});
</script>

</body>
</html>