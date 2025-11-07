<?php
session_start();

// 1. SECURITY CHECK
if (!isset($_SESSION['name']) || $_SESSION['role'] !== 'user' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

// 2. CONNECTION TO FINANCIALDB
$bank_conn = new mysqli("localhost", "root", "", "financialdb");
if ($bank_conn->connect_error) {
    $_SESSION['error_message'] = "System Error: Bank connection failed.";
    header("Location: user_page.php?tab=transactions");
    exit();
}

$BANK_ACCOUNT_ID = 999999; 

$bank_conn->begin_transaction();

try {
    // 3. COMMON INPUTS
    $action = $_POST['action'] ?? '';
    $customer_id = (int)($_SESSION['customer_id'] ?? 0); 
    $amount = (float)($_POST['amount'] ?? 0.00);
    $branch_id = (int)($_POST['branch_id'] ?? 0);
    $current_time = date('Y-m-d H:i:s');
    
    // Format amount as a safe string for the database
    $amount_str = number_format($amount, 2, '.', ''); 

    if ($customer_id === 0) {
        throw new Exception("Customer session ID is missing.");
    }
    if ($amount <= 0) {
        throw new Exception("Invalid amount specified for transaction.");
    }
    
    // A. HANDLE LOAN PAYMENT 
    if ($action === 'loan_payment') {
        $loan_id = (int)($_POST['loan_id'] ?? 0);
        $source_account_id = (int)($_POST['source_account_id'] ?? 0);
        $transaction_type = 'Loan Payment';

        if ($loan_id <= 0 || $source_account_id <= 0) {
            throw new Exception("Missing Loan ID or Source Account ID for payment.");
        }

        // 1. Check current account balance (still necessary to prevent overdraft)
        $stmt_check = $bank_conn->prepare("SELECT Balance FROM accounts WHERE Account_ID = ? AND Customer_ID = ? AND Account_status = 'Active'");
        $stmt_check->bind_param("ii", $source_account_id, $customer_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        $account_row = $result_check->fetch_assoc();
        $stmt_check->close();

        if (!$account_row) {
            throw new Exception("Source Account not found, inactive, or does not belong to you.");
        }
        if ($account_row['Balance'] < $amount) {
            throw new Exception("Insufficient funds in Account ID: $source_account_id.");
        }
    
        
        // 3. CREDIT: Decrease the outstanding loan amount
        $sql_loan_update = "UPDATE loans SET Loan_ammount = Loan_ammount - ? WHERE Loan_ID = ? AND Loan_ammount >= ?";
        $stmt_loan_update = $bank_conn->prepare($sql_loan_update);
        $stmt_loan_update->bind_param("sis", $amount_str, $loan_id, $amount_str); 
        if (!$stmt_loan_update->execute() || $bank_conn->affected_rows === 0) {
            throw new Exception("Error updating loan amount. Check Loan ID or remaining amount.");
        }
        $stmt_loan_update->close();

        // 4. RECORD TRANSACTION (This will fire the trigger that updates the balance)
        $sql_transaction = "INSERT INTO transactions (Transaction_type, Ammount, from_account_ID, to_account_ID, Branch_ID, Created_at) 
                            VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_transaction = $bank_conn->prepare($sql_transaction);
        $stmt_transaction->bind_param("ssiiis", $transaction_type, $amount_str, $source_account_id, $BANK_ACCOUNT_ID, $branch_id, $current_time);

        if (!$stmt_transaction->execute()) {
            throw new Exception("Error recording payment transaction: " . $stmt_transaction->error);
        }
        $stmt_transaction->close();

        // SUCCESS
        $bank_conn->commit();
        $_SESSION['success_message'] = "✅ Loan Payment of **$" . number_format($amount, 2) . "** successful for Loan ID: $loan_id.";
        header("Location: user_page.php?tab=transactions");
        exit();
    }
    

    // B. HANDLE ACCOUNT DEPOSIT
    elseif ($action === 'deposit') {
        $deposit_account_id = (int)($_POST['deposit_account_id'] ?? 0);
        $transaction_type = 'Deposit';
        
        if ($deposit_account_id <= 0) {
            throw new Exception("Missing Deposit Account ID.");
        }


        // 2. RECORD TRANSACTION (This insert will fire the trigger)
        $sql_transaction = "INSERT INTO transactions (Transaction_type, Ammount, from_account_ID, to_account_ID, Branch_ID, Created_at) 
                            VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_transaction = $bank_conn->prepare($sql_transaction);
        // from_account_ID = bank's placeholder, to_account_ID = user's account
        $stmt_transaction->bind_param("ssiiis", $transaction_type, $amount_str, $BANK_ACCOUNT_ID, $deposit_account_id, $branch_id, $current_time);

        if (!$stmt_transaction->execute()) {
            throw new Exception("Error recording deposit transaction: " . $stmt_transaction->error);
        }
        $stmt_transaction->close();

        // SUCCESS
        $bank_conn->commit();
        $_SESSION['success_message'] = "✅ Deposit of **$" . number_format($amount, 2) . "** successful into Account ID: $deposit_account_id.";
        header("Location: user_page.php?tab=transactions");
        exit();

    } else {
        throw new Exception("Invalid transaction action specified.");
    }

} catch (Exception $e) {
    // Failure: Rollback changes
    $bank_conn->rollback();
    $_SESSION['error_message'] = "Transaction Failed: " . $e.getMessage();
    header("Location: user_page.php?tab=transactions");
    exit();
}


$bank_conn->close();
?>