<?php
session_start();
require 'db_connection.php';


if (!isset($_SESSION['family_identifier'])) {
    header("Location: family-login.php");
    exit();
}

$family_identifier = $_SESSION['family_identifier'];
$household_head_id = $_SESSION['household_head_id'];

try {
    
$household_sql = "SELECT h.*, hh.full_name, hh.id_number, hh.date_of_birth, hh.gender, 
                         hh.marital_status, hh.education_level, hh.occupation,
                         hh.monthly_income, hh.spouse_name, hh.spouse_id,
                         hh.spouse_date_of_birth, hh.spouse_gender,
                         hh.spouse_occupation, hh.spouse_education_level, hh.spouse_monthly_income
                  FROM households h 
                  JOIN household_heads hh ON h.household_id = hh.household_id 
                  WHERE h.family_identifier = ? AND h.household_head_id = ?";
    $household_stmt = $conn->prepare($household_sql);
    $household_stmt->bind_param("ss", $family_identifier, $household_head_id);
    $household_stmt->execute();
    $household_result = $household_stmt->get_result();
    $household = $household_result->fetch_assoc();
    
    if (!$household) {
        throw new Exception("Household not found");
    }
    
    $members_sql = "SELECT * FROM family_members 
                    WHERE family_identifier = ? 
                    AND relationship_to_head NOT IN ('Head', 'Spouse') 
                    ORDER BY date_of_birth";
    $members_stmt = $conn->prepare($members_sql);
    $members_stmt->bind_param("s", $family_identifier);
    $members_stmt->execute();
    $members_result = $members_stmt->get_result();
    $family_members = [];
    
    while ($member = $members_result->fetch_assoc()) {
        $family_members[] = $member;
    }
    
} catch (Exception $e) {
    $error_message = $e->getMessage();
}


if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: family-login.php");
    exit();
}

function calculateCurrentAge($date_of_birth) {
    if (empty($date_of_birth)) return 'Unknown';
    
    $dob = new DateTime($date_of_birth);
    $today = new DateTime();
    $age = $today->diff($dob)->y;
    return $age;
}

function formatIncomeRange($income) {
    if (empty($income)) return 'Not specified';
    
    $ranges = [
        'Under 10000' => 'Under Ksh 10,000',
        '10000-25000' => 'Ksh 10,000 - 25,000',
        '25001-50000' => 'Ksh 25,001 - 50,000',
        '50001-100000' => 'Ksh 50,001 - 100,000',
        '100001-200000' => 'Ksh 100,001 - 200,000',
        '200001-500000' => 'Ksh 200,001 - 500,000',
        'Over 500000' => 'Over Ksh 500,000'
    ];
    
    return $ranges[$income] ?? $income;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Family Dashboard - NPR Kenya</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background: #f5f5f5;
            color: #333;
        }
        
        .dashboard-header {
            background: linear-gradient(135deg, #006400 0%, #228B22 100%);
            color: white;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-content h1 {
            font-size: 1.5em;
        }
        
        .user-info {
            text-align: right;
        }
        
        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-top: 5px;
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .card h3 {
            color: #006400;
            margin-bottom: 10px;
        }
        
        .card .number {
            font-size: 2em;
            font-weight: bold;
            color: #333;
        }
        
        .section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .section h2 {
            color: #006400;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e1e1e1;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
        }
        
        .info-item {
            margin-bottom: 10px;
        }
        
        .info-label {
            font-weight: bold;
            color: #666;
        }
        
        .info-value {
            color: #333;
        }
        
        .spouse-section {
            background: #f0f8ff;
            border-left: 4px solid #006400;
            padding: 20px;
            margin-top: 15px;
            border-radius: 5px;
        }
        
        .spouse-section h3 {
            color: #006400;
            margin-bottom: 15px;
        }
        
        .income-badge {
            background: #d4edda;
            color: #155724;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: bold;
        }
        
        .family-members-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .family-members-table th,
        .family-members-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e1e1e1;
        }
        
        .family-members-table th {
            background: #f8f9fa;
            font-weight: bold;
            color: #006400;
        }
        
        .family-members-table tr:hover {
            background: #f8f9fa;
        }
        
        .minor-badge {
            background: #ffeaa7;
            color: #856404;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.8em;
        }
        
        .adult-badge {
            background: #d4edda;
            color: #155724;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.8em;
        }
        
        .actions {
            text-align: center;
            margin-top: 30px;
        }
        
        .print-btn {
            background: #006400;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
        }
        
        .print-btn:hover {
            background: #228B22;
        }
        
        @media print {
            .dashboard-header, .logout-btn, .print-btn {
                display: none;
            }
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .no-members {
            text-align: center;
            color: #666;
            padding: 20px;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="header-content">
            <h1>🏠 Family Dashboard - NPR Kenya</h1>
            <div class="user-info">
                Welcome, <?php echo htmlspecialchars($household['full_name']); ?>
                <br>
                <a href="?logout=1" class="logout-btn">🚪 Logout</a>
            </div>
        </div>
    </div>
    
    <div class="dashboard-container">
        <?php if (isset($error_message)): ?>
            <div class="error-message">
                <strong>Error:</strong> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
    
        <div class="summary-cards">
            <div class="card">
                <h3>Total Family Members</h3>
                <div class="number"><?php echo $household['total_members']; ?></div>
            </div>
            <div class="card">
                <h3>Adults (18+)</h3>
                <div class="number"><?php echo $household['adult_count']; ?></div>
            </div>
            <div class="card">
                <h3>Minors (&lt;18)</h3>
                <div class="number"><?php echo $household['minor_count']; ?></div>
            </div>
            <div class="card">
                <h3>Family Code</h3>
                <div class="number" style="font-size: 1.2em;"><?php echo $household['family_identifier']; ?></div>
            </div>
        </div>
        
        
        <div class="section">
            <h2>👤 Household Head Information</h2>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Full Name:</span>
                    <span class="info-value"><?php echo htmlspecialchars($household['full_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">ID Number:</span>
                    <span class="info-value"><?php echo htmlspecialchars($household['id_number']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Date of Birth:</span>
                    <span class="info-value"><?php echo date('F j, Y', strtotime($household['date_of_birth'])); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Gender:</span>
                    <span class="info-value"><?php echo htmlspecialchars($household['gender']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Marital Status:</span>
                    <span class="info-value"><?php echo htmlspecialchars($household['marital_status']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Education Level:</span>
                    <span class="info-value"><?php echo htmlspecialchars($household['education_level'] ?? 'Not specified'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Occupation:</span>
                    <span class="info-value"><?php echo htmlspecialchars($household['occupation'] ?? 'Not specified'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Monthly Income:</span>
                    <span class="info-value">
                        <?php echo formatIncomeRange($household['monthly_income'] ?? ''); ?>
                        <?php if (!empty($household['monthly_income'])): ?>
                            <span class="income-badge"><?php echo $household['monthly_income']; ?></span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            
            
<?php if ($household['marital_status'] === 'Married' && !empty($household['spouse_name'])): ?>
<div class="spouse-section">
    <h3>💑 Spouse Information</h3>
    <div class="info-grid">
        <div class="info-item">
            <span class="info-label">Spouse Name:</span>
            <span class="info-value"><?php echo htmlspecialchars($household['spouse_name']); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Spouse ID:</span>
            <span class="info-value"><?php echo htmlspecialchars($household['spouse_id']); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Date of Birth:</span>
            <span class="info-value">
                <?php echo !empty($household['spouse_date_of_birth']) ? date('F j, Y', strtotime($household['spouse_date_of_birth'])) : 'Not specified'; ?>
            </span>
        </div>
        <div class="info-item">
            <span class="info-label">Age:</span>
            <span class="info-value">
                <?php echo !empty($household['spouse_date_of_birth']) ? calculateCurrentAge($household['spouse_date_of_birth']) . ' years' : 'Not specified'; ?>
            </span>
        </div>
        <div class="info-item">
            <span class="info-label">Gender:</span>
            <span class="info-value"><?php echo htmlspecialchars($household['spouse_gender'] ?? 'Not specified'); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Education Level:</span>
            <span class="info-value"><?php echo htmlspecialchars($household['spouse_education_level'] ?? 'Not specified'); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Occupation:</span>
            <span class="info-value"><?php echo htmlspecialchars($household['spouse_occupation'] ?? 'Not specified'); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Monthly Income:</span>
            <span class="info-value">
                <?php echo formatIncomeRange($household['spouse_monthly_income'] ?? ''); ?>
                <?php if (!empty($household['spouse_monthly_income'])): ?>
                    <span class="income-badge"><?php echo $household['spouse_monthly_income']; ?></span>
                <?php endif; ?>
            </span>
        </div>
    </div>
</div>
<?php endif; ?>



        <div class="section">
            <h2>📍 Location Information</h2>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">County:</span>
                    <span class="info-value"><?php echo htmlspecialchars($household['county']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Sub-County:</span>
                    <span class="info-value"><?php echo htmlspecialchars($household['sub_county']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Ward:</span>
                    <span class="info-value"><?php echo htmlspecialchars($household['ward']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Phone Number:</span>
                    <span class="info-value"><?php echo !empty($household['phone_number']) ? htmlspecialchars($household['phone_number']) : 'Not provided'; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Registration Date:</span>
                    <span class="info-value"><?php echo date('F j, Y \a\t g:i A', strtotime($household['registration_date'])); ?></span>
                </div>
            </div>
        </div>
        
    
        <div class="section">
            <h2>👨‍👩‍👧‍👦 Children & Dependents</h2>
            <?php if (empty($family_members)): ?>
                <div class="no-members">
                    No children or dependents registered in this household.
                </div>
            <?php else: ?>
            <table class="family-members-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Relationship</th>
                        <th>ID Number</th>
                        <th>Date of Birth</th>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Education</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($family_members as $member): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($member['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($member['relationship_to_head']); ?></td>
                        <td><?php echo htmlspecialchars($member['id_number']); ?></td>
                        <td><?php echo date('M j, Y', strtotime($member['date_of_birth'])); ?></td>
                        <td><?php echo $member['age_at_registration']; ?> years</td>
                        <td><?php echo htmlspecialchars($member['gender']); ?></td>
                        <td><?php echo htmlspecialchars($member['education_level'] ?? 'Not specified'); ?></td>
                        <td>
                            <?php if ($member['is_minor']): ?>
                                <span class="minor-badge">Minor</span>
                            <?php else: ?>
                                <span class="adult-badge">Adult</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        
        
        <div class="actions">
            <button onclick="window.print()" class="print-btn">🖨️ Print This Page</button>
        </div>
    </div>
</body>
</html>