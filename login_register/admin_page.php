<?php

session_start();

if (!isset($_SESSION['name']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$bank_conn = new mysqli("localhost", "root", "", "financialdb");
if ($bank_conn->connect_error) {
    die("Bank Connection failed: " . $bank_conn->connect_error);
}

// 1. LIVE METRICS CALCULATION

// Total Loans Approved
$sql_count = "SELECT COUNT(Loan_ID) AS total_loans FROM loans WHERE Deleted_at IS NULL;"; // Filter out deleted loans
$result_count = $bank_conn->query($sql_count);
$total_loans_approved = $result_count->fetch_assoc()['total_loans'] ?? 0;

// Total Loan Value
$sql_sum = "SELECT SUM(Loan_ammount) AS total_value FROM loans WHERE Deleted_at IS NULL;"; // Filter out deleted loans
$result_sum = $bank_conn->query($sql_sum);
$total_loan_value = $result_sum->fetch_assoc()['total_value'] ?? 0.00;

// Average Loan Amount
$average_loan_amount = $total_loans_approved > 0 ? $total_loan_value / $total_loans_approved : 0;

// 2. CHART DATA CALCULATION 

$sql_chart_data = "
    SELECT
        lt.Loan_Type_Name,
        COUNT(l.Loan_ID) AS loan_count
    FROM
        loan_type lt
    LEFT JOIN
        loans l ON lt.Loan_Type_ID = l.Loan_Type_ID
    WHERE l.Deleted_at IS NULL OR l.Loan_ID IS NULL /* Only count non-deleted loans for chart */
    GROUP BY
        lt.Loan_Type_Name
    ORDER BY
        loan_count DESC;
";
$result_chart_data = $bank_conn->query($sql_chart_data);

$chart_labels = [];
$chart_data = [];
while ($row = $result_chart_data->fetch_assoc()) {
    $chart_labels[] = $row['Loan_Type_Name'];
    $chart_data[] = $row['loan_count'];
}

// Encode the PHP arrays into JSON format for use in JavaScript
$json_labels = json_encode($chart_labels);
$json_data = json_encode($chart_data);

// 3. DATA TABLE QUERIES (Existing Queries)

$sql_loans = "
    SELECT
        c.First_name, c.Last_name, l.Loan_ID, l.Loan_ammount, l.date_issued, l.Loan_Details,
        lt.Loan_Type_Name, b.Branch_Name, l.Created_at, l.Deleted_at
    FROM
        loans l
    JOIN customer c ON l.Customer_ID = c.Customer_ID
    JOIN loan_type lt ON l.Loan_Type_ID = lt.Loan_Type_ID
    JOIN branch b ON l.Branch_ID = b.Branch_ID
    WHERE l.Deleted_at IS NULL
    ORDER BY l.Created_at DESC;
";

$result_loans = $bank_conn->query($sql_loans);

$sql_transactions = "
    SELECT
        t.Transaction_ID, t.Transaction_type, t.Ammount, t.Created_at,
        t.from_account_ID, t.to_account_ID, b.Branch_Name
    FROM
        transactions t
    JOIN
        branch b ON t.Branch_ID = b.Branch_ID
    ORDER BY t.Created_at DESC
    LIMIT 50;
";
$result_transactions = $bank_conn->query($sql_transactions);

// Message handling (Retrieves and clears session messages from delete_loan.php)
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

?>
<!DOCTYPE HTML>
<html lang="en">

<head>
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="admin_style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="admin-page">
    <div class="container">

        <div class="dashboard-header">
            <div style="width: 150px;"></div>
            <h1>Admin Dashboard, <span><?= htmlspecialchars($_SESSION['name']); ?></span></h1>
            <button onclick="window.location.href='logout.php'">Logout</button>
        </div>
    </div>

    <div class="admin-main-container">
        <div class="metrics-grid">
            <div class="metric-card">
                <h3>Total Loans Approved</h3>
                <p><?= number_format($total_loans_approved); ?></p>
            </div>
            <div class="metric-card">
                <h3>Total Value Borrowed</h3>
                <p>$<?= number_format($total_loan_value, 2); ?></p>
            </div>
            <div class="metric-card">
                <h3>Average Loan Amount</h3>
                <p>$<?= number_format($average_loan_amount, 2); ?></p>
            </div>
        </div>

        <div class="graph-section">
            <h2>Loan Counts by Type</h2>
            <div class="graph-container">
                <canvas id="loanTypeChart"></canvas>
            </div>
        </div>

        <div class="main-content">
            <?php if (!empty($success_message)): ?>
                <div class="success-message">
                    <?= htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <?= htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <h2>All Loan Records</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Customer Name</th>
                        <th>Amount</th>
                        <th>Date Issued</th>
                        <th>Details</th>
                        <th>Type</th>
                        <th>Branch</th>
                        <th>Date Created</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result_loans->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['Loan_ID']); ?></td>
                            <td><?= htmlspecialchars($row['First_name'] . ' ' . $row['Last_name']); ?></td>
                            <td>$<?= number_format($row['Loan_ammount'], 2); ?></td>
                            <td><?= htmlspecialchars($row['date_issued']); ?></td>
                            <td><?= htmlspecialchars($row['Loan_Details']); ?></td>
                            <td><?= htmlspecialchars($row['Loan_Type_Name']); ?></td>
                            <td><?= htmlspecialchars($row['Branch_Name']); ?></td>
                            <td><?= htmlspecialchars($row['Created_at']); ?></td>
                            <td>
                                <form action='loan_action.php' method='POST' style='display:inLine;'
                                onsubmit='return confirm("Are you sure you want to delete Loan ID: <?= htmlspecialchars($row['Loan_ID']); ?>? (Soft Delete - Auditable)");'>
                                <input type='hidden' name='action' value='delete'>
                                <input type='hidden' name='loan_id' value='<?= htmlspecialchars($row['Loan_ID']); ?>'>
                                <button type='submit'
                                style="background-color: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 14px; margin-bottom: 0;">Delete
                                </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <div class="data-section" style="margin-top: 30px;">
                <h2>Recent Transactions (Limit 50)</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>From Account</th>
                            <th>To Account</th>
                            <th>Branch</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result_transactions->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['Transaction_ID']); ?></td>
                                <td><?= htmlspecialchars($row['Transaction_type']); ?></td>
                                <td>$<?= number_format($row['Ammount'], 2); ?></td>
                                <td><?= htmlspecialchars($row['from_account_ID'] ?? 'BANK'); ?></td>
                                <td><?= htmlspecialchars($row['to_account_ID'] ?? 'N/A'); ?></td>
                                <td><?= htmlspecialchars($row['Branch_Name']); ?></td>
                                <td><?= htmlspecialchars($row['Created_at']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>


    <script>
        // Data generated dynamically by PHP
        const labels = <?= $json_labels; ?>;
        const data_points = <?= $json_data; ?>;

        const ctx = document.getElementById('loanTypeChart').getContext('2d');

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Number of Approved Loans',
                    data: data_points,
                    // Dynamic colors for bar chart
                    backgroundColor: [
                        'rgba(76, 191, 245, 0.7)', // Blue
                        'rgba(195, 143, 255, 0.7)', // Purple
                        'rgba(255, 159, 64, 0.7)', // Orange
                        'rgba(75, 192, 192, 0.7)', // Green
                        'rgba(255, 99, 132, 0.7)' // Red
                    ],
                    borderColor: [
                        '#4cbff5',
                        '#c38fff',
                        '#ff9f40',
                        '#4bc0c0',
                        '#ff6384'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: '#f8f9fa'
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    },
                    x: {
                        ticks: {
                            color: '#f8f9fa'
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    }
                },
                plugins: {
                    legend: {
                        labels: {
                            color: '#f8f9fa'
                        }
                    },
                    title: {
                        display: true,
                        text: 'Loan Distribution by Type',
                        color: '#f8f9fa'
                    }
                }
            }
        });
    </script>
</body>

</html>
<?php $bank_conn->close(); ?>