-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 07, 2025 at 01:23 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `financialdb`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`` PROCEDURE `GetAccountDetails` (IN `input_AccountID` BIGINT)   BEGIN
    SELECT 
        A.Account_ID,
        A.Balance,
        A.Account_status,
        A.Currency,
        C.First_name,
        C.Last_name,
        B.Branch_Name
    FROM 
        accounts A
    INNER JOIN customer C ON A.Customer_ID = C.Customer_ID
    INNER JOIN branch B ON A.Branch_ID = B.Branch_ID
    WHERE 
        A.Account_ID = input_AccountID;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

CREATE TABLE `accounts` (
  `Account_ID` bigint(20) NOT NULL,
  `Customer_ID` int(11) NOT NULL,
  `Account_Type_ID` int(11) NOT NULL,
  `Branch_ID` int(11) NOT NULL,
  `Balance` bigint(20) DEFAULT 0,
  `Account_status` varchar(50) DEFAULT 'Active',
  `Currency` varchar(3) DEFAULT 'USD',
  `Created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `Deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `accounts`
--

INSERT INTO `accounts` (`Account_ID`, `Customer_ID`, `Account_Type_ID`, `Branch_ID`, `Balance`, `Account_status`, `Currency`, `Created_at`, `Deleted_at`) VALUES
(1, 10019, 2, 102, 60000, 'Active', 'USD', '2025-10-31 10:58:26', NULL),
(999999, 9999, 1, 102, -90000, 'Active', 'USD', '2025-10-18 13:05:27', NULL),
(1000002, 10021, 1, 102, 40000, 'Active', 'USD', '2025-10-31 11:27:20', NULL),
(1000003, 10022, 2, 102, 40000, 'Active', 'USD', '2025-11-06 01:00:12', NULL),
(1000004, 10022, 1, 102, 0, 'Active', 'USD', '2025-11-06 01:00:12', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `account_type`
--

CREATE TABLE `account_type` (
  `Account_Type_ID` int(11) NOT NULL,
  `Account_Type_Name` varchar(50) NOT NULL,
  `Minimum_Balance` bigint(20) DEFAULT 0,
  `Interest_Rate_Basis` decimal(5,4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `account_type`
--

INSERT INTO `account_type` (`Account_Type_ID`, `Account_Type_Name`, `Minimum_Balance`, `Interest_Rate_Basis`) VALUES
(1, 'Checking', 0, NULL),
(2, 'Savings', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `branch`
--

CREATE TABLE `branch` (
  `Branch_ID` int(11) NOT NULL,
  `Branch_Name` varchar(255) DEFAULT NULL,
  `Branch_Address` varchar(255) DEFAULT NULL,
  `Created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `Deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `branch`
--

INSERT INTO `branch` (`Branch_ID`, `Branch_Name`, `Branch_Address`, `Created_at`, `Deleted_at`) VALUES
(102, 'Main Street Branch', '102 Main Street', '2025-10-18 13:05:27', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `customer`
--

CREATE TABLE `customer` (
  `Customer_ID` int(11) NOT NULL,
  `First_name` varchar(255) NOT NULL,
  `Last_name` varchar(255) NOT NULL,
  `Email` varchar(255) DEFAULT NULL,
  `Created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `Deleted_at` timestamp NULL DEFAULT NULL,
  `Cell_Phone` varchar(20) DEFAULT NULL,
  `Address` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer`
--

INSERT INTO `customer` (`Customer_ID`, `First_name`, `Last_name`, `Email`, `Created_at`, `Deleted_at`, `Cell_Phone`, `Address`) VALUES
(9999, 'Bank', 'System', 'system@bank.com', '2025-10-18 13:05:27', NULL, NULL, NULL),
(10019, 'mickey', 'mariel', NULL, '2025-10-31 10:58:26', NULL, NULL, NULL),
(10021, 'renz', 'enz', NULL, '2025-10-31 11:27:20', NULL, NULL, NULL),
(10022, 'Denmark', 'Ligma', NULL, '2025-11-06 01:00:12', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `deposit_accounts`
--

CREATE TABLE `deposit_accounts` (
  `account_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `account_type` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `deposit_accounts`
--

INSERT INTO `deposit_accounts` (`account_id`, `customer_id`, `account_number`, `account_type`) VALUES
(1, 100065, '1234567890', 'Savings'),
(2, 100065, '9876543210', 'Checking');

-- --------------------------------------------------------

--
-- Table structure for table `loans`
--

CREATE TABLE `loans` (
  `Loan_ID` bigint(20) NOT NULL,
  `Loan_ammount` bigint(20) DEFAULT NULL,
  `Customer_ID` int(11) NOT NULL,
  `date_issued` date DEFAULT NULL,
  `Applied_Interest_Rate` decimal(5,4) DEFAULT NULL,
  `Loan_Type_ID` int(11) NOT NULL,
  `Branch_ID` int(11) NOT NULL,
  `Created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `Deleted_at` timestamp NULL DEFAULT NULL,
  `Loan_Details` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loans`
--

INSERT INTO `loans` (`Loan_ID`, `Loan_ammount`, `Customer_ID`, `date_issued`, `Applied_Interest_Rate`, `Loan_Type_ID`, `Branch_ID`, `Created_at`, `Deleted_at`, `Loan_Details`) VALUES
(11, 10000, 10019, '2025-10-31', NULL, 1, 102, '2025-10-31 04:06:24', NULL, 'Loan'),
(12, 10000, 10019, '2025-10-31', NULL, 1, 102, '2025-10-31 04:09:28', NULL, 'Gambling'),
(13, 10000, 10021, '2025-10-31', NULL, 1, 102, '2025-10-31 04:28:44', NULL, 'Gambling'),
(14, 0, 10019, '2025-10-31', NULL, 1, 102, '2025-10-31 04:29:54', NULL, 'Gambling'),
(15, 10000, 10021, '2025-10-31', NULL, 1, 102, '2025-10-31 04:30:17', NULL, 'gambling'),
(16, 10000, 10022, '2025-11-06', NULL, 1, 102, '2025-11-05 18:42:20', NULL, 'car');

--
-- Triggers `loans`
--
DELIMITER $$
CREATE TRIGGER `trg_CheckLoanLimit` BEFORE INSERT ON `loans` FOR EACH ROW BEGIN
    DECLARE max_limit DECIMAL(20, 2);
    
    SELECT Max_Loan_Amount INTO max_limit
    FROM loan_type
    WHERE Loan_Type_ID = NEW.Loan_Type_ID;

    IF max_limit IS NOT NULL AND NEW.Loan_ammount > max_limit THEN
        SIGNAL SQLSTATE '10000' 
        SET MESSAGE_TEXT = 'Hold up! This loan amount goes over the max limit set for this loan type.';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `loan_type`
--

CREATE TABLE `loan_type` (
  `Loan_Type_ID` int(11) NOT NULL,
  `Loan_Type_Name` varchar(255) DEFAULT NULL,
  `Base_Interest_Rate` decimal(5,4) DEFAULT NULL,
  `Max_Loan_Amount` decimal(20,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loan_type`
--

INSERT INTO `loan_type` (`Loan_Type_ID`, `Loan_Type_Name`, `Base_Interest_Rate`, `Max_Loan_Amount`) VALUES
(1, 'Personal Loan', 0.0600, 100000.00);

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `Transaction_ID` bigint(20) NOT NULL,
  `Transaction_type` varchar(255) DEFAULT NULL,
  `from_account_ID` bigint(20) NOT NULL,
  `to_account_ID` bigint(20) NOT NULL,
  `Currency_Code` varchar(3) DEFAULT NULL,
  `Ammount` bigint(20) DEFAULT NULL,
  `Branch_ID` int(11) NOT NULL,
  `Created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `Deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`Transaction_ID`, `Transaction_type`, `from_account_ID`, `to_account_ID`, `Currency_Code`, `Ammount`, `Branch_ID`, `Created_at`, `Deleted_at`) VALUES
(7, 'Loan Deposit', 999999, 1, NULL, 10000, 102, '2025-10-31 04:06:24', NULL),
(8, 'Loan Deposit', 999999, 1, NULL, 10000, 102, '2025-10-31 04:09:28', NULL),
(9, 'Loan Deposit', 999999, 1000002, NULL, 10000, 102, '2025-10-31 04:28:44', NULL),
(10, 'Loan Deposit', 999999, 1, NULL, 10000, 102, '2025-10-31 04:29:54', NULL),
(11, 'Loan Deposit', 999999, 1000002, NULL, 10000, 102, '2025-10-31 04:30:17', NULL),
(12, 'Deposit', 999999, 1, NULL, 10000, 102, '2025-11-05 06:58:18', NULL),
(14, 'Loan Payment', 1, 999999, NULL, 10000, 102, '2025-11-05 07:04:14', NULL),
(15, 'Deposit', 999999, 1000003, NULL, 10000, 102, '2025-11-05 18:34:28', NULL),
(16, 'Loan Deposit', 999999, 1000003, NULL, 10000, 102, '2025-11-05 18:42:20', NULL);

--
-- Triggers `transactions`
--
DELIMITER $$
CREATE TRIGGER `trg_UpdateAccountBalance` AFTER INSERT ON `transactions` FOR EACH ROW BEGIN
    IF NEW.from_account_ID > 0 THEN
        UPDATE accounts
        SET Balance = Balance - NEW.Ammount
        WHERE Account_ID = NEW.from_account_ID;
    END IF;
    UPDATE accounts
    SET Balance = Balance + NEW.Ammount
    WHERE Account_ID = NEW.to_account_ID;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `First_name` varchar(255) NOT NULL,
  `Last_name` varchar(255) NOT NULL,
  `Email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `Customer_ID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `First_name`, `Last_name`, `Email`, `password`, `role`, `Customer_ID`) VALUES
(1, 'mickey', 'mariel', 'mickey@gmail.com', '$2y$10$BozLKvKReIO4ArFoO6wTWu1ikZEgTgVBjz/T2MsCbWTqgAcSnsXlm', 'user', 10019),
(2, 'veh14', 'panzerausf', 'veh14@panzerausf', '$2y$10$7MUTWjOCp7tMz2btuSCt4e7rOBHmrTUdHwv13qmCrNrquVKjLxhCW', 'admin', NULL),
(3, 'renz', 'enz', 'renz@gmail.com', '$2y$10$4Eoh4g7OhAFM5a51yzyyteAtXslju3md00XSB/SHAXoZRb2G/mDHC', 'user', 10021),
(4, 'Denmark', 'Ligma', 'Denmark@gmail.com', '$2y$10$I9LMh34ky8gqwvtd2TYrZun6JiLVaUxXXelNdQj1FfHHnBsVJmtki', 'user', 10022);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`Account_ID`),
  ADD KEY `idx_AC_Customer` (`Customer_ID`),
  ADD KEY `idx_AC_Branch` (`Branch_ID`),
  ADD KEY `idx_AC_AccountType` (`Account_Type_ID`);

--
-- Indexes for table `account_type`
--
ALTER TABLE `account_type`
  ADD PRIMARY KEY (`Account_Type_ID`),
  ADD UNIQUE KEY `Account_Type_Name` (`Account_Type_Name`);

--
-- Indexes for table `branch`
--
ALTER TABLE `branch`
  ADD PRIMARY KEY (`Branch_ID`);

--
-- Indexes for table `customer`
--
ALTER TABLE `customer`
  ADD PRIMARY KEY (`Customer_ID`),
  ADD UNIQUE KEY `Email` (`Email`);

--
-- Indexes for table `deposit_accounts`
--
ALTER TABLE `deposit_accounts`
  ADD PRIMARY KEY (`account_id`);

--
-- Indexes for table `loans`
--
ALTER TABLE `loans`
  ADD PRIMARY KEY (`Loan_ID`),
  ADD KEY `fk_Loans_Branch` (`Branch_ID`),
  ADD KEY `fk_Loans_LoanType` (`Loan_Type_ID`),
  ADD KEY `idx_loans_customer_loan_type_branch` (`Customer_ID`,`Loan_Type_ID`,`Branch_ID`);

--
-- Indexes for table `loan_type`
--
ALTER TABLE `loan_type`
  ADD PRIMARY KEY (`Loan_Type_ID`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`Transaction_ID`),
  ADD KEY `fk_Transactions_ToAccount` (`to_account_ID`),
  ADD KEY `fk_Transactions_Branch` (`Branch_ID`),
  ADD KEY `idx_transactions_from_account_to_account_branch` (`from_account_ID`,`to_account_ID`,`Branch_ID`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`Email`),
  ADD KEY `fk_user_customer` (`Customer_ID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accounts`
--
ALTER TABLE `accounts`
  MODIFY `Account_ID` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1000005;

--
-- AUTO_INCREMENT for table `customer`
--
ALTER TABLE `customer`
  MODIFY `Customer_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10023;

--
-- AUTO_INCREMENT for table `deposit_accounts`
--
ALTER TABLE `deposit_accounts`
  MODIFY `account_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `loans`
--
ALTER TABLE `loans`
  MODIFY `Loan_ID` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `loan_type`
--
ALTER TABLE `loan_type`
  MODIFY `Loan_Type_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `Transaction_ID` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `accounts`
--
ALTER TABLE `accounts`
  ADD CONSTRAINT `fk_AC_AccountType` FOREIGN KEY (`Account_Type_ID`) REFERENCES `account_type` (`Account_Type_ID`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_AC_Branch` FOREIGN KEY (`Branch_ID`) REFERENCES `branch` (`Branch_ID`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_AC_Customer` FOREIGN KEY (`Customer_ID`) REFERENCES `customer` (`Customer_ID`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints for table `loans`
--
ALTER TABLE `loans`
  ADD CONSTRAINT `fk_LN_Branch` FOREIGN KEY (`Branch_ID`) REFERENCES `branch` (`Branch_ID`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_LN_Customer` FOREIGN KEY (`Customer_ID`) REFERENCES `customer` (`Customer_ID`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_LN_LoanType` FOREIGN KEY (`Loan_Type_ID`) REFERENCES `loan_type` (`Loan_Type_ID`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `fk_TR_Branch` FOREIGN KEY (`Branch_ID`) REFERENCES `branch` (`Branch_ID`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_TR_FromAccount` FOREIGN KEY (`from_account_ID`) REFERENCES `accounts` (`Account_ID`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_TR_ToAccount` FOREIGN KEY (`to_account_ID`) REFERENCES `accounts` (`Account_ID`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_user_customer` FOREIGN KEY (`Customer_ID`) REFERENCES `customer` (`Customer_ID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
