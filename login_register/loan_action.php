<?php
session_start();

// 1. Authorization Check (Only Admin can perform actions)
if (!isset($_SESSION['name']) || $_SESSION['role'] !== 'admin') { 
    $_SESSION['error_message'] = "Authorization failed. Please log in as an administrator.";
    header("Location: index.php");
    exit();
}

// 2. Database Connection
$bank_conn = new mysqli("localhost", "root", "", "financialdb");
if ($bank_conn->connect_error) {
    $_SESSION['error_message'] = "System Error: Bank connection failed.";
    header("Location: admin_page.php");
    exit();
}

// 3. Handle DELETE Action (POST Request)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    // Ensure Loan_ID is a valid integer
    $loan_id = (int)($_POST['loan_id'] ?? 0);

    if ($loan_id > 0) {
        try {
            $current_time = date('Y-m-d H:i:s');
            $sql_delete = "UPDATE loans SET Deleted_at = ? WHERE Loan_ID = ? AND Deleted_at IS NULL";
            
            $stmt = $bank_conn->prepare($sql_delete);
            
            if ($stmt === false) {
                 throw new Exception("SQL Prepare Failed: " . $bank_conn->error);
            }
            
            // Bind the current timestamp and the Loan_ID
            $stmt->bind_param("si", $current_time, $loan_id);
            
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $_SESSION['success_message'] = "Loan ID **{$loan_id}** successfully deleted (soft delete).";
                } else {
                    $_SESSION['error_message'] = "Loan ID **{$loan_id}** not found, or it was already deleted.";
                }
            } else {
                throw new Exception("SQL Execute Failed: " . $stmt->error);
            }
            $stmt->close();

        } catch (Exception $e) {
            $_SESSION['error_message'] = "Database Error during deletion: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = "Invalid Loan ID for deletion.";
    }
}

// 4. Handle EDIT Action (GET Request - Placeholder)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'edit') {
    $loan_id = (int)($_GET['loan_id'] ?? 0);

    if ($loan_id > 0) {
        
        $_SESSION['info_message'] = "Edit functionality is initiated for Loan ID: **{$loan_id}**. You would typically redirect to a separate form to handle the update logic.";
    } else {
        $_SESSION['error_message'] = "Invalid Loan ID for editing.";
    }
}


// Redirect back to the admin page for all scenarios
$bank_conn->close();
header("Location: admin_page.php");
exit();
?>