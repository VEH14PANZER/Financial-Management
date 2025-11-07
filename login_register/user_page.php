<?php

session_start();
if (!isset($_SESSION['name']) || $_SESSION['role'] !== 'user') { 
    header("Location: index.php");
    exit();
}

// 1. Database Connection
$bank_conn = new mysqli("localhost", "root", "", "financialdb");
if ($bank_conn->connect_error) {
    die("Bank Connection failed: " . $bank_conn->connect_error);
}

// Ensure customer_id is set before querying
$customer_id = $_SESSION['customer_id'] ?? null;
if (!$customer_id) {
    die("CRITICAL ERROR: Customer ID is missing from your session. Please check your login process.");
}

// =================================================================
// MESSAGE HELPER FUNCTIONS
// =================================================================
function showError($error) {
    return !empty($error) ? "<p class='message error-message'>$error</p>" : '';
}

function showSuccess($message) { 
    return !empty($message) ? "<p class='message success-message'>$message</p>" : ''; 
}

// =================================================================
// 0. MESSAGE HANDLING
// =================================================================
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// =================================================================
// 1. Fetch Customer Data
// =================================================================
$customer_data = null;
// Removed the incorrect condition from the customer table query.
$stmt_customer = $bank_conn->prepare("SELECT First_name, Last_name FROM customer WHERE Customer_ID = ?"); 
$stmt_customer->bind_param("i", $customer_id);
$stmt_customer->execute();
$result_customer = $stmt_customer->get_result();
if ($result_customer->num_rows > 0) {
    $customer_data = $result_customer->fetch_assoc();
}
$stmt_customer->close();

// =================================================================
// 2. Fetch Active Accounts (Needed for both Deposit and Loan Application)
// =================================================================
$accounts_data = [];
$sql_accounts = "
    SELECT 
        a.Account_ID, 
        a.Balance, 
        at.Account_Type_Name,
        a.Branch_ID 
    FROM accounts a
    JOIN account_type at ON a.Account_Type_ID = at.Account_Type_ID
    WHERE a.Customer_ID = ? AND a.Account_status = 'Active'
    ORDER BY a.Account_ID ASC
";
$stmt_accounts = $bank_conn->prepare($sql_accounts);
$stmt_accounts->bind_param("i", $customer_id);
$stmt_accounts->execute();
$accounts_result = $stmt_accounts->get_result();
while ($row = $accounts_result->fetch_assoc()) {
    $accounts_data[] = $row;
}
$account_count = count($accounts_data);
$stmt_accounts->close();

// =================================================================
// 3. Fetch Loan History (Used by Dashboard and Transactions Tab)
// =================================================================
$loan_history = [];
$sql_loan_history = "
    SELECT 
        l.Loan_ID, 
        lt.Loan_Type_Name, 
        l.Loan_ammount, 
        l.Created_at,
        l.Branch_ID  
    FROM loans l
    JOIN loan_type lt ON l.Loan_Type_ID = lt.Loan_Type_ID
    WHERE l.Customer_ID = ? AND l.Deleted_at IS NULL AND l.Loan_ammount > 0 
    ORDER BY l.Created_at DESC
";
$stmt_loan_history = $bank_conn->prepare($sql_loan_history);
$stmt_loan_history->bind_param("i", $customer_id);
$stmt_loan_history->execute();
$loan_history_result = $stmt_loan_history->get_result();
while ($row = $loan_history_result->fetch_assoc()) {
    $loan_history[] = $row;
}
$stmt_loan_history->close();


// =================================================================
// 4. Fetch Transaction History (NEW)
// =================================================================
$transaction_history = [];
$account_ids = array_column($accounts_data, 'Account_ID'); // Get all user's active Account IDs
$BANK_ACCOUNT_ID = 999999; // Define the bank's internal account ID (used in process_transaction.php)

if (!empty($account_ids)) {
    // Convert array of IDs into a comma-separated string for the SQL IN clause
    $account_ids_string = implode(',', $account_ids);

    // Query to get transactions where the user is either the sender or the receiver
    $sql_transactions = "
        SELECT 
            Transaction_type, 
            Ammount, 
            from_account_ID, 
            to_account_ID, 
            Created_at
        FROM transactions
        WHERE from_account_ID IN ($account_ids_string)
           OR to_account_ID IN ($account_ids_string)
        ORDER BY Created_at DESC
        LIMIT 10
    ";
    
    // Using simple query here since $account_ids_string is derived internally and guaranteed to contain only safe integer values
    $result_transactions = $bank_conn->query($sql_transactions);
    
    if ($result_transactions) {
        while ($row = $result_transactions->fetch_assoc()) {
            $transaction_history[] = $row;
        }
    }
}


// =================================================================
// 5. Fetch Loan Types (for Apply Loan form)
// =================================================================
$loan_types = [];
// Updated default limit to a higher value based on user's confirmed data (100000.00)
$max_global_loan_limit = 100000.00; 

// FIX: Use 'Max_Loan_Amount' which matches the user's database column name instead of 'Loan_Limit'.
$sql_loan_types = "SELECT Loan_Type_ID, Loan_Type_Name, Max_Loan_Amount FROM loan_type";
$result_loan_types = $bank_conn->query($sql_loan_types);

if ($result_loan_types && $result_loan_types->num_rows > 0) {
    while ($row = $result_loan_types->fetch_assoc()) {
        $loan_types[] = $row;
        // Update the global limit to the highest available limit from the database for the initial display max.
        if ($row['Max_Loan_Amount'] > $max_global_loan_limit) {
            $max_global_loan_limit = $row['Max_Loan_Amount'];
        }
    }
} 

// =================================================================
// 6. Active Tab Logic
// =================================================================
$allowed_tabs = ['dashboard', 'accounts', 'apply_loan', 'transactions']; 
// NOTE: Updated 'loan_application' to 'apply_loan' in the logic to match form name
$active_tab = $_GET['tab'] ?? 'dashboard';
if (!in_array($active_tab, $allowed_tabs)) {
    $active_tab = 'dashboard';
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - <?= $_SESSION['name'] ?? 'User'; ?></title>
    <link rel="stylesheet" href="user_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"> 
</head>
<body class="user-page">

    <div class="sidebar-nav">
        <div class="logo-area">
            <h1><i class="fas fa-bank"></i> F-Bank</h1>
        </div>
        
        <nav>
            <a href="user_page.php?tab=dashboard" class="nav-item <?= ($active_tab === 'dashboard') ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i> Dashboard
            </a>
            <a href="user_page.php?tab=accounts" class="nav-item <?= ($active_tab === 'accounts') ? 'active' : ''; ?>">
                <i class="fas fa-wallet"></i> Accounts
            </a>
            <a href="user_page.php?tab=transactions" class="nav-item <?= ($active_tab === 'transactions') ? 'active' : ''; ?>">
                <i class="fas fa-exchange-alt"></i> Transactions
            </a>
            <a href="user_page.php?tab=apply_loan" class="nav-item <?= ($active_tab === 'apply_loan') ? 'active' : ''; ?>">
                <i class="fas fa-money-check-alt"></i> Loans
            </a>
        </nav>

        <a href="logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div> 
    <div class="main-content-area">

        <header class="page-header">
            <h2>Welcome back, <?= htmlspecialchars($_SESSION['name']); ?></h2>
        </header>

        <?= showSuccess($success_message); ?>
        <?= showError($error_message); ?>

        <div class="container user-dashboard">
            
            <?php if ($active_tab == 'dashboard'): ?>
                <h2>Your Financial Summary</h2>

                <div class="dashboard-grid"> 

                    <div class="summary-cards">
                        <div class="card summary-card balance-card">
                            <h3>Total Balance</h3>
                            <?php 
                            $total_balance = array_sum(array_column($accounts_data, 'Balance'));
                            ?>
                            <p class="summary-value">$<?= number_format($total_balance, 2); ?></p>
                            <span class="detail">Across <?= $account_count; ?> active account(s)</span>
                        </div>
                        <div class="card summary-card loan-card">
                            <h3>Active Loans</h3>
                            <?php
                            $total_loan_amount = array_sum(array_column($loan_history, 'Loan_ammount'));
                            $loan_count = count($loan_history);
                            ?>
                            <p class="summary-value">$<?= number_format($total_loan_amount, 2); ?></p>
                            <span class="detail"><?= $loan_count; ?> active loan(s) remaining</span>
                        </div>
                    </div>

                    <div class="card latest-loan-activity-container">
                        <h3>Latest Loan Activity</h3>
                        <div class="loan-history-card">
                            <?php if (count($loan_history) > 0): ?>
                                <table class="data-table loan-activity-table"> 
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Loan Type</th>
                                            <th>Amount Remaining</th>
                                            <th>Applied On</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($loan_history, 0, 5) as $loan): // Show only top 5 ?>
                                            <tr>
                                                <td data-label="ID:">#<?= $loan['Loan_ID']; ?></td>
                                                <td data-label="Type:"><?= htmlspecialchars($loan['Loan_Type_Name']); ?></td>
                                                <td data-label="Remaining:"><span class="amount-tag">$<?= number_format($loan['Loan_ammount'], 2); ?></span></td> 
                                                <td data-label="Applied:"><?= date('M d, Y', strtotime($loan['Created_at'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p class="info-detail" style="text-align: center; padding: 10px;">You have no active loans on record.</p>
                            <?php endif; ?>
                        </div>
                    </div> </div> <?php elseif ($active_tab == 'apply_loan'): ?>
                <h2>Apply for a New Loan</h2>

                <div class="card loan-application-form-container">
                    <form action="process_loan.php" method="POST">
                        <label for="loan_type">Loan Type:</label>
                        <select name="loan_type_id" id="loan_type" required>
                            <option value="">--Select Loan Type--</option>
                            <?php foreach ($loan_types as $type): ?>
                                <option 
                                    value="<?= $type['Loan_Type_ID']; ?>" 
                                    data-max="<?= $type['Max_Loan_Amount']; ?>"
                                >
                                    <?= htmlspecialchars($type['Loan_Type_Name']); ?> (Max: $<?= number_format($type['Max_Loan_Amount'], 2); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label id="loan_amount_label" for="loan_amount">Loan Amount (Max: $<?= number_format($max_global_loan_limit, 2); ?>):</label>
                        <input type="number" name="loan_amount" id="loan_amount" placeholder="Enter Amount" required min="100.00" step="0.01" max="<?= $max_global_loan_limit; ?>">
                        
                        <label for="deposit_account">Deposit Into Account:</label>
                        <select name="deposit_account_id" id="deposit_account" required>
                            <option value="">--Select Account--</option>
                            <?php foreach ($accounts_data as $account): ?>
                                <option value="<?= $account['Account_ID']; ?>">
                                    Account ID: <?= htmlspecialchars($account['Account_ID']); ?> (Type: <?= htmlspecialchars($account['Account_Type_Name']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <input type="hidden" name="branch_id" value="<?= $accounts_data[0]['Branch_ID'] ?? 1; ?>">

                        <label for="details">Purpose/Details (Optional):</label>
                        <textarea name="details" id="details" rows="3" placeholder="e.g., Car purchase, Education fees"></textarea>

                        <?php if ($account_count > 0): ?>
                            <button type="submit" name="apply_loan">Submit Application</button>
                        <?php else: ?>
                            <button type="button" disabled>Cannot Apply (No Active Account)</button>
                        <?php endif; ?>

                    </form>
                </div>

            <?php elseif ($active_tab == 'accounts'): ?>
                <h2>My Active Accounts</h2>
                
                <div class="account-list-container">
                    <?php if ($account_count > 0): ?>
                        <?php foreach ($accounts_data as $account): ?>
                            <div class="card account-item">
                                <div class="account-header">
                                    <h3><?= htmlspecialchars($account['Account_Type_Name']); ?></h3>
                                    <span class="account-status active-status">Active</span>
                                </div>
                                <div class="account-details">
                                    <div class="detail-row">
                                        <span class="detail-label">Account ID:</span>
                                        <span class="detail-value"><?= $account['Account_ID']; ?></span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Branch ID:</span>
                                        <span class="detail-value"><?= $account['Branch_ID']; ?></span>
                                    </div>
                                    <div class="detail-row balance-row">
                                        <span class="detail-label">Current Balance:</span>
                                        <span class="detail-value balance-amount">$<?= number_format($account['Balance'], 2); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="info-detail">You have no active accounts.</p>
                    <?php endif; ?>
                </div>
            
            <?php elseif ($active_tab == 'transactions'): ?>

                <h2>Initiate Transaction</h2>

                <div class="transaction-grid">
                    
                    <div class="card transaction-form-container loan-payment-card">
                        <h3>➡️ Make a Loan Payment</h3>
                        <?php if (count($loan_history) > 0 && $account_count > 0): ?> 
                            <form action="process_transaction.php" method="POST">
                                <input type="hidden" name="action" value="loan_payment">
                                <input type="hidden" name="branch_id" value="<?= $loan_history[0]['Branch_ID'] ?? 1; ?>"> 
                                
                                <label for="loan_id">Select Loan to Pay:</label>
                                <select name="loan_id" id="loan_id" required>
                                    <option value="">--Select Loan ID--</option>
                                    <?php foreach ($loan_history as $loan): ?>
                                        <option value="<?= $loan['Loan_ID']; ?>">
                                            Loan ID: <?= $loan['Loan_ID']; ?> (Remaining: $<?= number_format($loan['Loan_ammount'], 2); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <label for="source_account_id">Select Account to Pay From:</label>
                                <select name="source_account_id" id="source_account_id" required>
                                    <option value="">--Select Source Account--</option>
                                    <?php foreach ($accounts_data as $account): ?>
                                        <option value="<?= $account['Account_ID']; ?>">
                                            Account ID: <?= htmlspecialchars($account['Account_ID']); ?> (Type: <?= htmlspecialchars($account['Account_Type_Name']); ?> | Bal: $<?= number_format($account['Balance'], 2); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <label for="payment_amount">Payment Amount:</label>
                                <input type="number" name="amount" id="payment_amount" placeholder="Enter Amount" required min="0.01" step="0.01">
                                
                                <button type="submit">Submit Payment</button>
                            </form>
                        <?php elseif (count($loan_history) > 0 && $account_count === 0): ?>
                             <p class="info-detail" style="text-align: center; color: #ffc107;">You have active loans, but need an active bank account to make a payment.</p>
                        <?php else: ?>
                            <p class="info-detail" style="text-align: center; color: #ffc107;">You have no active loans to pay.</p>
                        <?php endif; ?>
                    </div>

                    <div class="card transaction-form-container account-deposit-card">
                        <h3>⬅️ Deposit Money</h3>
                        <?php if ($account_count > 0): ?>
                            <form action="process_transaction.php" method="POST">
                                <input type="hidden" name="action" value="deposit">
                                <input type="hidden" name="branch_id" value="<?= $accounts_data[0]['Branch_ID'] ?? 1; ?>"> 

                                <label for="deposit_account">Select Account:</label>
                                <select name="deposit_account_id" id="deposit_account_id" required>
                                    <option value="">--Select Deposit Account--</option>
                                    <?php foreach ($accounts_data as $account): ?>
                                        <option value="<?= $account['Account_ID']; ?>">
                                            Account ID: <?= htmlspecialchars($account['Account_ID']); ?> (Bal: $<?= number_format($account['Balance'], 2); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <label for="deposit_amount">Deposit Amount:</label>
                                <input type="number" name="amount" id="deposit_amount" placeholder="Enter Amount" required min="0.01" step="0.01">
                                
                                <button type="submit">Submit Deposit</button>
                            </form>
                        <?php else: ?>
                            <p class="info-detail" style="text-align: center; color: #ffc107;">You must have an active account to make a deposit.</p>
                        <?php endif; ?>
                    </div>

                </div>

                <h3 class="transactions-history-header">Recent Transaction History</h3>
                <div class="card transaction-history-container" style="padding: 0;">
                    <?php if (!empty($transaction_history)): ?>
                        <table class="data-table transaction-table"> 
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Type</th>
                                    <th>Details</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                    foreach ($transaction_history as $tx): 
                                        // Check if the transaction is an inflow (to account is one of the user's)
                                        $is_inflow = in_array($tx['to_account_ID'], $account_ids);
                                        $flow_sign = $is_inflow ? '+' : '-';
                                        $flow_class = $is_inflow ? 'inflow' : 'outflow';
                                        $icon = $is_inflow ? '⬇️' : '⬆️'; // Down arrow for money in, up arrow for money out
                                        $transaction_type_display = htmlspecialchars($tx['Transaction_type']);

                                        // Determine the source/destination text for display
                                        $BANK_ACCOUNT_ID = 999999; // Re-define locally for use here

                                        if ($tx['from_account_ID'] == $BANK_ACCOUNT_ID) {
                                            $detail_text = "From **Bank** to Account #**" . $tx['to_account_ID'] . "**";
                                        } elseif ($tx['to_account_ID'] == $BANK_ACCOUNT_ID) {
                                            $detail_text = "Payment from Account #**" . $tx['from_account_ID'] . "** to **Bank**";
                                        } else {
                                            // Transfer between two of the user's own accounts
                                            $detail_text = "Transfer: #**" . $tx['from_account_ID'] . "** -> #**" . $tx['to_account_ID'] . "**";
                                        }
                                ?>
                                <tr class="<?= $flow_class; ?>">
                                    <td data-label="Date:"><?= date('M d, Y', strtotime($tx['Created_at'])); ?><br><small><?= date('H:i:s', strtotime($tx['Created_at'])); ?></small></td>
                                    <td data-label="Type:"><?= $icon; ?> <?= $transaction_type_display; ?></td>
                                    <td data-label="Details:"><?= $detail_text; ?></td>
                                    <td data-label="Amount:" class="amount-cell">
                                        <span class="amount-<?= $flow_class; ?>">
                                            <?= $flow_sign . number_format($tx['Ammount'], 2); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="info-detail" style="text-align: center; padding: 15px;">No recent transactions found for your active accounts.</p>
                    <?php endif; ?>
                </div>

            <?php endif; ?>
            
        </div>
    </div>

    <script>
        // Loan application script for dynamic Max Amount
        document.getElementById('loan_type').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            // Get the max value from the data-max attribute, or use the global limit if not found
            const maxAmount = selectedOption.getAttribute('data-max') || <?= $max_global_loan_limit; ?>; 
            
            const loanAmountInput = document.getElementById('loan_amount');
            loanAmountInput.max = maxAmount;
            
            const loanAmountLabel = document.querySelector('#loan_amount_label');
            loanAmountLabel.innerHTML = `Loan Amount (Max: $${Number(maxAmount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}):`;

            // If the current value exceeds the new max, reset the value
            if (parseFloat(loanAmountInput.value) > parseFloat(maxAmount)) {
                loanAmountInput.value = maxAmount;
            } 
        });
        
        // Auto-run the function on page load in case a loan type is pre-selected
        document.addEventListener('DOMContentLoaded', function() {
            // Check if the loan_type element exists before dispatching the event
            const loanTypeElement = document.getElementById('loan_type');
            if (loanTypeElement) {
                loanTypeElement.dispatchEvent(new Event('change'));
            }
        });
    </script>
</body>
</html>
<?php $bank_conn->close(); ?>