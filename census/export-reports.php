<?php
session_start();
require 'db_connection.php';


if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin-login.php");
    exit();
}


$type = $_GET['type'] ?? 'full';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$county_filter = $_GET['county'] ?? '';

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
    die("Error loading report data: " . $e->getMessage());
}


$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Census Data Analysis Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #667eea;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #667eea;
            margin: 0;
            font-size: 24px;
        }
        .header h2 {
            color: #666;
            margin: 5px 0;
            font-size: 16px;
            font-weight: normal;
        }
        .section {
            margin-bottom: 25px;
            page-break-inside: avoid;
        }
        .section-title {
            background: #667eea;
            color: white;
            padding: 8px 15px;
            margin: 15px 0;
            font-size: 16px;
            font-weight: bold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: 12px;
        }
        th {
            background: #f8f9fa;
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            font-weight: bold;
        }
        td {
            border: 1px solid #ddd;
            padding: 8px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin: 15px 0;
        }
        .stat-card {
            border: 1px solid #ddd;
            padding: 15px;
            text-align: center;
            background: #f9f9f9;
        }
        .stat-number {
            font-size: 20px;
            font-weight: bold;
            color: #667eea;
            margin: 5px 0;
        }
        .stat-label {
            font-size: 12px;
            color: #666;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 11px;
            color: #666;
        }
        .page-break {
            page-break-before: always;
        }
        .progress-bar {
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            height: 6px;
            margin: 3px 0;
        }
        .progress-fill {
            height: 100%;
            background: #667eea;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>NATIONAL POPULATION REGISTER - KENYA</h1>
        <h2>CENSUS DATA ANALYSIS REPORT</h2>
        <p><strong>Report Period:</strong> ' . date('F j, Y', strtotime($start_date)) . ' to ' . date('F j, Y', strtotime($end_date)) . '</p>
        ' . (!empty($county_filter) ? '<p><strong>County Filter:</strong> ' . $county_filter . '</p>' : '') . '
        <p><strong>Generated on:</strong> ' . date('F j, Y g:i A') . '</p>
    </div>

    <div class="section">
        <div class="section-title">1. Executive Summary</div>
        <p>This report provides a comprehensive analysis of census data collected through the National Population Register. 
        The data covers ' . number_format($total_households) . ' households and ' . number_format($total_individuals) . ' individuals registered in the system. 
        During the selected period, ' . number_format($date_range_stats['households_in_range']) . ' households were registered, with an average household size of ' . number_format($date_range_stats['avg_household_size'], 1) . ' persons.</p>
    </div>

    <div class="section">
        <div class="section-title">2. Key Statistics</div>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number">' . number_format($total_households) . '</div>
                <div class="stat-label">Total Households</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">' . number_format($total_individuals) . '</div>
                <div class="stat-label">Total Individuals</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">' . number_format($date_range_stats['households_in_range']) . '</div>
                <div class="stat-label">Households in Period</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">' . number_format($date_range_stats['avg_household_size'], 1) . '</div>
                <div class="stat-label">Avg Household Size</div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">3. County Distribution</div>
        <table>
            <thead>
                <tr>
                    <th>County</th>
                    <th>Households</th>
                    <th>Individuals</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>';
            
            $county_stats->data_seek(0);
            while ($county = $county_stats->fetch_assoc()) {
                $percentage = ($county['household_count'] / $total_households) * 100;
                $html .= '
                <tr>
                    <td>' . htmlspecialchars($county['county']) . '</td>
                    <td style="text-align: right;">' . number_format($county['household_count']) . '</td>
                    <td style="text-align: right;">' . number_format($county['total_individuals']) . '</td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="min-width: 40px;">' . number_format($percentage, 1) . '%</span>
                            <div class="progress-bar" style="flex: 1;">
                                <div class="progress-fill" style="width: ' . $percentage . '%;"></div>
                            </div>
                        </div>
                    </td>
                </tr>';
            }
            
$html .= '
            </tbody>
        </table>
    </div>

    <div class="page-break"></div>

    <div class="section">
        <div class="section-title">4. Age Distribution</div>
        <table>
            <thead>
                <tr>
                    <th>Age Group</th>
                    <th>Count</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>';
            
            $age_distribution->data_seek(0);
            while ($age = $age_distribution->fetch_assoc()) {
                $html .= '
                <tr>
                    <td>' . htmlspecialchars($age['age_group']) . '</td>
                    <td style="text-align: right;">' . number_format($age['count']) . '</td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="min-width: 40px;">' . $age['percentage'] . '%</span>
                            <div class="progress-bar" style="flex: 1;">
                                <div class="progress-fill" style="width: ' . $age['percentage'] . '%;"></div>
                            </div>
                        </div>
                    </td>
                </tr>';
            }
            
$html .= '
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">5. Gender Distribution</div>
        <table>
            <thead>
                <tr>
                    <th>Gender</th>
                    <th>Count</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>';
            
            $gender_stats->data_seek(0);
            while ($gender = $gender_stats->fetch_assoc()) {
                $html .= '
                <tr>
                    <td>' . htmlspecialchars($gender['gender']) . '</td>
                    <td style="text-align: right;">' . number_format($gender['count']) . '</td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="min-width: 40px;">' . $gender['percentage'] . '%</span>
                            <div class="progress-bar" style="flex: 1;">
                                <div class="progress-fill" style="width: ' . $gender['percentage'] . '%;"></div>
                            </div>
                        </div>
                    </td>
                </tr>';
            }
            
$html .= '
            </tbody>
        </table>
    </div>

    <div class="page-break"></div>

    <div class="section">
        <div class="section-title">6. Education Levels (Adults)</div>
        <table>
            <thead>
                <tr>
                    <th>Education Level</th>
                    <th>Count</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>';
            
            $education_stats->data_seek(0);
            while ($education = $education_stats->fetch_assoc()) {
                $html .= '
                <tr>
                    <td>' . htmlspecialchars($education['education_level']) . '</td>
                    <td style="text-align: right;">' . number_format($education['count']) . '</td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="min-width: 40px;">' . $education['percentage'] . '%</span>
                            <div class="progress-bar" style="flex: 1;">
                                <div class="progress-fill" style="width: ' . $education['percentage'] . '%;"></div>
                            </div>
                        </div>
                    </td>
                </tr>';
            }
            
$html .= '
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">7. Top 10 Sub-Counties</div>
        <table>
            <thead>
                <tr>
                    <th>Sub-County</th>
                    <th>County</th>
                    <th>Households</th>
                    <th>Individuals</th>
                </tr>
            </thead>
            <tbody>';
            
            $top_subcounties->data_seek(0);
            while ($subcounty = $top_subcounties->fetch_assoc()) {
                $html .= '
                <tr>
                    <td>' . htmlspecialchars($subcounty['sub_county']) . '</td>
                    <td>' . htmlspecialchars($subcounty['county']) . '</td>
                    <td style="text-align: right;">' . number_format($subcounty['household_count']) . '</td>
                    <td style="text-align: right;">' . number_format($subcounty['total_individuals']) . '</td>
                </tr>';
            }
            
$html .= '
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">8. Conclusion</div>
        <p>This comprehensive report demonstrates the effectiveness of the National Population Register in capturing vital demographic data. 
        The analysis provides valuable insights for policy planning, resource allocation, and development initiatives. 
        Regular monitoring of these metrics will help track population trends and inform national development strategies.</p>
    </div>

    <div class="footer">
        <p>National Population Register - Kenya | Confidential Report</p>
        <p>Generated on ' . date('F j, Y \a\t g:i A') . '</p>
    </div>
</body>
</html>';


if (isset($_GET['preview'])) {
    
    echo $html;
    exit();
}

$filename = 'Census_Report_' . date('Y-m-d_H-i-s') . '.html';


header('Content-Type: text/html');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($html));
header('Connection: close');


echo $html;

$conn->close();
exit();
?>