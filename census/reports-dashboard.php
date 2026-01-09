<?php
session_start();
require 'db_connection.php';


if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed: " . ($conn->connect_error ?? "Unknown error"));
}


if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin-login.php");
    exit();
}

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$county_filter = $_GET['county'] ?? '';
$report_type = $_GET['report_type'] ?? 'overview';

try {
    
    $total_households = $conn->query("SELECT COUNT(*) as total FROM households")->fetch_assoc()['total'];
    $total_individuals = $conn->query("SELECT COUNT(*) as total FROM family_members")->fetch_assoc()['total'];
    
    
    $date_range_stats = $conn->query("
        SELECT 
            COUNT(*) as households_in_range,
            SUM(total_members) as individuals_in_range,
            AVG(total_members) as avg_household_size
        FROM households 
        WHERE DATE(registration_date) BETWEEN '$start_date' AND '$end_date'
    ")->fetch_assoc();
    
    
    $county_stats = $conn->query("
        SELECT county, COUNT(*) as household_count, SUM(total_members) as total_individuals
        FROM households 
        GROUP BY county 
        ORDER BY household_count DESC
    ");
    

    $age_distribution = $conn->query("
        SELECT 
            CASE 
                WHEN age_at_registration < 18 THEN 'Children (0-17)'
                WHEN age_at_registration BETWEEN 18 AND 24 THEN 'Youth (18-24)'
                WHEN age_at_registration BETWEEN 25 AND 34 THEN 'Young Adults (25-34)'
                WHEN age_at_registration BETWEEN 35 AND 59 THEN 'Adults (35-59)'
                ELSE 'Seniors (60+)'
            END as age_group,
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM family_members), 2) as percentage
        FROM family_members 
        GROUP BY age_group
        ORDER BY 
            CASE age_group
                WHEN 'Children (0-17)' THEN 1
                WHEN 'Youth (18-24)' THEN 2
                WHEN 'Young Adults (25-34)' THEN 3
                WHEN 'Adults (35-59)' THEN 4
                ELSE 5
            END
    ");
    
    $gender_stats = $conn->query("
        SELECT 
            gender,
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM family_members), 2) as percentage
        FROM family_members 
        GROUP BY gender
    ");
    
    $monthly_trend = $conn->query("
        SELECT 
            DATE_FORMAT(registration_date, '%Y-%m') as month,
            COUNT(*) as households_registered,
            SUM(total_members) as individuals_registered
        FROM households 
        WHERE registration_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(registration_date, '%Y-%m')
        ORDER BY month
    ");
    
    $education_stats = $conn->query("
        SELECT 
            COALESCE(education_level, 'Not Specified') as education_level,
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM family_members WHERE is_adult = 1), 2) as percentage
        FROM family_members 
        WHERE is_adult = 1
        GROUP BY education_level
        ORDER BY count DESC
    ");
    
    $household_size_stats = $conn->query("
        SELECT 
            total_members as household_size,
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM households), 2) as percentage
        FROM households 
        GROUP BY total_members
        ORDER BY total_members
    ");
    
    $top_subcounties = $conn->query("
        SELECT 
            sub_county,
            county,
            COUNT(*) as household_count,
            SUM(total_members) as total_individuals
        FROM households 
        GROUP BY sub_county, county
        ORDER BY household_count DESC
        LIMIT 10
    ");

} catch (Exception $e) {
    error_log("Reports error: " . $e->getMessage());
    $error = "Error loading report data: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Reports - NPR Kenya</title>
    <link rel="stylesheet" href="admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .reports-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        .reports-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .filters-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .filter-row {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            align-items: end;
        }
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        .filter-group input, .filter-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #667eea;
            margin: 10px 0;
        }
        .stat-label {
            color: #666;
            font-size: 0.9em;
        }
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        .chart-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .chart-card h3 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .tables-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 25px;
        }
        .table-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .table-card h3 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .data-table th,
        .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        .data-table tr:hover {
            background: #f8f9fa;
        }
        .export-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            justify-content: center;
        }
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-size: 16px;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5a6fd8;
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        .report-section {
            margin-bottom: 40px;
        }
        .section-title {
            color: #333;
            border-left: 4px solid #667eea;
            padding-left: 15px;
            margin: 30px 0 20px 0;
        }
        .progress-bar {
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            height: 8px;
            margin: 5px 0;
        }
        .progress-fill {
            height: 100%;
            background: #667eea;
            border-radius: 10px;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #667eea;
            text-decoration: none;
            margin-bottom: 20px;
            font-weight: bold;
        }
        .back-link:hover {
            color: #5a6fd8;
        }
    </style>
</head>
<body>
    <div class="reports-container">
        
        <div class="reports-header">
            <a href="admin-dashboard.php" class="back-link" style="color: white;">
                ← Back to Dashboard
            </a>
            <h1>📊 Advanced Reports & Analytics</h1>
            <p>Comprehensive census data analysis and insights</p>
        </div>

        
        <div class="filters-card">
            <form method="GET" action="reports-dashboard.php" id="reportFilters">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="report_type">Report Type</label>
                        <select name="report_type" id="report_type" onchange="this.form.submit()">
                            <option value="overview" <?php echo $report_type === 'overview' ? 'selected' : ''; ?>>Overview Dashboard</option>
                            <option value="demographic" <?php echo $report_type === 'demographic' ? 'selected' : ''; ?>>Demographic Analysis</option>
                            <option value="geographic" <?php echo $report_type === 'geographic' ? 'selected' : ''; ?>>Geographic Distribution</option>
                            <option value="trends" <?php echo $report_type === 'trends' ? 'selected' : ''; ?>>Registration Trends</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="start_date">Start Date</label>
                        <input type="date" name="start_date" id="start_date" value="<?php echo $start_date; ?>" onchange="this.form.submit()">
                    </div>
                    <div class="filter-group">
                        <label for="end_date">End Date</label>
                        <input type="date" name="end_date" id="end_date" value="<?php echo $end_date; ?>" onchange="this.form.submit()">
                    </div>
                    <div class="filter-group">
                        <label for="county">County Filter</label>
                        <select name="county" id="county" onchange="this.form.submit()">
                            <option value="">All Counties</option>
                            <?php
                            $counties = $conn->query("SELECT DISTINCT county FROM households ORDER BY county");
                            while ($county = $counties->fetch_assoc()) {
                                $selected = $county_filter === $county['county'] ? 'selected' : '';
                                echo "<option value='{$county['county']}' $selected>{$county['county']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="reports-dashboard.php" class="btn btn-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>

        <?php if (isset($error)): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Households</div>
                <div class="stat-number"><?php echo number_format($total_households); ?></div>
                <div class="stat-label">Registered</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Individuals</div>
                <div class="stat-number"><?php echo number_format($total_individuals); ?></div>
                <div class="stat-label">Registered</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Households in Range</div>
                <div class="stat-number"><?php echo number_format($date_range_stats['households_in_range'] ?? 0); ?></div>
                <div class="stat-label"><?php echo date('M j, Y', strtotime($start_date)); ?> - <?php echo date('M j, Y', strtotime($end_date)); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Avg Household Size</div>
                <div class="stat-number"><?php echo number_format($date_range_stats['avg_household_size'] ?? 0, 1); ?></div>
                <div class="stat-label">Persons per household</div>
            </div>
        </div>

        
        <h2 class="section-title">📈 Visual Analytics</h2>
        <div class="charts-grid">
            
            <div class="chart-card">
                <h3>Households by County</h3>
                <canvas id="countyChart" height="300"></canvas>
            </div>

            <div class="chart-card">
                <h3>Age Distribution</h3>
                <canvas id="ageChart" height="300"></canvas>
            </div>

            <div class="chart-card">
                <h3>Gender Distribution</h3>
                <canvas id="genderChart" height="300"></canvas>
            </div>

            <div class="chart-card">
                <h3>Monthly Registration Trend</h3>
                <canvas id="trendChart" height="300"></canvas>
            </div>

            <div class="chart-card">
                <h3>Household Size Distribution</h3>
                <canvas id="householdSizeChart" height="300"></canvas>
            </div>

            <div class="chart-card">
                <h3>Education Levels (Adults)</h3>
                <canvas id="educationChart" height="300"></canvas>
            </div>
        </div>

        
        <h2 class="section-title">📋 Detailed Data Tables</h2>
        <div class="tables-grid">
            
            <div class="table-card">
                <h3>County Statistics</h3>
                <div style="max-height: 400px; overflow-y: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>County</th>
                                <th>Households</th>
                                <th>Individuals</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $county_stats->data_seek(0);
                            while ($county = $county_stats->fetch_assoc()):
                                $percentage = ($county['household_count'] / $total_households) * 100;
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($county['county']); ?></td>
                                <td><?php echo number_format($county['household_count']); ?></td>
                                <td><?php echo number_format($county['total_individuals']); ?></td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <span><?php echo number_format($percentage, 1); ?>%</span>
                                        <div class="progress-bar" style="flex: 1;">
                                            <div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            
            <div class="table-card">
                <h3>Age Distribution</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Age Group</th>
                            <th>Count</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($age = $age_distribution->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($age['age_group']); ?></td>
                            <td><?php echo number_format($age['count']); ?></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span><?php echo $age['percentage']; ?>%</span>
                                    <div class="progress-bar" style="flex: 1;">
                                        <div class="progress-fill" style="width: <?php echo $age['percentage']; ?>%"></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            
            <div class="table-card">
                <h3>Top 10 Sub-Counties</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Sub-County</th>
                            <th>County</th>
                            <th>Households</th>
                            <th>Individuals</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($subcounty = $top_subcounties->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($subcounty['sub_county']); ?></td>
                            <td><?php echo htmlspecialchars($subcounty['county']); ?></td>
                            <td><?php echo number_format($subcounty['household_count']); ?></td>
                            <td><?php echo number_format($subcounty['total_individuals']); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            
            <div class="table-card">
                <h3>Education Levels</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Education Level</th>
                            <th>Count</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($education = $education_stats->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($education['education_level']); ?></td>
                            <td><?php echo number_format($education['count']); ?></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span><?php echo $education['percentage']; ?>%</span>
                                    <div class="progress-bar" style="flex: 1;">
                                        <div class="progress-fill" style="width: <?php echo $education['percentage']; ?>%"></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="filters-card">
            <h3 style="text-align: center;">📥 Export Full Report</h3>
            <div class="export-buttons">
                <a href="export-reports.php?type=full&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&county=<?php echo $county_filter; ?>" class="btn btn-success">
                    📊 Download Full Report (PDF)
                </a>
            </div>
        </div>
    </div>

    <script>
        
        const countyData = [
            <?php
            $county_stats->data_seek(0);
            while ($county = $county_stats->fetch_assoc()) {
                echo "{county: '" . addslashes($county['county']) . "', households: " . $county['household_count'] . "},";
            }
            ?>
        ];

        const ageData = [
            <?php
            $age_distribution->data_seek(0);
            while ($age = $age_distribution->fetch_assoc()) {
                echo "{ageGroup: '" . addslashes($age['age_group']) . "', count: " . $age['count'] . "},";
            }
            ?>
        ];

        const genderData = [
            <?php
            $gender_stats->data_seek(0);
            while ($gender = $gender_stats->fetch_assoc()) {
                echo "{gender: '" . addslashes($gender['gender']) . "', count: " . $gender['count'] . "},";
            }
            ?>
        ];

        const monthlyData = [
            <?php
            $monthly_trend->data_seek(0);
            while ($month = $monthly_trend->fetch_assoc()) {
                echo "{month: '" . $month['month'] . "', households: " . $month['households_registered'] . ", individuals: " . $month['individuals_registered'] . "},";
            }
            ?>
        ];

        const householdSizeData = [
            <?php
            $household_size_stats->data_seek(0);
            while ($size = $household_size_stats->fetch_assoc()) {
                echo "{size: " . $size['household_size'] . ", count: " . $size['count'] . "},";
            }
            ?>
        ];

        const educationData = [
            <?php
            $education_stats->data_seek(0);
            while ($edu = $education_stats->fetch_assoc()) {
                echo "{level: '" . addslashes($edu['education_level']) . "', count: " . $edu['count'] . "},";
            }
            ?>
        ];

        document.addEventListener('DOMContentLoaded', function() {
            
            new Chart(document.getElementById('countyChart'), {
                type: 'bar',
                data: {
                    labels: countyData.map(d => d.county),
                    datasets: [{
                        label: 'Households',
                        data: countyData.map(d => d.households),
                        backgroundColor: '#667eea',
                        borderColor: '#5a6fd8',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Households'
                            }
                        }
                    }
                }
            });

            new Chart(document.getElementById('ageChart'), {
                type: 'doughnut',
                data: {
                    labels: ageData.map(d => d.ageGroup),
                    datasets: [{
                        data: ageData.map(d => d.count),
                        backgroundColor: [
                            '#667eea', '#764ba2', '#f093fb', '#ffd89b', '#19547b'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            });

            new Chart(document.getElementById('genderChart'), {
                type: 'pie',
                data: {
                    labels: genderData.map(d => d.gender),
                    datasets: [{
                        data: genderData.map(d => d.count),
                        backgroundColor: ['#667eea', '#764ba2']
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            });

            
            new Chart(document.getElementById('trendChart'), {
                type: 'line',
                data: {
                    labels: monthlyData.map(d => d.month),
                    datasets: [
                        {
                            label: 'Households',
                            data: monthlyData.map(d => d.households),
                            borderColor: '#667eea',
                            backgroundColor: 'rgba(102, 126, 234, 0.1)',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Individuals',
                            data: monthlyData.map(d => d.individuals),
                            borderColor: '#764ba2',
                            backgroundColor: 'rgba(118, 75, 162, 0.1)',
                            tension: 0.4,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // Household Size Chart
            new Chart(document.getElementById('householdSizeChart'), {
                type: 'bar',
                data: {
                    labels: householdSizeData.map(d => d.size + ' persons'),
                    datasets: [{
                        label: 'Number of Households',
                        data: householdSizeData.map(d => d.count),
                        backgroundColor: '#667eea'
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

    
            new Chart(document.getElementById('educationChart'), {
                type: 'polarArea',
                data: {
                    labels: educationData.map(d => d.level),
                    datasets: [{
                        data: educationData.map(d => d.count),
                        backgroundColor: [
                            '#667eea', '#764ba2', '#f093fb', '#ffd89b', '#19547b',
                            '#93a5cf', '#e4efe9', '#667eea', '#764ba2', '#f093fb'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>
<?php 

if (isset($conn)) {
    $conn->close();
}
?>