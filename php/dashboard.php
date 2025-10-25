<?php
include("../php/account.php");

$servername = "localhost";
$username = "root";
$xampp_password = "";
$database = "test";

$conn = mysqli_connect($servername, $username, $xampp_password, $database);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

//  get logged-in email from session
if (!isset($_SESSION['email']) || empty($_SESSION['email'])) {
    echo "<script>
        alert('Please login first');
        window.location.href='../pages/login.html';
    </script>";
    exit();
}
$email = $_SESSION['email'] ?? $admin_email;

//  get user info
$sql_user = "SELECT id, username, email, phone FROM users WHERE email='$email'";
$result_user = mysqli_query($conn, $sql_user);

if (!$result_user || mysqli_num_rows($result_user) === 0) {
    echo "<script>
   window.location.href='../pages/signup.html';
   </script>";
}

$row_user = mysqli_fetch_assoc($result_user);
$user_id = $row_user['id'];
$name = $row_user['username'];
$current_email = $row_user['email'];
$current_phone = $row_user['phone'];

// ✅ Check if 'amounts' table exists
$check_table = "SHOW TABLES LIKE 'amounts'";
$table_exists = mysqli_query($conn, $check_table);

if (mysqli_num_rows($table_exists) === 0) {
    // Create amounts table if not exists
    $create_amounts = "
        CREATE TABLE amounts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            total_balance DECIMAL(10,2) DEFAULT 0.00,
            income DECIMAL(10,2) DEFAULT 0.00,
            expenses DECIMAL(10,2) DEFAULT 0.00,
            savings DECIMAL(10,2) DEFAULT 0.00,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
    if (mysqli_query($conn, $create_amounts)) {
        echo "✅ Table 'amounts' created successfully.<br>";
    } else {
        die("❌ Error creating 'amounts': " . mysqli_error($conn));
    }
}

// ✅ Check if user already has a row in amounts table
$sql_amount = "SELECT * FROM amounts WHERE user_id='$user_id'";
$result_amount = mysqli_query($conn, $sql_amount);

if (mysqli_num_rows($result_amount) > 0) {
    // ✅ Existing data — fetch it
    $amount_data = mysqli_fetch_assoc($result_amount);
    $total_balance = $amount_data['total_balance'];
    $savings = $amount_data['savings'];
    $income = $amount_data['income'];
    $expenses = $amount_data['expenses'];
} else {
    // 🚀 Initialize new amount record
    $init_sql = "INSERT INTO amounts (user_id, total_balance, income, expenses, savings)
                 VALUES ('$user_id', 1000.00, 0.00, 0.00, 500.00)"; // Default starting amounts
    mysqli_query($conn, $init_sql);

    $total_balance = 1000.00;
    $income = 0.00;
    $expenses = 0.00;
    $savings = 500.00;
}


/////////// check transactions table exist 
$check_transactions_table = "SHOW TABLES LIKE 'transactions'";
$check_transactions = mysqli_query($conn, $check_transactions_table);

if (mysqli_num_rows($check_transactions) === 0) {
      
    $create_transactions_table = "CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('transfer_sent', 'transfer_received', 'expense', 'income'),
    amount DECIMAL(10,2),
    description VARCHAR(255),
    from_account VARCHAR(50),
    to_account VARCHAR(100),
    status ENUM('completed', 'pending', 'failed'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);";

    if (mysqli_query($conn, $create_transactions_table)) {
        echo "✅ Table 'transactions' created successfully.<br>";
    } else {
        die("❌ Error creating 'transactions': " . mysqli_error($conn));
    }
}

$sql_transactions = "SELECT * FROM transactions WHERE user_id='$user_id' ORDER BY created_at DESC LIMIT 10";
$result_transactions = mysqli_query($conn, $sql_transactions);

if ($result_transactions && mysqli_num_rows($result_transactions) > 0) {
    while($row = mysqli_fetch_assoc($result_transactions)) {
        $transactions[] = $row;
    }
}

// Handle Profile Update
if (isset($_POST["update_profile"])) {
    $setting_name = mysqli_real_escape_string($conn, $_POST["setting_name"]);
    $setting_email = mysqli_real_escape_string($conn, $_POST["setting_email"]);
    $setting_phone = mysqli_real_escape_string($conn, $_POST["setting_phone"]);

    $sql_setting_update = "UPDATE users
                           SET username='$setting_name', email='$setting_email', phone='$setting_phone'
                           WHERE id='$user_id'";

    if (mysqli_query($conn, $sql_setting_update)) {
        // Update session and current variables
        $_SESSION['email'] = $setting_email;
        $email = $setting_email;
        $name = $setting_name;
        $current_phone = $setting_phone;

        echo "<script>
         window.location.href='dashboard.php';
         </script>";
    } else {
        echo "<script>alert('❌ Error updating profile: " . mysqli_error($conn) . "');</script>";
    }
}

// Handle Change Password
if (isset($_POST["change_password"])) {
    $current_password = mysqli_real_escape_string($conn, $_POST["current_password"]);
    $new_password = mysqli_real_escape_string($conn, $_POST["new_password"]);
    $confirm_password = mysqli_real_escape_string($conn, $_POST["confirm_password"]);

    // Get current password from database
    $sql_get_password = "SELECT password FROM users WHERE id='$user_id'";
    $result_password = mysqli_query($conn, $sql_get_password);
    $row_password = mysqli_fetch_assoc($result_password);

    if ($row_password && password_verify($current_password, $row_password['password'])) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 6) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $sql_update_password = "UPDATE users SET password='$hashed_password' WHERE id='$user_id'";

                if (mysqli_query($conn, $sql_update_password)) {
                    echo "<script>alert('✅ Password changed successfully!'); window.location.href='dashboard.php';</script>";
                } else {
                    echo "<script>alert('❌ Error updating password!');</script>";
                }
            } else {
                echo "<script>alert('❌ Password must be at least 6 characters long!');</script>";
            }
        } else {
            echo "<script>alert('❌ New passwords do not match!');</script>";
        }
    } else {
        echo "<script>alert('❌ Current password is incorrect!');</script>";
    }
}

// Handle Money Transfer
if (isset($_POST["transfer"])) {
    $transfer_email = mysqli_real_escape_string($conn, $_POST["transfer_email"]);
    $transfer_amount = floatval($_POST["transfer_amount"]);
    $transfer_description = mysqli_real_escape_string($conn, $_POST["transfer_description"]);
    $from_account = mysqli_real_escape_string($conn, $_POST["from_account"]);

    // Validate amount
    if ($transfer_amount <= 0) {
        echo "<script>alert('❌ Please enter a valid amount!');</script>";
    } else {
        // Get sender's current balances
        $sql_sender_amount = "SELECT total_balance, savings FROM amounts WHERE user_id='$user_id'";
        $result_sender_amount = mysqli_query($conn, $sql_sender_amount);
        $row_sender_amount = mysqli_fetch_assoc($result_sender_amount);

        $sender_balance = $row_sender_amount['total_balance'];
        $sender_savings = $row_sender_amount['savings'];

        // Check if recipient exists
        $sql_recipient = "SELECT id FROM users WHERE email='$transfer_email'";
        $result_recipient = mysqli_query($conn, $sql_recipient);

        if (mysqli_num_rows($result_recipient) > 0) {
            // Recipient exists - transfer between users
            $row_recipient = mysqli_fetch_assoc($result_recipient);
            $recipient_id = $row_recipient['id'];

            // Check sufficient balance based on selected account
            if ($from_account == 'Checking Account') {
                if ($sender_balance >= $transfer_amount) {

                    $summaryTotal = $transfer_amount;  ////// latter for data to be shown when entry transfer amount


                    // Deduct from sender's checking account
                    $new_balance = $sender_balance - $transfer_amount;
                    $sql_sender_update = "UPDATE amounts
                                           SET total_balance = $new_balance,
                                               expenses = expenses + $transfer_amount
                                           WHERE user_id='$user_id'";

                    // Add to recipient's checking account
                    $sql_recipient_update = "UPDATE amounts
                                              SET total_balance = total_balance + $transfer_amount,
                                                  income = income + $transfer_amount
                                              WHERE user_id='$recipient_id'";

                    if (mysqli_query($conn, $sql_sender_update) && mysqli_query($conn, $sql_recipient_update)) {
                        // Record transaction for sender
                        $sql_transaction = "INSERT INTO transactions (user_id, type, amount, description, from_account, to_account, status)
                                           VALUES ('$user_id', 'transfer_sent', $transfer_amount, '$transfer_description', '$from_account', '$transfer_email', 'completed')";
                        mysqli_query($conn, $sql_transaction);

                        // Record transaction for recipient
                        $sql_transaction_recipient = "INSERT INTO transactions (user_id, type, amount, description, from_account, to_account, status)
                                                      VALUES ('$recipient_id', 'transfer_received', $transfer_amount, '$transfer_description', '$email', 'Checking Account', 'completed')";
                        mysqli_query($conn, $sql_transaction_recipient);

                        echo "<script>alert('✅ Transfer successful!'); window.location.href='dashboard.php';</script>";
                    } else {
                        echo "<script>alert('❌ Transfer failed!');</script>";
                    }
                } else {
                    echo "<script>alert('❌ Insufficient checking account balance!');</script>";
                }
            } elseif ($from_account == 'Savings Account') {
                if ($sender_savings >= $transfer_amount) {
                    // Deduct from sender's savings account
                    $new_savings = $sender_savings - $transfer_amount;
                    $sql_sender_update = "UPDATE amounts
                                           SET savings = $new_savings,
                                               expenses = expenses + $transfer_amount
                                           WHERE user_id='$user_id'";

                    // Add to recipient's checking account
                    $sql_recipient_update = "UPDATE amounts
                                              SET total_balance = total_balance + $transfer_amount,
                                                  income = income + $transfer_amount
                                              WHERE user_id='$recipient_id'";

                    if (mysqli_query($conn, $sql_sender_update) && mysqli_query($conn, $sql_recipient_update)) {
                        // Record transaction for sender
                        $sql_transaction = "INSERT INTO transactions (user_id, type, amount, description, from_account, to_account, status)
                                           VALUES ('$user_id', 'transfer_sent', $transfer_amount, '$transfer_description', '$from_account', '$transfer_email', 'completed')";
                        mysqli_query($conn, $sql_transaction);

                        // Record transaction for recipient
                        $sql_transaction_recipient = "INSERT INTO transactions (user_id, type, amount, description, from_account, to_account, status)
                                                      VALUES ('$recipient_id', 'transfer_received', $transfer_amount, '$transfer_description', '$email', 'Checking Account', 'completed')";
                        mysqli_query($conn, $sql_transaction_recipient);

                        echo "<script>alert('✅ Transfer successful!'); window.location.href='dashboard.php';</script>";
                    } else {
                        echo "<script>alert('❌ Transfer failed!');</script>";
                    }
                } else {
                    echo "<script>alert('❌ Insufficient savings account balance!');</script>";
                }
            }
        } else {
            // Recipient doesn't exist - transfer to admin
            $admin_id = 1; // Assuming admin has ID 1

            // Check sufficient balance based on selected account
            if ($from_account == 'Checking Account') {
                if ($sender_balance >= $transfer_amount) {
                    // Deduct from sender's checking account
                    $new_balance = $sender_balance - $transfer_amount;
                    $sql_sender_update = "UPDATE amounts
                                           SET total_balance = $new_balance,
                                               expenses = expenses + $transfer_amount
                                           WHERE user_id='$user_id'";

                    // Add to admin's account
                    $sql_admin_update = "UPDATE amounts
                                          SET total_balance = total_balance + $transfer_amount,
                                              income = income + $transfer_amount
                                          WHERE user_id='$admin_id'";

                    if (mysqli_query($conn, $sql_sender_update) && mysqli_query($conn, $sql_admin_update)) {
                        // Record transaction
                        $sql_transaction = "INSERT INTO transactions (user_id, type, amount, description, from_account, to_account, status)
                                           VALUES ('$user_id', 'transfer_sent', $transfer_amount, '$transfer_description', '$from_account', '$transfer_email', 'completed')";
                        mysqli_query($conn, $sql_transaction);

                        echo "<script>alert('✅ Transfer to admin successful!'); window.location.href='dashboard.php';</script>";
                    } else {
                        echo "<script>alert('❌ Transfer failed!');</script>";
                    }
                } else {
                    echo "<script>alert('❌ Insufficient checking account balance!');</script>";
                }
            } elseif ($from_account == 'Savings Account') {
                if ($sender_savings >= $transfer_amount) {
                    // Deduct from sender's savings account
                    $new_savings = $sender_savings - $transfer_amount;
                    $sql_sender_update = "UPDATE amounts
                                           SET savings = $new_savings,
                                               expenses = expenses + $transfer_amount
                                           WHERE user_id='$user_id'";

                    // Add to admin's account
                    $sql_admin_update = "UPDATE amounts
                                          SET total_balance = total_balance + $transfer_amount,
                                              income = income + $transfer_amount
                                          WHERE user_id='$admin_id'";

                    if (mysqli_query($conn, $sql_sender_update) && mysqli_query($conn, $sql_admin_update)) {
                        // Record transaction
                        $sql_transaction = "INSERT INTO transactions (user_id, type, amount, description, from_account, to_account, status)
                                           VALUES ('$user_id', 'transfer_sent', $transfer_amount, '$transfer_description', '$from_account', '$transfer_email', 'completed')";
                        mysqli_query($conn, $sql_transaction);

                        echo "<script>alert('✅ Transfer to admin successful!'); window.location.href='dashboard.php';</script>";
                    } else {
                        echo "<script>alert('❌ Transfer failed!');</script>";
                    }
                } else {
                    echo "<script>alert('❌ Insufficient savings account balance!');</script>";
                }
            }
        }
    }
}

// Refresh data after updates
$sql_amount = "SELECT * FROM amounts WHERE user_id='$user_id'";
$result_amount = mysqli_query($conn, $sql_amount);
if (mysqli_num_rows($result_amount) > 0) {
    $amount_data = mysqli_fetch_assoc($result_amount);
    $total_balance = $amount_data['total_balance'];
    $savings = $amount_data['savings'];
    $income = $amount_data['income'];
    $expenses = $amount_data['expenses'];
}



?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SecureBank</title>
    <link rel="icon" type="image/x-icon" href="../favicon.ico">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="brand">
                <i class="fas fa-university"></i>
                <span>SecureBank</span>
            </div>
        </div>
        
        <nav class="sidebar-nav">
            <a href="#" class="nav-item active" data-section="overview">
                <i class="fas fa-tachometer-alt"></i>
                <span>Overview</span>
            </a>
            <a href="#" class="nav-item" data-section="accounts">
                <i class="fas fa-wallet"></i>
                <span>Accounts</span>
            </a>
            <a href="#" class="nav-item" data-section="transactions">
                <i class="fas fa-exchange-alt"></i>
                <span>Transactions</span>
            </a>
            <a href="#" class="nav-item" data-section="transfer">
                <i class="fas fa-paper-plane"></i>
                <span>Transfer</span>
            </a>
            <a href="#" class="nav-item" data-section="cards">
                <i class="fas fa-credit-card"></i>
                <span>Cards</span>
            </a>
            <a href="#" class="nav-item" data-section="settings">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </nav>
        
        <div class="sidebar-footer">
            <button class="logout-btn" onclick="logout()">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </button>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <header class="dashboard-header">
            <div class="header-left">
                <button class="menu-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 id="pageTitle">Dashboard Overview</h1>
            </div>
            <div class="header-right">
                <div class="user-profile">
                    <img src="../images/image.png" alt="Profile" class="profile-img">
                    <div class="user-info">
                        <span class="user-name"><?php echo $name; ?></span>
                        <span class="user-id">ID: <?php echo $user_id; ?></span>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Overview Section -->
            <div id="overview" class="content-section active">
                <!-- Account Summary Cards -->
                <div class="summary-cards">
                    <div class="summary-card primary">
                        <div class="card-icon">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <div class="card-content">
                            <h3>Total Balance</h3>
                            <p class="amount">Rs<?php echo number_format($total_balance, 2); ?></p>
                            <span class="change positive">+0% from last month</span>
                        </div>
                    </div>
                    
                    <div class="summary-card success">
                        <div class="card-icon">
                            <i class="fas fa-arrow-down"></i>
                        </div>
                        <div class="card-content">
                            <h3>Income</h3>
                            <p class="amount">Rs<?php echo number_format($income, 2); ?></p>
                            <span class="change positive">+0% from last month</span>
                        </div>
                    </div>
                    
                    <div class="summary-card warning">
                        <div class="card-icon">
                            <i class="fas fa-arrow-up"></i>
                        </div>
                        <div class="card-content">
                            <h3>Expenses</h3>
                            <p class="amount">Rs<?php echo number_format($expenses, 2); ?></p>
                            <span class="change negative">+0% from last month</span>
                        </div>
                    </div>
                    
                    <div class="summary-card info">
                        <div class="card-icon">
                            <i class="fas fa-piggy-bank"></i>
                        </div>
                        <div class="card-content">
                            <h3>Savings</h3>
                            <p class="amount">Rs<?php echo number_format($savings, 2); ?></p>
                            <span class="change positive">+0% from last month</span>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="quick-actions">
                    <h2>Quick Actions</h2>
                    <div class="action-buttons">
                        <button class="action-btn" onclick="showSection('transfer')">
                            <i class="fas fa-paper-plane"></i>
                            <span>Send Money</span>
                        </button>
                        <button class="action-btn" onclick="showSection('transactions')">
                            <i class="fas fa-history"></i>
                            <span>View History</span>
                        </button>
                        <button class="action-btn" onclick="showSection('cards')">
                            <i class="fas fa-credit-card"></i>
                            <span>Manage Cards</span>
                        </button>
                        <button class="action-btn" onclick="downloadStatement()">
                            <i class="fas fa-download"></i>
                            <span>Download Statement</span>
                        </button>
                    </div>
                </div>
                
                <!-- Recent Transactions -->
                <div class="recent-transactions">
                    <div class="section-header">
                        <h2>Recent Transactions</h2>
                        <button class="view-all-btn" onclick="showSection('transactions')">View All</button>
                    </div>
                    <div class="transactions-list" id="recentTransactionsList">
                        <?php
                        if (isset($transactions) && count($transactions) > 0) {
                            foreach (array_slice($transactions, 0, 5) as $transaction) {
                                $icon_class = '';
                                $amount_class = '';
                                $icon = '';
                                $sign = '';

                                if ($transaction['type'] == 'transfer_received' || $transaction['type'] == 'income') {
                                    $icon_class = 'income';
                                    $amount_class = 'positive';
                                    $icon = 'fa-arrow-down';
                                    $sign = '+';
                                } else {
                                    $icon_class = 'expense';
                                    $amount_class = 'negative';
                                    $icon = 'fa-arrow-up';
                                    $sign = '-';
                                }

                                echo '<div class="transaction-item">';
                                echo '  <div class="transaction-info">';
                                echo '    <div class="transaction-icon ' . $icon_class . '">';
                                echo '      <i class="fas ' . $icon . '"></i>';
                                echo '    </div>';
                                echo '    <div class="transaction-details">';
                                echo '      <h4>' . ucfirst(str_replace('_', ' ', $transaction['type'])) . '</h4>';
                                echo '      <p>' . htmlspecialchars($transaction['description'] ?: $transaction['to_account']) . ' • ' . date('M d, Y', strtotime($transaction['created_at'])) . '</p>';
                                echo '    </div>';
                                echo '  </div>';
                                echo '  <div class="transaction-amount ' . $amount_class . '">' . $sign . 'Rs' . number_format($transaction['amount'], 2) . '</div>';
                                echo '</div>';
                            }
                        } else {
                            echo '<p style="text-align: center; color: #6b7280; padding: 2rem;">No transactions found</p>';
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <!-- Accounts Section -->
            <div id="accounts" class="content-section">
                <div class="section-header">
                    <h2>My Accounts</h2>
                </div>
                
                <div class="accounts-grid">
                    <div class="account-card checking">
                        <div class="account-header">
                            <div class="account-type">
                                <i class="fas fa-university"></i>
                                <span>Checking Account</span>
                            </div>
                            <div class="account-number">****1234</div>
                        </div>
                        <div class="account-balance">
                            <span class="balance-label">Total Balance</span>
                            <span class="balance-amount">Rs<?php echo number_format($total_balance, 2); ?></span>
                        </div>
                        <div class="account-actions">
                            <button class="btn btn-sm" onclick="showSection('transfer')">Transfer</button>
                            <button class="btn btn-sm btn-outline">Details</button>
                        </div>
                    </div>
                    
                    <div class="account-card savings">
                        <div class="account-header">
                            <div class="account-type">
                                <i class="fas fa-piggy-bank"></i>
                                <span>Savings Account</span>
                            </div>
                            <div class="account-number">****5678</div>
                        </div>
                        <div class="account-balance">
                            <span class="balance-label">Available Balance</span>
                            <span class="balance-amount">Rs <?php echo number_format($savings, 2); ?></span>
                        </div>
                        <div class="account-actions">
                            <button class="btn btn-sm" onclick="showSection('transfer')">Transfer</button>
                            <button class="btn btn-sm btn-outline">Details</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Transactions Section -->
            <div id="transactions" class="content-section">
                <div class="section-header">
                    <h2>Transaction History</h2>
                    <div class="filters">
                        <select class="filter-select">
                            <option>All Transactions</option>
                            <option>Income</option>
                            <option>Expenses</option>
                            <option>Transfers</option>
                        </select>
                    </div>
                </div>
                
                <div class="transactions-table">
                    <div class="table-header">
                        <div class="table-row">
                            <div class="table-cell">Date</div>
                            <div class="table-cell">From</div>
                            <div class="table-cell">Category</div>
                            <div class="table-cell">Amount</div>
                            <div class="table-cell">Description</div>
                        </div>
                    </div>
                    <div class="table-body" id="transactionsTableBody">
                        <?php
                        if (isset($transactions) && count($transactions) > 0) {
                            foreach ($transactions as $transaction) {
                                $icon_class = '';
                                $amount_class = '';
                                $sign = '';

                                if ($transaction['type'] == 'transfer_received' || $transaction['type'] == 'income') {
                                    $icon_class = 'income';
                                    $amount_class = 'positive';
                                    $sign = '+';
                                } else {
                                    $icon_class = 'expense';
                                    $amount_class = 'negative';
                                    $sign = '-';
                                }

                                echo '<div class="table-row" data-type="' . $transaction['type'] . '">';
                                echo '  <div class="table-cell">' . date('M d, Y h:i A', strtotime($transaction['created_at'])) . '</div>';
                                echo '  <div class="table-cell">' . htmlspecialchars($transaction['from_account'] ?: 'N/A') . '</div>';
                                echo '  <div class="table-cell"><span class="badge ' . $icon_class . '">' . ucfirst(str_replace('_', ' ', $transaction['type'])) . '</span></div>';
                                echo '  <div class="table-cell"><span class="transaction-amount ' . $amount_class . '">' . $sign . 'Rs' . number_format($transaction['amount'], 2) . '</span></div>';
                                echo '  <div class="table-cell">' . htmlspecialchars($transaction['description'] ?: $transaction['to_account']) . '</div>';
                                echo '</div>';
                            }
                        } else {
                            echo '<div style="text-align: center; color: #6b7280; padding: 2rem;">No transactions found</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <!-- Transfer Section -->
            <div id="transfer" class="content-section">
                <div class="transfer-container">
                    <div class="transfer-form-card">
                        <h2>Send Money</h2>
                        <form id="transferForm" name="transfer_form" method="POST" action="dashboard.php">
                            <div class="form-group">
                                <label>From Account</label>
                                <select class="form-control" name="from_account" id="fromAccount" required>
                                    <option value="Checking Account">Checking Account (****1234) - Rs <?php echo number_format($total_balance, 2); ?></option>
                                    <option value="Savings Account">Savings Account (****5678) - Rs <?php echo number_format($savings, 2); ?></option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>To Account/Email</label>
                                <input type="text" class="form-control" id="toAccount" placeholder="Enter account number or email" name="transfer_email" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Amount</label>
                                <div class="amount-input">
                                    <span class="currency">Rs</span>
                                    <input type="number" class="form-control" id="transferAmount" placeholder="0.00" step="0.01" min="0.01" name="transfer_amount" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Description (Optional)</label>
                                <input type="text" class="form-control" id="transferDescription" placeholder="What's this for?" name="transfer_description">
                            </div>
                            
                            <div class="transfer-summary">
                                <div class="summary-row">
                                    <span>Transfer Amount:</span>
                                    <span id="summaryAmount">Rs0.00</span>
                                </div>
                                <div class="summary-row">
                                    <span>Transfer Fee:</span>
                                    <span>Rs0.00</span>
                                </div>
                                <div class="summary-row total">
                                    <span>Total:</span>
                                    <span id="summaryTotal">Rs0.00</span>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-full" name="transfer">
                                <i class="fas fa-paper-plane"></i>
                                Send Money
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Cards Section -->
            <div id="cards" class="content-section">
                <div class="section-header">
                    <h2>My Cards</h2>
                </div>
                
                <div class="cards-grid">
                    <div class="credit-card primary">
                        <div class="card-header">
                            <div class="card-type">Debit Card</div>
                            <i class="fab fa-cc-visa"></i>
                        </div>
                        <div class="card-chip"></div>
                        <div class="card-number">**** **** **** 1234</div>
                        <div class="card-footer">
                            <div class="card-holder"><?php echo strtoupper($name); ?></div>
                            <div class="card-expiry">12/25</div>
                        </div>
                        <div class="card-actions">
                            <button class="btn btn-sm">Block Card</button>
                            <button class="btn btn-sm btn-outline">Settings</button>
                        </div>
                    </div>
                    
                    <div class="credit-card secondary">
                        <div class="card-header">
                            <div class="card-type">Credit Card</div>
                            <i class="fab fa-cc-mastercard"></i>
                        </div>
                        <div class="card-chip"></div>
                        <div class="card-number">**** **** **** 5678</div>
                        <div class="card-footer">
                            <div class="card-holder"><?php echo strtoupper($name); ?></div>
                            <div class="card-expiry">08/26</div>
                        </div>
                        <div class="card-actions">
                            <button class="btn btn-sm">Block Card</button>
                            <button class="btn btn-sm btn-outline">Settings</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Settings Section -->
            <div id="settings" class="content-section">
                <div class="settings-container">
                    <div class="settings-section">
                        <h3>Profile Information</h3>
                        <form class="settings-form" name="setting_form" action="../php/dashboard.php" method="POST">
                            <div class="form-group">
                                <label>Name</label>
                                <input type="text" name="setting_name" class="form-control" value="<?php echo $name; ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="setting_email" class="form-control" value="<?php echo $email; ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Phone</label>
                                <input type="tel" name="setting_phone" class="form-control" placeholder="Enter phone number">
                            </div>
                            <button type="submit" class="btn btn-primary" name="update_profile">Update Profile</button>
                        </form>
                    </div>
                    
                    <div class="settings-section">
                        <h3>Security Settings</h3>
                        <div class="security-options">
                            <div class="security-item">
                                <div class="security-info">
                                    <h4>Two-Factor Authentication</h4>
                                    <p>Add an extra layer of security to your account</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" checked>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="security-item">
                                <div class="security-info">
                                    <h4>Email Notifications</h4>
                                    <p>Receive email alerts for transactions</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" checked>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="security-item">
                                <div class="security-info">
                                    <h4>SMS Notifications</h4>
                                    <p>Receive SMS alerts for large transactions</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox">
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>
                        <button class="btn btn-outline" onclick="showPasswordModal()">Change Password</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Password Change Modal -->
    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closePasswordModal()">&times;</span>
            <h2>Change Password</h2>
            <form method="POST" action="dashboard.php" onsubmit="return validatePasswordForm()">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" id="currentPassword" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" id="newPassword" class="form-control" required minlength="6">
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" id="confirmPassword" class="form-control" required minlength="6">
                </div>
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                    <button type="button" class="btn btn-outline" onclick="closePasswordModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script type="module" src="../js/dashboard.js"></script>
</body>
</html>


<?php
// Close connection at the very end
mysqli_close($conn);
?>
