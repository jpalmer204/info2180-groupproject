<?php
require 'config.php';
requireAdmin(); 

// Get all users
$stmt = $pdo->prepare("
    SELECT id, firstname, lastname, email, role, created_at
    FROM users
    ORDER BY created_at DESC
");
$stmt->execute();
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Users - Dolphin CRM</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .role-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .role-admin {
            background: #e8f4fc;
            color: #2980b9;
        }
        
        .role-member {
            background: #e8f6f3;
            color: #27ae60;
        }
    </style>
</head>
<body>

    <div class="header">
        <div class="logo">
            <span>🐬</span>
            <span>Dolphin CRM</span>
        </div>
        
        <div class="nav-links">
            <a href="dashboard.php" class="nav-link">Dashboard</a>
            <a href="add_contact.php" class="nav-link">New Contact</a>
            <?php if (isAdmin()): ?>
                <a href="add_user.php" class="nav-link">New User</a>
                <a href="view_users.php" class="nav-link">View Users</a>
            <?php endif; ?>
        </div>
        
        <div class="user-info">
            <span>Welcome, <?php 
                echo htmlspecialchars($_SESSION['name'], ENT_QUOTES, 'UTF-8'); 
            ?> (<?php 
                echo htmlspecialchars($_SESSION['role'], ENT_QUOTES, 'UTF-8'); 
            ?>)</span>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
    

    <div class="container">
        <div class="page-title">
            <h1>System Users</h1>
            <a href="add_user.php" class="btn btn-success">Add New User</a>
        </div>
        
        <?php if (empty($users)): ?>
            <div class="alert alert-error">
                No users found. <a href="add_user.php">Add the first user</a>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Date Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <strong><?php 
                                        echo htmlspecialchars(
                                            $user['firstname'] . ' ' . $user['lastname'], 
                                            ENT_QUOTES, 
                                            'UTF-8'
                                        ); 
                                    ?></strong>
                                </td>
                                <td>
                                    <a href="mailto:<?php 
                                        echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); 
                                    ?>">
                                        <?php 
                                            echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); 
                                        ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="role-badge <?php 
                                        echo htmlspecialchars(
                                            $user['role'] == 'Admin' ? 'role-admin' : 'role-member',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); 
                                    ?>">
                                        <?php 
                                            echo htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8'); 
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                        echo htmlspecialchars(
                                            date('F j, Y \a\t g:i A', strtotime($user['created_at'])),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); 
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="text-center mt-20">
                <p>Total Users: <strong><?php 
                    echo htmlspecialchars(count($users), ENT_QUOTES, 'UTF-8'); 
                ?></strong></p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>