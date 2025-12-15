<?php
require 'config.php';
requireAdmin(); // Only admins can access

// AJAX endpoint for form submission
if (isset($_GET['ajax']) && $_GET['ajax'] == 'submit') {
    header('Content-Type: application/json');
    
    $errors = [];
    $success = false;
    $message = '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Get form data
        $firstname = htmlspecialchars(trim($_POST['firstname'] ?? ''), ENT_QUOTES, 'UTF-8');
        $lastname = htmlspecialchars(trim($_POST['lastname'] ?? ''), ENT_QUOTES, 'UTF-8');
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $role = htmlspecialchars($_POST['role'] ?? '', ENT_QUOTES, 'UTF-8');
        
        // Validation
        if (empty($firstname)) $errors[] = 'First name is required';
        if (empty($lastname)) $errors[] = 'Last name is required';
        if (empty($email)) $errors[] = 'Email is required';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';
        if (empty($password)) $errors[] = 'Password is required';
        if (empty($role)) $errors[] = 'Role is required';
        
        // Password validation regex: at least 8 chars, 1 number, 1 capital letter
        if (!empty($password) && !preg_match('/^(?=.*[A-Z])(?=.*\d).{8,}$/', $password)) {
            $errors[] = 'Password must be at least 8 characters with at least 1 number and 1 capital letter';
        }
        
        // Confirm password
        if ($password !== $confirm_password) {
            $errors[] = 'Passwords do not match';
        }
        
        // Check if email already exists
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'Email already exists';
            }
        }
        
        // If no errors, insert user
        if (empty($errors)) {
            try {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("
                    INSERT INTO users (firstname, lastname, email, password, role) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([$firstname, $lastname, $email, $hashed_password, $role]);
                
                $user_id = $pdo->lastInsertId();
                $success = true;
                $message = 'User added successfully!';
                
                // Return user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();

                if ($user) {
                    $user['firstname'] = htmlspecialchars($user['firstname'], ENT_QUOTES, 'UTF-8');
                    $user['lastname'] = htmlspecialchars($user['lastname'], ENT_QUOTES, 'UTF-8');
                    $user['email'] = htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8');
                    $user['role'] = htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8');
                }
                
                
            } catch(PDOException $e) {
                $errors[] = 'Database error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            }
        }
    } else {
        $errors[] = 'Invalid request method';
    }
    
    $sanitized_errors = [];
    foreach ($errors as $error) {
        $sanitized_errors[] = htmlspecialchars($error, ENT_QUOTES, 'UTF-8');
    }
    
    echo json_encode([
        'success' => $success,
        'message' => htmlspecialchars($message, ENT_QUOTES, 'UTF-8'),
        'errors' => $sanitized_errors,
        'user' => $user ?? null,
        'redirect_url' => $success ? 'view_users.php' : null
    ]);
    exit();
}

$users = $pdo->query("SELECT id, firstname, lastname FROM users ORDER BY firstname")->fetchAll();


$sanitized_users = [];
foreach ($users as $user) {
    $sanitized_users[] = [
        'id' => (int)$user['id'],
        'firstname' => htmlspecialchars($user['firstname'], ENT_QUOTES, 'UTF-8'),
        'lastname' => htmlspecialchars($user['lastname'], ENT_QUOTES, 'UTF-8')
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New User - Dolphin CRM</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .ajax-message {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 6px;
            color: white;
            z-index: 1000;
            opacity: 0;
            transform: translateY(-20px);
            transition: all 0.3s;
            max-width: 400px;
        }
        
        .ajax-message.show {
            opacity: 1;
            transform: translateY(0);
        }
        
        .ajax-message.success {
            background: #27ae60;
        }
        
        .ajax-message.error {
            background: #e74c3c;
        }
        
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            z-index: 999;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }
        
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 15px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .form-disabled {
            opacity: 0.6;
            pointer-events: none;
        }
        
        .success-actions {
            margin-top: 20px;
            padding: 20px;
            background: #d4edda;
            border-radius: 6px;
            border: 1px solid #c3e6cb;
            display: none;
        }
        
        .success-actions.show {
            display: block;
        }
        
        .password-rules {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin: 10px 0;
            font-size: 14px;
            color: #666;
            border: 1px solid #e9ecef;
        }
        
        .password-rules ul {
            margin-left: 20px;
            margin-top: 5px;
        }
        
        .password-rules li.valid {
            color: #27ae60;
        }
        
        .password-rules li.invalid {
            color: #e74c3c;
        }
        
        .password-strength {
            margin-top: 10px;
            height: 5px;
            background: #ddd;
            border-radius: 3px;
            overflow: hidden;
        }
        
        .strength-bar {
            height: 100%;
            width: 0%;
            background: #e74c3c;
            transition: width 0.3s, background 0.3s;
        }
        
        .password-match {
            font-size: 14px;
            margin-top: 5px;
            display: none;
        }
        
        .password-match.valid {
            color: #27ae60;
            display: block;
        }
        
        .password-match.invalid {
            color: #e74c3c;
            display: block;
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loading-overlay">
        <div class="loading-spinner"></div>
        <p>Creating user...</p>
    </div>
    
    <!-- AJAX Message -->
    <div id="ajax-message" class="ajax-message"></div>
    
    <!-- Header -->
    <div class="header">
        <div class="logo">
            <span>🐬</span>
            <span>Dolphin CRM</span>
        </div>
        
        <div class="nav-links">
            <a href="dashboard.php" class="nav-link">Dashboard</a>
            <a href="add_contact.php" class="nav-link">New Contact</a>
            <a href="add_user.php" class="nav-link">New User</a>
            <a href="view_users.php" class="nav-link">Users</a>
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
    
    <!-- Main Content -->
    <div class="container">
        <div class="page-title">
            <h1>Add New User</h1>
            <a href="dashboard.php" class="btn">Back to Dashboard</a>
        </div>
        
        <!-- Success Actions (hidden by default) -->
        <div class="success-actions" id="success-actions">
            <h3>✅ User Added Successfully!</h3>
            <p id="success-message"></p>
            <div class="form-actions" style="margin-top: 15px;">
                <a href="add_user.php" class="btn btn-success" id="add-another-btn">Add Another User</a>
                <a href="view_users.php" class="btn">View All Users</a>
                <a href="dashboard.php" class="btn">Go to Dashboard</a>
            </div>
        </div>
        
        <!-- Form Container -->
        <div id="form-container">
            <div class="form-container">
                <form id="add-user-form">
                    <div class="form-row">
                        <div class="form-col">
                            <label class="form-label">First Name *</label>
                            <input type="text" name="firstname" id="firstname" class="form-control" required>
                            <div class="error-message" id="firstname-error"></div>
                        </div>
                        <div class="form-col">
                            <label class="form-label">Last Name *</label>
                            <input type="text" name="lastname" id="lastname" class="form-control" required>
                            <div class="error-message" id="lastname-error"></div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" id="email" class="form-control" required>
                            <div class="error-message" id="email-error"></div>
                        </div>
                        <div class="form-col">
                            <label class="form-label">Role *</label>
                            <select name="role" id="role" class="form-control" required>
                                <option value="">Select Role</option>
                                <option value="Admin">Admin</option>
                                <option value="Member">Member</option>
                            </select>
                            <div class="error-message" id="role-error"></div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <label class="form-label">Password *</label>
                            <input type="password" name="password" id="password" class="form-control" required>
                            <div class="error-message" id="password-error"></div>
                            
                            <div class="password-rules">
                                <strong>Password Requirements:</strong>
                                <ul>
                                    <li id="rule-length" class="invalid">At least 8 characters</li>
                                    <li id="rule-number" class="invalid">Contains at least 1 number</li>
                                    <li id="rule-capital" class="invalid">Contains at least 1 capital letter</li>
                                </ul>
                                <div class="password-strength">
                                    <div class="strength-bar" id="strength-bar"></div>
                                </div>
                            </div>
                        </div>
                        <div class="form-col">
                            <label class="form-label">Confirm Password *</label>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                            <div class="error-message" id="confirm_password-error"></div>
                            <div class="password-match" id="password-match"></div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success" id="submit-btn">Add User</button>
                        <a href="dashboard.php" class="btn">Cancel</a>
                        <div class="loading" id="form-loading" style="display: none; margin-top: 10px;">
                            <div class="loading-spinner" style="width: 20px; height: 20px; display: inline-block; margin-right: 10px;"></div>
                            Creating user...
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('add-user-form');
        const formContainer = document.getElementById('form-container');
        const successActions = document.getElementById('success-actions');
        const successMessage = document.getElementById('success-message');
        const loadingOverlay = document.getElementById('loading-overlay');
        const messageDiv = document.getElementById('ajax-message');
        const submitBtn = document.getElementById('submit-btn');
        const formLoading = document.getElementById('form-loading');
        const addAnotherBtn = document.getElementById('add-another-btn');
        const passwordField = document.getElementById('password');
        const confirmPasswordField = document.getElementById('confirm_password');
        const strengthBar = document.getElementById('strength-bar');
        
        let passwordValid = false;
        let passwordsMatch = false;
        
        // Clear all error messages
        function clearErrors() {
            document.querySelectorAll('.error-message').forEach(el => {
                el.textContent = '';
                el.style.display = 'none';
            });
            document.querySelectorAll('.form-control').forEach(el => {
                el.classList.remove('error');
            });
        }
        
        // Show error on specific field
        function showError(fieldId, message) {
            const field = document.getElementById(fieldId);
            const errorEl = document.getElementById(fieldId + '-error');
            
            if (field && errorEl) {
                field.classList.add('error');
                errorEl.textContent = message;
                errorEl.style.display = 'block';
                errorEl.style.color = '#e74c3c';
                errorEl.style.fontSize = '14px';
                errorEl.style.marginTop = '5px';
            }
        }
        
        // Show message
        function showMessage(text, type = 'success') {
            messageDiv.textContent = text;
            messageDiv.className = 'ajax-message ' + type + ' show';
            setTimeout(() => {
                messageDiv.classList.remove('show');
            }, 5000);
        }
        
        // Show loading
        function showLoading(show) {
            if (show) {
                loadingOverlay.style.display = 'flex';
                submitBtn.disabled = true;
                formLoading.style.display = 'block';
                formContainer.classList.add('form-disabled');
            } else {
                loadingOverlay.style.display = 'none';
                submitBtn.disabled = false;
                formLoading.style.display = 'none';
                formContainer.classList.remove('form-disabled');
            }
        }
        
        // Show success state
        function showSuccess(user) {
            // Hide form, show success actions
            formContainer.style.display = 'none';
            successActions.classList.add('show');
            
            // Set success message
            const userName = user.firstname + ' ' + user.lastname + ' (' + user.email + ')';
            successMessage.textContent = userName + ' has been added as a ' + user.role + '.';
            
            // Show success message
            showMessage('User added successfully!');
        }
        
        // Reset form
        function resetForm() {
            form.reset();
            clearErrors();
            formContainer.style.display = 'block';
            successActions.classList.remove('show');
            formContainer.classList.remove('form-disabled');
            validatePassword(); // Reset validation UI
            checkPasswordMatch(); // Reset match UI
            updateSubmitButton();
        }
        
        // Validate password strength
        function validatePassword() {
            const password = passwordField.value;
            
            // Check rules
            const hasLength = password.length >= 8;
            const hasNumber = /\d/.test(password);
            const hasCapital = /[A-Z]/.test(password);
            
            // Update rule indicators
            document.getElementById('rule-length').className = hasLength ? 'valid' : 'invalid';
            document.getElementById('rule-number').className = hasNumber ? 'valid' : 'invalid';
            document.getElementById('rule-capital').className = hasCapital ? 'valid' : 'invalid';
            
            // Calculate strength
            let strength = 0;
            if (hasLength) strength += 33;
            if (hasNumber) strength += 33;
            if (hasCapital) strength += 34;
            
            // Update strength bar
            strengthBar.style.width = strength + '%';
            
            // Change color based on strength
            if (strength < 50) {
                strengthBar.style.backgroundColor = '#e74c3c';
            } else if (strength < 80) {
                strengthBar.style.backgroundColor = '#f39c12';
            } else {
                strengthBar.style.backgroundColor = '#27ae60';
            }
            
            // Update password valid flag
            passwordValid = hasLength && hasNumber && hasCapital;
            
            // Update submit button
            updateSubmitButton();
            
            // Also check password match
            checkPasswordMatch();
            
            return passwordValid;
        }
        
        // Check if passwords match
        function checkPasswordMatch() {
            const password = passwordField.value;
            const confirmPassword = confirmPasswordField.value;
            const matchDiv = document.getElementById('password-match');
            
            if (confirmPassword === '') {
                matchDiv.textContent = '';
                matchDiv.className = 'password-match';
                passwordsMatch = false;
            } else if (password === confirmPassword) {
                matchDiv.textContent = '✓ Passwords match';
                matchDiv.className = 'password-match valid';
                passwordsMatch = true;
            } else {
                matchDiv.textContent = '✗ Passwords do not match';
                matchDiv.className = 'password-match invalid';
                passwordsMatch = false;
            }
            
            // Update submit button
            updateSubmitButton();
            
            return passwordsMatch;
        }
        
        // Update submit button state
        function updateSubmitButton() {
            // Check all required fields
            const requiredFields = ['firstname', 'lastname', 'email', 'role', 'password', 'confirm_password'];
            let allRequiredFilled = true;
            
            requiredFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field && !field.value.trim()) {
                    allRequiredFilled = false;
                }
            });
            
            // Enable/disable button
            submitBtn.disabled = !(allRequiredFilled && passwordValid && passwordsMatch);
        }
        
        // Form submission
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validate password one more time
            if (!validatePassword()) {
                showMessage('Password does not meet requirements', 'error');
                return;
            }
            
            if (!checkPasswordMatch()) {
                showMessage('Passwords do not match', 'error');
                return;
            }
            
            // Clear previous errors
            clearErrors();
            
            // Show loading
            showLoading(true);
            
            // Get form data
            const formData = new FormData(form);
            
            // Send AJAX request
            fetch('add_user.php?ajax=submit', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                showLoading(false);
                
                if (data.success) {
                    // Success - show success state
                    showSuccess(data.user);
                } else {
                    // Show errors
                    if (data.errors && data.errors.length > 0) {
                        // Show general error
                        showMessage(data.errors.join(', '), 'error');
                        
                        // Try to map errors to fields
                        data.errors.forEach(error => {
                            const lowerError = error.toLowerCase();
                            
                            if (lowerError.includes('first name')) showError('firstname', error);
                            else if (lowerError.includes('last name')) showError('lastname', error);
                            else if (lowerError.includes('email')) showError('email', error);
                            else if (lowerError.includes('role')) showError('role', error);
                            else if (lowerError.includes('password')) {
                                if (lowerError.includes('match')) {
                                    showError('confirm_password', error);
                                } else {
                                    showError('password', error);
                                }
                            }
                        });
                    } else {
                        showMessage(data.message || 'An error occurred', 'error');
                    }
                }
            })
            .catch(error => {
                showLoading(false);
                showMessage('Network error. Please try again.', 'error');
                console.error('Error:', error);
            });
        });
        
        // Add another user button
        addAnotherBtn.addEventListener('click', function(e) {
            e.preventDefault();
            resetForm();
        });
        
        // Live validation events
        passwordField.addEventListener('input', validatePassword);
        passwordField.addEventListener('blur', validatePassword);
        confirmPasswordField.addEventListener('input', checkPasswordMatch);
        confirmPasswordField.addEventListener('blur', checkPasswordMatch);
        
        // Monitor all required fields for button state
        const requiredFields = ['firstname', 'lastname', 'email', 'role', 'password', 'confirm_password'];
        requiredFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.addEventListener('input', updateSubmitButton);
                field.addEventListener('blur', function() {
                    if (!this.value.trim()) {
                        showError(fieldId, 'This field is required');
                    } else {
                        const errorEl = document.getElementById(fieldId + '-error');
                        if (errorEl) {
                            errorEl.textContent = '';
                            errorEl.style.display = 'none';
                        }
                        this.classList.remove('error');
                    }
                    updateSubmitButton();
                });
            }
        });
        
        // Email validation
        const emailField = document.getElementById('email');
        if (emailField) {
            emailField.addEventListener('blur', function() {
                const email = this.value.trim();
                if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    showError('email', 'Please enter a valid email address');
                }
            });
        }
        
        // Initial button state
        updateSubmitButton();
        
        // Add CSS for error styling
        const style = document.createElement('style');
        style.textContent = `
            .form-control.error {
                border-color: #e74c3c !important;
                box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1) !important;
            }
            .error-message {
                display: none;
            }
        `;
        document.head.appendChild(style);
    });
    </script>
</body>
</html>