<?php

session_start();
require_once 'config.php'; // Connects to financialdb ($conn)

// 1. REGISTRATION LOGIC
if (isset($_POST['register'])) {
    // 1. INPUTS 
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    // 2. CHECK EMAIL EXISTENCE 
    $stmt_check = $conn->prepare("SELECT email FROM users WHERE email = ?");
    $stmt_check->bind_param("s", $email);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        $_SESSION['register_error'] = 'Email Already Exists';
        $_SESSION['active_form'] = 'register';
    } else {
        $customer_id_to_insert = NULL;
        $db_error = false; 

        // PHASE 1: CREATE BANK CUSTOMER RECORD (for 'user' role)
        if ($role === 'user') {
            $conn->begin_transaction(); // Start transaction
            try {
                // A. Insert into customer table
                $sql_customer = "INSERT INTO customer (First_name, Last_name) VALUES (?, ?)";
                $stmt_customer = $conn->prepare($sql_customer);
                $stmt_customer->bind_param("ss", $first_name, $last_name);
                $stmt_customer->execute();
                $customer_id_to_insert = $conn->insert_id;
                $stmt_customer->close();

                // B. Auto-create a default account
                $default_account_type = 2; // Savings
                $default_branch = 102;     // Main Street Branch
                $default_balance = 0.00;
                $sql_account = "INSERT INTO accounts (Customer_ID, Account_Type_ID, Branch_ID, Balance) VALUES (?, ?, ?, ?)";
                $stmt_account = $conn->prepare($sql_account);
                $stmt_account->bind_param("iiid", $customer_id_to_insert, $default_account_type, $default_branch, $default_balance);
                $stmt_account->execute();
                $stmt_account->close();

                // C. Auto-create an initial 'deposit' account
                $default_branch_id = 102; // Branch ID
                $default_account_type = 1; // 'Checking'
                        
                $sql_account = "INSERT INTO accounts (Customer_ID, Account_Type_ID, Balance, Branch_ID, Account_status) VALUES (?, ?, 0.00, ?, 'Active')";
                $stmt_account = $conn->prepare($sql_account);
                // Bind Customer_ID (i), Account_Type_ID (i), and Branch_ID (i)
                $stmt_account->bind_param("iii", $customer_id_to_insert, $default_account_type, $default_branch_id); 
                $stmt_account->execute();
                $stmt_account->close();
                
                $conn->commit(); // Commit all financialdb changes
            } catch (Exception $e) {
                $conn->rollback();
                $db_error = true;
                $_SESSION['register_error'] = 'Failed to create financial record: ' . $e->getMessage();
            }
        } // end if role is 'user'

        // PHASE 2: CREATE USERS_DB LOGIN RECORD 
        if (!$db_error) {
            $stmt_insert = null;
            
            if ($role === 'user') {
                // Query for 'user' role: includes new columns AND Customer_ID
                $sql_insert = "INSERT INTO users (First_name, Last_name, email, password, role, Customer_ID) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt_insert = $conn->prepare($sql_insert);
                $stmt_insert->bind_param("sssssi", $first_name, $last_name, $email, $password, $role, $customer_id_to_insert);
            } else {
                // Query for 'admin' role: includes new columns, excludes Customer_ID
                $sql_insert = "INSERT INTO users (First_name, Last_name, email, password, role) VALUES (?, ?, ?, ?, ?)";
                $stmt_insert = $conn->prepare($sql_insert);
                $stmt_insert->bind_param("sssss", $first_name, $last_name, $email, $password, $role);
            }
            
            if ($stmt_insert && $stmt_insert->execute()) {
                $_SESSION['login_success'] = 'Registration successful! Please log in.';
                $_SESSION['active_form'] = 'login';
            } else {
                $_SESSION['register_error'] = 'Failed to create login account.';
                // Rollback customer creation if user creation fails
                if ($role === 'user') {
                    // ideally handled by a single transaction,
                    // as for now, we just report the error.
                    $_SESSION['register_error'] .= ' (Customer record might be orphaned)';
                }
            }
            if ($stmt_insert) $stmt_insert->close();
        }
    }
    $stmt_check->close();
    header("Location: index.php");
    exit();
}

// 2. LOGIN LOGIC
if (isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Update SELECT statement to get First_name and Last_name
    $stmt_login = $conn->prepare("SELECT First_name, Last_name, password, role, Customer_ID FROM users WHERE email = ?");
    
    $stmt_login->bind_param("s", $email);
    $stmt_login->execute();
    $result = $stmt_login->get_result();
    $stmt_login->close();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            // Set session name by combining First_name and Last_name
            $_SESSION['name'] = $user['First_name'] . ' ' . $user['Last_name'];
            
            $_SESSION['role'] = $user['role'];
            $_SESSION['customer_id'] = $user['Customer_ID']; 

            if ($user['role'] === 'admin') {
                header("Location: admin_page.php");
            } else {
                header("Location: user_page.php");
            }
            exit();
        }
    }

    $_SESSION['login_error'] = 'Invalid Email or Password';
    $_SESSION['active_form'] = 'login';
    header("Location: index.php");
    exit();
}

?>
