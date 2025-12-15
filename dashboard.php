<?php
require 'config.php';
requireLogin();

// AJAX endpoint - returns JSON
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json');
    
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
    $params = [];
    
    // Build SQL based on filter
    $sql = "SELECT c.*, 
                   u.firstname as assigned_firstname, 
                   u.lastname as assigned_lastname 
            FROM contacts c 
            LEFT JOIN users u ON c.assigned_to = u.id";
    
    switch ($filter) {
        case 'sales':
            $sql .= " WHERE c.type = 'Sales Lead'";
            break;
        case 'support':
            $sql .= " WHERE c.type = 'Support'";
            break;
        case 'assigned':
            $sql .= " WHERE c.assigned_to = ?";
            $params[] = $_SESSION['user_id'];
            break;
    }
    
    $sql .= " ORDER BY c.created_at DESC";
    
    // Execute query
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $contacts = $stmt->fetchAll();
    
    // Format dates and prepare HTML
    $html = '';
    if (empty($contacts)) {
        $html = '<tr><td colspan="6" class="text-center" style="padding: 40px;">No contacts found.</td></tr>';
    } else {
        foreach ($contacts as $contact) {
            $html .= '<tr>';
            $html .= '<td>';
            $html .= '<strong>' . htmlspecialchars($contact['title'] . ' ' . $contact['firstname'] . ' ' . $contact['lastname']) . '</strong><br>';
            $html .= '<small class="contact-assigned">Created: ' . date('M j, Y', strtotime($contact['created_at'])) . '</small>';
            $html .= '</td>';
            $html .= '<td>';
            $html .= '<a href="mailto:' . htmlspecialchars($contact['email']) . '">' . htmlspecialchars($contact['email']) . '</a>';
            $html .= '</td>';
            $html .= '<td>' . htmlspecialchars($contact['company'] ?: '-') . '</td>';
            $html .= '<td>';
            $html .= '<span class="badge ' . ($contact['type'] == 'Sales Lead' ? 'badge-sales' : 'badge-support') . '">';
            $html .= htmlspecialchars($contact['type']);
            $html .= '</span>';
            $html .= '</td>';
            $html .= '<td>';
            if ($contact['assigned_firstname']) {
                $html .= htmlspecialchars($contact['assigned_firstname'] . ' ' . $contact['assigned_lastname']);
            } else {
                $html .= '<span style="color: #95a5a6;">Not assigned</span>';
            }
            $html .= '</td>';
            $html .= '<td>';
            $html .= '<a href="view_contact.php?id=' . $contact['id'] . '" class="table-link">View</a>';
            $html .= '</td>';
            $html .= '</tr>';
        }
    }
    
    echo json_encode([
        'success' => true,
        'html' => $html,
        'count' => count($contacts)
    ]);
    exit();
}

// Normal page load - get initial contacts
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$filter_label = 'All Contacts';

switch ($filter) {
    case 'sales':
        $filter_label = 'Sales Leads';
        break;
    case 'support':
        $filter_label = 'Support';
        break;
    case 'assigned':
        $filter_label = 'Assigned to Me';
        break;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Dolphin CRM</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .filter-active {
            background: #3498db !important;
            color: white !important;
            border-color: #3498db !important;
        }
        
        .contact-assigned {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 5px;
            display: block;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
            color: #3498db;
        }
        
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Header -->
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
                <a href="view_users.php" class="nav-link">Users</a>
            <?php endif; ?>
        </div>
        
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="container">
        <div class="page-title">
            <h1 id="page-title"><?php echo $filter_label; ?></h1>
            <a href="add_contact.php" class="btn btn-success">Add New Contact</a>
        </div>
        
        <!-- Filter Bar -->
        <div class="filter-bar">
            <strong class="filter-label">Filter by:</strong>
            <a href="#" data-filter="all" 
               class="filter-btn ajax-filter <?php echo $filter == 'all' ? 'filter-active' : ''; ?>">
                All Contacts
            </a>
            <a href="#" data-filter="sales" 
               class="filter-btn ajax-filter <?php echo $filter == 'sales' ? 'filter-active' : ''; ?>">
                Sales Leads
            </a>
            <a href="#" data-filter="support" 
               class="filter-btn ajax-filter <?php echo $filter == 'support' ? 'filter-active' : ''; ?>">
                Support
            </a>
            <a href="#" data-filter="assigned" 
               class="filter-btn ajax-filter <?php echo $filter == 'assigned' ? 'filter-active' : ''; ?>">
                Assigned to Me
            </a>
        </div>
        
        <!-- Loading Indicator -->
        <div class="loading" id="loading">
            <div class="loading-spinner"></div>
            Loading contacts...
        </div>
        
        <!-- Contacts Table -->
        <div id="contacts-container">
            <div class="table-container">
                <table class="table" id="contacts-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Company</th>
                            <th>Type</th>
                            <th>Assigned To</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="contacts-body">
                        <!-- Contacts will be loaded here via AJAX -->
                        <?php
                        // Initial load
                        $sql = "SELECT c.*, 
                                       u.firstname as assigned_firstname, 
                                       u.lastname as assigned_lastname 
                                FROM contacts c 
                                LEFT JOIN users u ON c.assigned_to = u.id";
                        
                        $params = [];
                        if ($filter == 'sales') {
                            $sql .= " WHERE c.type = 'Sales Lead'";
                        } elseif ($filter == 'support') {
                            $sql .= " WHERE c.type = 'Support'";
                        } elseif ($filter == 'assigned') {
                            $sql .= " WHERE c.assigned_to = ?";
                            $params[] = $_SESSION['user_id'];
                        }
                        
                        $sql .= " ORDER BY c.created_at DESC";
                        
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        $contacts = $stmt->fetchAll();
                        
                        if (empty($contacts)): ?>
                            <tr>
                                <td colspan="6" class="text-center" style="padding: 40px;">
                                    No contacts found. <a href="add_contact.php">Add your first contact</a>
                                </td>
                            </tr>
                        <?php else: 
                            foreach ($contacts as $contact): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($contact['title'] . ' ' . $contact['firstname'] . ' ' . $contact['lastname']); ?></strong><br>
                                        <small class="contact-assigned">
                                            Created: <?php echo date('M j, Y', strtotime($contact['created_at'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <a href="mailto:<?php echo htmlspecialchars($contact['email']); ?>">
                                            <?php echo htmlspecialchars($contact['email']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($contact['company'] ?: '-'); ?></td>
                                    <td>
                                        <span class="badge <?php echo $contact['type'] == 'Sales Lead' ? 'badge-sales' : 'badge-support'; ?>">
                                            <?php echo htmlspecialchars($contact['type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($contact['assigned_firstname']): ?>
                                            <?php echo htmlspecialchars($contact['assigned_firstname'] . ' ' . $contact['assigned_lastname']); ?>
                                        <?php else: ?>
                                            <span style="color: #95a5a6;">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="view_contact.php?id=<?php echo $contact['id']; ?>" class="table-link">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; 
                        endif; ?>
                    </tbody>
                </table>
                
                <div class="text-center mt-20" id="contacts-count">
                    <p>Showing <?php echo count($contacts); ?> contact(s)</p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const filterButtons = document.querySelectorAll('.ajax-filter');
        const contactsBody = document.getElementById('contacts-body');
        const pageTitle = document.getElementById('page-title');
        const contactsCount = document.getElementById('contacts-count');
        const loading = document.getElementById('loading');
        
        // Filter button click handler
        filterButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                const filter = this.getAttribute('data-filter');
                
                // Update active button
                filterButtons.forEach(btn => {
                    btn.classList.remove('filter-active');
                });
                this.classList.add('filter-active');
                
                // Update page title
                updatePageTitle(filter);
                
                // Load contacts via AJAX
                loadContacts(filter);
            });
        });
        
        function updatePageTitle(filter) {
            const titles = {
                'all': 'All Contacts',
                'sales': 'Sales Leads',
                'support': 'Support',
                'assigned': 'Assigned to Me'
            };
            pageTitle.textContent = titles[filter] || 'All Contacts';
        }
        
        function loadContacts(filter) {
            // Show loading
            loading.style.display = 'block';
            
            // Create AJAX request
            const xhr = new XMLHttpRequest();
            xhr.open('GET', 'dashboard.php?ajax=1&filter=' + filter, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            
            xhr.onload = function() {
                loading.style.display = 'none';
                
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        
                        if (response.success) {
                            // Update table body
                            contactsBody.innerHTML = response.html;
                            
                            // Update count
                            contactsCount.innerHTML = '<p>Showing ' + response.count + ' contact(s)</p>';
                            
                            // Update URL without reloading page
                            history.pushState({filter: filter}, '', 'dashboard.php?filter=' + filter);
                        } else {
                            contactsBody.innerHTML = '<tr><td colspan="6" class="text-center" style="padding: 40px;">Error loading contacts.</td></tr>';
                        }
                    } catch (e) {
                        console.error('JSON Parse Error:', e);
                        contactsBody.innerHTML = '<tr><td colspan="6" class="text-center" style="padding: 40px;">Error loading contacts.</td></tr>';
                    }
                } else {
                    contactsBody.innerHTML = '<tr><td colspan="6" class="text-center" style="padding: 40px;">Error loading contacts.</td></tr>';
                }
            };
            
            xhr.onerror = function() {
                loading.style.display = 'none';
                contactsBody.innerHTML = '<tr><td colspan="6" class="text-center" style="padding: 40px;">Network error. Please try again.</td></tr>';
            };
            
            xhr.send();
        }
        
        // Handle browser back/forward buttons
        window.addEventListener('popstate', function(event) {
            if (event.state && event.state.filter) {
                const filter = event.state.filter;
                
                // Update active button
                filterButtons.forEach(btn => {
                    btn.classList.remove('filter-active');
                    if (btn.getAttribute('data-filter') === filter) {
                        btn.classList.add('filter-active');
                    }
                });
                
                // Update page title
                updatePageTitle(filter);
                
                // Load contacts
                loadContacts(filter);
            }
        });
    });
    </script>
</body>
</html>