<?php
require 'config.php';
requireLogin();

$success = '';
$errors = [];

// Get all users for dropdown
$users = $pdo->query("SELECT id, firstname, lastname FROM users ORDER BY firstname")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $email = trim($_POST['email']);
    $telephone = trim($_POST['telephone']);
    $company = trim($_POST['company']);
    $type = $_POST['type'];
    $assigned_to = $_POST['assigned_to'];
    
    // Validation
    if (empty($firstname)) $errors[] = 'First name is required';
    if (empty($lastname)) $errors[] = 'Last name is required';
    if (empty($email)) $errors[] = 'Email is required';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';
    
    // Insert if no errors
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO contacts 
                (title, firstname, lastname, email, telephone, company, type, assigned_to, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $title, $firstname, $lastname, $email, 
                $telephone, $company, $type, $assigned_to, 
                $_SESSION['user_id']
            ]);
            
            $success = 'Contact added successfully!';
            $_POST = []; // Clear form
            
        } catch(PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Contact - Dolphin CRM</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="logo">
            <span>🐬</span>
            <span>Dolphin CRM</span>
        </div>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="container">
        <div class="page-title">
            <h1>Add New Contact</h1>
            <a href="dashboard.php" class="btn">Back to Dashboard</a>
        </div>
        
        <!-- Messages -->
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <!-- Form -->
        <div class="form-container">
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-col">
                        <label class="form-label">Title *</label>
                        <select name="title" class="form-control" required>
                            <option value="">Select Title</option>
                            <option value="Mr" <?php echo isset($_POST['title']) && $_POST['title'] == 'Mr' ? 'selected' : ''; ?>>Mr</option>
                            <option value="Mrs" <?php echo isset($_POST['title']) && $_POST['title'] == 'Mrs' ? 'selected' : ''; ?>>Mrs</option>
                            <option value="Ms" <?php echo isset($_POST['title']) && $_POST['title'] == 'Ms' ? 'selected' : ''; ?>>Ms</option>
                            <option value="Dr" <?php echo isset($_POST['title']) && $_POST['title'] == 'Dr' ? 'selected' : ''; ?>>Dr</option>
                            <option value="Prof" <?php echo isset($_POST['title']) && $_POST['title'] == 'Prof' ? 'selected' : ''; ?>>Prof</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <label class="form-label">First Name *</label>
                        <input type="text" name="firstname" class="form-control" 
                               value="<?php echo isset($_POST['firstname']) ? htmlspecialchars($_POST['firstname']) : ''; ?>" 
                               required>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Last Name *</label>
                        <input type="text" name="lastname" class="form-control" 
                               value="<?php echo isset($_POST['lastname']) ? htmlspecialchars($_POST['lastname']) : ''; ?>" 
                               required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                               required>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Telephone</label>
                        <input type="tel" name="telephone" class="form-control" 
                               value="<?php echo isset($_POST['telephone']) ? htmlspecialchars($_POST['telephone']) : ''; ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <label class="form-label">Company</label>
                        <input type="text" name="company" class="form-control" 
                               value="<?php echo isset($_POST['company']) ? htmlspecialchars($_POST['company']) : ''; ?>">
                    </div>
                    <div class="form-col">
                        <label class="form-label">Type *</label>
                        <select name="type" class="form-control" required>
                            <option value="">Select Type</option>
                            <option value="Sales Lead" <?php echo isset($_POST['type']) && $_POST['type'] == 'Sales Lead' ? 'selected' : ''; ?>>Sales Lead</option>
                            <option value="Support" <?php echo isset($_POST['type']) && $_POST['type'] == 'Support' ? 'selected' : ''; ?>>Support</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <label class="form-label">Assigned To *</label>
                        <select name="assigned_to" class="form-control" required>
                            <option value="">Select User</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" 
                                    <?php echo isset($_POST['assigned_to']) && $_POST['assigned_to'] == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-success">Save Contact</button>
                    <a href="dashboard.php" class="btn">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>