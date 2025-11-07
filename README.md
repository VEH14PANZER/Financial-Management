# Financial-Management
My first project

Import the financialdb and import the triggers and procedure

Stored Procedure

CREATE DEFINER=`` PROCEDURE `GetAccountDetails` (IN `input_AccountID` BIGINT)
BEGIN
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
END

Trigger Statement trg_CheckLoanLimit

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

trg_UpdateAccountBalance (Transaction Processing)

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
