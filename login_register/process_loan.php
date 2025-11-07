<?php
session_start();
// Security check: must be a logged-in user and coming from the loan form
if (!isset($_SESSION['name']) || $_SESSION['role'] !== 'user' || $_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['apply_loan'])) {
    header("Location: index.php");
    exit();
}

// 1. CONNECTION TO FINANCIALDB
$bank_conn = new mysqli("localhost", "root", "", "financialdb");
if ($bank_conn->connect_error) {
    $_SESSION['error_message'] = "System Error: Bank connection failed.";
    header("Location: user_page.php");
    exit();
}

$BANK_ACCOUNT_ID = 999999; // The bank's main account

// 2. INPUT VALIDATION AND CASTING
$customer_id = (int)($_SESSION['customer_id'] ?? 0); // FETCH Customer ID from Session

// Read the correct 'name' attributes from the form
$loan_type_id = (int)($_POST['loan_type_id'] ?? 0); 
$deposit_account_id = (int)($_POST['deposit_account_id'] ?? 0); 
$details = trim($_POST['details'] ?? ''); 

$loan_amount = (float)($_POST['loan_amount'] ?? 0.00);
$branch_id = (int)($_POST['branch_id'] ?? 0);

//  1: Convert float amount to a safe string for all SQL bindings
$loan_amount_str = number_format($loan_amount, 2, '.', '');

// Check if all necessary IDs and values are set
if ($loan_amount <= 0 || $customer_id === 0 || $deposit_account_id === 0 || $branch_id === 0 || $loan_type_id === 0) {
    $_SESSION['error_message'] = "Invalid loan details provided. Please fill out all required fields.";
    header("Location: user_page.php?tab=apply_loan");
    exit();
}

// 2.5. (OPTIONAL BUT RECOMMENDED) SECURITY CHECKS from previous versions
$sql_check_type = "
    SELECT at.Account_Type_Name 
    FROM accounts a
    JOIN account_type at ON a.Account_Type_ID = at.Account_Type_ID
    WHERE a.Account_ID = ? AND a.Customer_ID = ? AND a.Account_status = 'Active'";
$stmt_check_type = $bank_conn->prepare($sql_check_type);
$stmt_check_type->bind_param("ii", $deposit_account_id, $customer_id);
$stmt_check_type->execute();
$result_type = $stmt_check_type->get_result();
$account_data = $result_type->fetch_assoc();
$stmt_check_type->close();

if (!$account_data) {
     $_SESSION['error_message'] = "Security Error: The selected deposit account does not belong to you or is inactive.";
    header("Location: user_page.php?tab=apply_loan");
    exit();
}
if ($account_data['Account_Type_Name'] === 'Checking') {
    $_SESSION['error_message'] = "SECURITY VIOLATION: Loans cannot be deposited into a Checking account.";
    header("Location: user_page.php?tab=apply_loan");
    exit();
}
// End security checks

// 3. TRANSACTION MANAGEMENT (Loan Process)
$bank_conn->begin_transaction();
$current_time = date('Y-m-d H:i:s');
$application_date = date('Y-m-d'); 
$transaction_type = 'Loan Deposit';

try {
    // A. INSERT LOAN RECORD (Uses the Branch ID selected by the user)
    // Using your database's column names: Loan_ammount and Loan_Details
    $sql_loan = "INSERT INTO loans (Customer_ID, Loan_Type_ID, Branch_ID, Loan_ammount, date_issued, Deleted_at, Loan_Details, Created_at) 
                 VALUES (?, ?, ?, ?, ?, NULL, ?, ?)";
    $stmt_loan = $bank_conn->prepare($sql_loan);
    
    // 2: Use 's' (string) for $loan_amount_str instead of 'd' (double)
    $stmt_loan->bind_param("iisssss", $customer_id, $loan_type_id, $branch_id, $loan_amount_str, $application_date, $details, $current_time);

    if (!$stmt_loan->execute()) {
         throw new Exception("Error recording loan: " . $stmt_loan->error);
    }
    $loan_id = $stmt_loan->insert_id;
    $stmt_loan->close();
    // C. RECORD TRANSACTION (This will fire the trigger to update the balance)
    $sql_transaction = "INSERT INTO transactions (Transaction_type, Ammount, from_account_ID, to_account_ID, Branch_ID, Created_at) 
                        VALUES (?, ?, ?, ?, ?, ?)";
    $stmt_transaction = $bank_conn->prepare($sql_transaction);
    
    // 3: Use 's' (string) for $loan_amount_str instead of 'd'
    $stmt_transaction->bind_param("ssiiis", $transaction_type, $loan_amount_str, $BANK_ACCOUNT_ID, $deposit_account_id, $branch_id, $current_time);

    if (!$stmt_transaction->execute()) {
        throw new Exception("Error recording transaction: " . $stmt_transaction->error);
    }
    $stmt_transaction->close();

    // 4. COMMIT & SUCCESS
    $bank_conn->commit();
    $_SESSION['success_message'] = "Loan Application (ID: $loan_id) was Approved. **$" . number_format($loan_amount, 2) . "** has been deposited into Account ID: $deposit_account_id.";
    header("Location: user_page.php");
    exit();

} catch (Exception $e) {
    // 5. ROLLBACK & FAILURE (This is your line 133)
    $bank_conn->rollback();
    $_SESSION['error_message'] = "Loan Application Failed: " . $e->getMessage();
    header("Location: user_page.php?tab=apply_loan");
    exit();
}

$bank_conn->close();

?>
