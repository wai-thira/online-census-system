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


try {
    if (isset($_SESSION['admin_id'])) {
        $stmt = $conn->prepare("SELECT id, full_name FROM admins WHERE id = ? AND is_active = TRUE");
        $stmt->bind_param("i", $_SESSION['admin_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows !== 1) {
            
            session_destroy();
            header("Location: admin-login.php");
            exit();
        }
    }
} catch (Exception $e) {
    
    error_log("Admin verification error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - NPR Kenya</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="notifications.css">
    <style>
        .action-icons {
            display: flex;
            gap: 8px;
            justify-content: center;
        }
        .icon-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            padding: 5px;
            border-radius: 4px;
            transition: background-color 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .icon-btn:hover {
            background-color: #f0f0f0;
        }
        .view-icon { color: #2196F3; }
        .edit-icon { color: #FF9800; }
        .delete-icon { color: #f44336; }
        .export-icon { color: #4CAF50; }
    </style>
</head>
<body>
    <div class="admin-container">
        
        <header class="admin-header">
            <h1>📊 NPR Admin Dashboard</h1>
            <div class="admin-info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Administrator'); ?></span>
                
                
                <div class="notifications-panel" id="notificationsPanel">
                    <div class="notification-bell">
                        🔔 Notifications
                        <span class="notification-count" id="notificationCount">0</span>
                    </div>
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-list" id="notificationList">
                            <div class="loading-text">Loading notifications...</div>
                        </div>
                        <div class="notification-actions">
                            <button onclick="markAllNotificationsRead()" class="btn-mark-all">Mark all as read</button>
                        </div>
                    </div>
                </div>
                
                <a href="logout.php" class="btn-logout">Logout</a>
            </div>
        </header>


        <section class="stats-grid">
            <div class="stat-card">
                <h3>Total Households</h3>
                <p class="stat-number">
                    <?php
                    try {
                        $result = $conn->query("SELECT COUNT(*) as total FROM households");
                        if ($result && $result->num_rows > 0) {
                            echo $result->fetch_assoc()['total'];
                        } else {
                            echo "0";
                        }
                    } catch (Exception $e) {
                        echo "Error";
                    }
                    ?>
                </p>
            </div>
            
            <div class="stat-card">
                <h3>Today's Registrations</h3>
                <p class="stat-number">
                    <?php
                    try {
                        $result = $conn->query("SELECT COUNT(*) as today FROM households 
                                              WHERE DATE(registration_date) = CURDATE()");
                        if ($result && $result->num_rows > 0) {
                            echo $result->fetch_assoc()['today'];
                        } else {
                            echo "0";
                        }
                    } catch (Exception $e) {
                        echo "Error";
                    }
                    ?>
                </p>
            </div>
            
            <div class="stat-card">
                <h3>Total Individuals</h3>
                <p class="stat-number">
                    <?php
                    try {
                        $result = $conn->query("SELECT COUNT(*) as total FROM family_members");
                        if ($result && $result->num_rows > 0) {
                            echo $result->fetch_assoc()['total'];
                        } else {
                            echo "0";
                        }
                    } catch (Exception $e) {
                        echo "Error";
                    }
                    ?>
                </p>
            </div>
            
            <div class="stat-card">
                <h3>Counties Covered</h3>
                <p class="stat-number">
                    <?php
                    try {
                        $result = $conn->query("SELECT COUNT(DISTINCT county) as counties FROM households");
                        if ($result && $result->num_rows > 0) {
                            echo $result->fetch_assoc()['counties'];
                        } else {
                            echo "0";
                        }
                    } catch (Exception $e) {
                        echo "Error";
                    }
                    ?>
                </p>
            </div>
        </section>
        
<section class="data-section">
    <h2>⚙️ System Settings</h2>
    <div class="export-actions">
        <a href="admin-census-settings.php" class="btn-primary">📅 Manage Census Period</a>
        <a href="reports-dashboard.php" class="btn-secondary">📊 Advanced Reports</a>
    </div>
</section>

        
        <section class="data-section">
            <h2>📋 Household Data Management</h2>
            
        
            <div class="filters">
                <input type="text" id="searchInput" placeholder="Search by name or ID...">
                <select id="countyFilter">
                    <option value="">All Counties</option>
                    <?php
                    try {
                        $counties = $conn->query("SELECT DISTINCT county FROM households ORDER BY county");
                        if ($counties && $counties->num_rows > 0) {
                            while ($row = $counties->fetch_assoc()) {
                                echo "<option value='" . htmlspecialchars($row['county']) . "'>" . htmlspecialchars($row['county']) . "</option>";
                            }
                        }
                    } catch (Exception $e) {
                        echo "<!-- Error loading counties -->";
                    }
                    ?>
                </select>
                <button onclick="filterData()" class="btn-primary">Apply Filters</button>
                <button onclick="clearFilters()" class="btn-secondary">Clear</button>
            </div>

            
            <div class="table-container">
                <table id="censusTable">
                    <thead>
                        <tr>
                            <th>Family Code</th>
                            <th>Head Name</th>
                            <th>Head ID</th>
                            <th>County</th>
                            <th>Sub-County</th>
                            <th>Total Members</th>
                            <th>Registration Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $result = $conn->query("
                                SELECT h.household_id, h.family_identifier, h.household_head_id, h.county, h.sub_county, 
                                       h.total_members, h.registration_date, hh.full_name as head_name
                                FROM households h
                                LEFT JOIN household_heads hh ON h.household_head_id = hh.id_number
                                ORDER BY h.registration_date DESC 
                                LIMIT 50
                            ");
                            
                            if ($result && $result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    echo "<tr>
                                        <td>" . htmlspecialchars($row['family_identifier']) . "</td>
                                        <td>" . htmlspecialchars($row['head_name'] ?? 'N/A') . "</td>
                                        <td>" . htmlspecialchars($row['household_head_id']) . "</td>
                                        <td>" . htmlspecialchars($row['county']) . "</td>
                                        <td>" . htmlspecialchars($row['sub_county']) . "</td>
                                        <td>" . htmlspecialchars($row['total_members'] ?? '0') . "</td>
                                        <td>" . htmlspecialchars($row['registration_date']) . "</td>
                                        <td>
                                            <div class='action-icons'>
                                                <a href='view-household.php?id=" . $row['household_id'] . "' class='icon-btn view-icon' title='View Details'>👁️</a>
                                                <a href='edit-household.php?id=" . $row['household_id'] . "' class='icon-btn edit-icon' title='Edit'>✏️</a>
                                                <a href='export-single-household.php?id=" . $row['household_id'] . "' class='icon-btn export-icon' title='Export'>📥</a>
                                                <button onclick='confirmDelete(" . $row['household_id'] . ", \"household\")' class='icon-btn delete-icon' title='Delete'>🗑️</button>
                                            </div>
                                        </td>
                                    </tr>";
                                }
                            } else {
                                echo "<tr><td colspan='8' style='text-align: center;'>No households found</td></tr>";
                            }
                        } catch (Exception $e) {
                            echo "<tr><td colspan='8' style='text-align: center; color: red;'>Error loading data: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </section>


        <section class="data-section">
            <h2>👨‍👩‍👧‍👦 Family Members Data</h2>
            
            <div class="table-container">
                <table id="membersTable">
                    <thead>
                        <tr>
                            <th>Family Code</th>
                            <th>Full Name</th>
                            <th>ID Number</th>
                            <th>Date of Birth</th>
                            <th>Gender</th>
                            <th>Relationship</th>
                            <th>Age</th>
                            <th>Registration Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $result = $conn->query("
                                SELECT fm.member_id, fm.family_identifier, fm.full_name, fm.id_number, fm.date_of_birth, 
                                       fm.gender, fm.relationship_to_head, fm.age_at_registration, fm.registration_date
                                FROM family_members fm
                                ORDER BY fm.registration_date DESC 
                                LIMIT 50
                            ");
                            
                            if ($result && $result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    echo "<tr>
                                        <td>" . htmlspecialchars($row['family_identifier']) . "</td>
                                        <td>" . htmlspecialchars($row['full_name']) . "</td>
                                        <td>" . htmlspecialchars($row['id_number']) . "</td>
                                        <td>" . htmlspecialchars($row['date_of_birth']) . "</td>
                                        <td>" . htmlspecialchars($row['gender']) . "</td>
                                        <td>" . htmlspecialchars($row['relationship_to_head']) . "</td>
                                        <td>" . htmlspecialchars($row['age_at_registration']) . "</td>
                                        <td>" . htmlspecialchars($row['registration_date']) . "</td>
                                        <td>
                                            <div class='action-icons'>
                                                <a href='view-member.php?id=" . $row['member_id'] . "' class='icon-btn view-icon' title='View Details'>👁️</a>
                                                <a href='edit-member.php?id=" . $row['member_id'] . "' class='icon-btn edit-icon' title='Edit'>✏️</a>
                                                <button onclick='confirmDelete(" . $row['member_id'] . ", \"member\")' class='icon-btn delete-icon' title='Delete'>🗑️</button>
                                            </div>
                                        </td>
                                    </tr>";
                                }
                            } else {
                                echo "<tr><td colspan='9' style='text-align: center;'>No family members found</td></tr>";
                            }
                        } catch (Exception $e) {
                            echo "<tr><td colspan='9' style='text-align: center; color: red;'>Error loading data: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </section>

        
        <section class="reports-section">
            <h2>📈 Reports & Analytics</h2>
            <div class="reports-grid">
                <div class="report-card">
                    <h4>Households by County</h4>
                    <div class="chart-placeholder">
                        <?php
                        try {
                            $county_data = $conn->query("
                                SELECT county, COUNT(*) as count 
                                FROM households 
                                GROUP BY county 
                                ORDER BY count DESC
                                LIMIT 10
                            ");
                            if ($county_data && $county_data->num_rows > 0) {
                                while ($row = $county_data->fetch_assoc()) {
                                    echo "<p>" . htmlspecialchars($row['county']) . ": " . $row['count'] . " households</p>";
                                }
                            } else {
                                echo "<p>No data available</p>";
                            }
                        } catch (Exception $e) {
                            echo "<p>Error loading data</p>";
                        }
                        ?>
                    </div>
                </div>
                
                <div class="report-card">
                    <h4>Gender Distribution</h4>
                    <div class="chart-placeholder">
                        <?php
                        try {
                            $gender_data = $conn->query("
                                SELECT gender, COUNT(*) as count 
                                FROM family_members 
                                GROUP BY gender
                            ");
                            if ($gender_data && $gender_data->num_rows > 0) {
                                while ($row = $gender_data->fetch_assoc()) {
                                    echo "<p>" . htmlspecialchars($row['gender']) . ": " . $row['count'] . " individuals</p>";
                                }
                            } else {
                                echo "<p>No data available</p>";
                            }
                        } catch (Exception $e) {
                            echo "<p>Error loading data</p>";
                        }
                        ?>
                    </div>
                </div>
                
                <div class="report-card">
                    <h4>Age Distribution</h4>
                    <div class="chart-placeholder">
                        <?php
                        try {
                            $age_data = $conn->query("
                                SELECT 
                                    CASE 
                                        WHEN age_at_registration < 18 THEN 'Minors (<18)'
                                        WHEN age_at_registration BETWEEN 18 AND 35 THEN 'Youth (18-35)'
                                        WHEN age_at_registration BETWEEN 36 AND 59 THEN 'Adults (36-59)'
                                        ELSE 'Seniors (60+)'
                                    END as age_group,
                                    COUNT(*) as count
                                FROM family_members 
                                GROUP BY age_group
                                ORDER BY count DESC
                            ");
                            if ($age_data && $age_data->num_rows > 0) {
                                while ($row = $age_data->fetch_assoc()) {
                                    echo "<p>" . htmlspecialchars($row['age_group']) . ": " . $row['count'] . "</p>";
                                }
                            } else {
                                echo "<p>No data available</p>";
                            }
                        } catch (Exception $e) {
                            echo "<p>Error loading data</p>";
                        }
                        ?>
                    </div>
                </div>
                
                <div class="report-card">
                    <h4>Relationship Distribution</h4>
                    <div class="chart-placeholder">
                        <?php
                        try {
                            $relationship_data = $conn->query("
                                SELECT relationship_to_head, COUNT(*) as count 
                                FROM family_members 
                                GROUP BY relationship_to_head
                                ORDER BY count DESC
                            ");
                            if ($relationship_data && $relationship_data->num_rows > 0) {
                                while ($row = $relationship_data->fetch_assoc()) {
                                    echo "<p>" . htmlspecialchars($row['relationship_to_head']) . ": " . $row['count'] . "</p>";
                                }
                            } else {
                                echo "<p>No data available</p>";
                            }
                        } catch (Exception $e) {
                            echo "<p>Error loading data</p>";
                        }
                        ?>
                    </div>
                </div>
            </div>
            
        
            <div class="export-actions">
                <a href="reports-dashboard.php" class="btn-primary">📊 Advanced Reports</a>
            </div>
        </section>
    </div>

    
    <div class="helpdesk-widget">
        <button class="helpdesk-toggle">💬 Help</button>
        <div class="helpdesk-panel">
            <div class="helpdesk-header">
                <h3>Support Center</h3>
                <button class="close-helpdesk">×</button>
            </div>
            <div class="helpdesk-content">
                <div class="tab-buttons">
                    <button class="tab-btn active" data-tab="faqs">FAQs</button>
                    <button class="tab-btn" data-tab="contact">Contact</button>
                    <button class="tab-btn" data-tab="chat">Live Chat</button>
                </div>
                
                <div class="tab-content">
                    <div class="tab-pane active" id="faqs-tab">
                        <div class="faq-categories">
                            <button class="category-btn active" data-category="all">All</button>
                            <button class="category-btn" data-category="account">Account</button>
                            <button class="category-btn" data-category="registration">Registration</button>
                            <button class="category-btn" data-category="data">Data</button>
                        </div>
                        <div class="faqs-list" id="faqs-list">
                            <div class="loading-text">Loading FAQs...</div>
                        </div>
                    </div>
                    
                    <div class="tab-pane" id="contact-tab">
                        <form id="support-form">
                            <input type="text" name="subject" placeholder="Subject" required>
                            <textarea name="message" placeholder="Describe your issue..." required></textarea>
                            <select name="priority">
                                <option value="low">Low Priority</option>
                                <option value="medium" selected>Medium Priority</option>
                                <option value="high">High Priority</option>
                            </select>
                            <button type="submit">Submit Ticket</button>
                        </form>
                        <div id="form-message"></div>
                    </div>
                    
                    <div class="tab-pane" id="chat-tab">
                        <div class="chat-messages" id="chat-messages">
                            <div class="system-message">Live chat support will be available during business hours (8 AM - 5 PM).</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        
        document.addEventListener('DOMContentLoaded', function() {
            loadNotifications(0);
            loadFAQs('all');
            
            
            initHelpdesk();
        });

    
        function confirmDelete(id, type) {
            const message = type === 'household' 
                ? 'Are you sure you want to delete this household? This will delete all family members associated with it. This action cannot be undone.'
                : 'Are you sure you want to delete this family member? This action cannot be undone.';
            
            if (confirm(message)) {
                deleteRecord(id, type);
            }
        }

        async function deleteRecord(id, type) {
            try {
                const endpoint = type === 'household' ? 'delete-household.php' : 'delete-member.php';
                const idField = type === 'household' ? 'household_id' : 'member_id';
                
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        [idField]: id
                    })
                });
                
                
                if (!response.ok) {
                    throw new Error(`Server returned ${response.status}: ${response.statusText}`);
                }
                
                
                const responseText = await response.text();
                console.log('Raw response:', responseText);
                
                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('JSON parse error:', parseError);
                    throw new Error('Invalid response from server');
                }
                
                console.log('Parsed result:', result);
                
                if (result.success) {
                    const successMessage = type === 'household' 
                        ? 'Household deleted successfully' 
                        : 'Family member deleted successfully';
                    alert(successMessage);
                    location.reload();
                } else {
                    throw new Error(result.error || 'Unknown error occurred');
                }
            } catch (error) {
                console.error('Error deleting record:', error);
                alert('Error: ' + error.message);
            }
        }

        async function loadNotifications(userId) {
            try {
                const response = await fetch(`get-notifications.php?user_id=${userId}&limit=10`);
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                const result = await response.json();
                
                if (result.success) {
                    document.getElementById('notificationCount').textContent = result.unread_count;
                    
                    const notificationList = document.getElementById('notificationList');
                    if (result.data.length === 0) {
                        notificationList.innerHTML = '<div class="no-notifications">No notifications</div>';
                    } else {
                        notificationList.innerHTML = result.data.map(notif => `
                            <div class="notification-item ${notif.is_read ? '' : 'new'}" data-id="${notif.id}">
                                <strong>${escapeHtml(notif.title)}</strong>
                                <p>${escapeHtml(notif.message)}</p>
                                <small>${escapeHtml(notif.time_ago)}</small>
                            </div>
                        `).join('');
                    }
                } else {
                    throw new Error(result.error || 'Failed to load notifications');
                }
            } catch (error) {
                console.error('Error loading notifications:', error);
                document.getElementById('notificationList').innerHTML = '<div class="error-text">Failed to load notifications</div>';
            }
        }

        async function markAllNotificationsRead() {
            try {
                const response = await fetch('mark-notification-read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        user_id: 0,
                        notification_id: 'all'
                    })
                });
                
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                
                const result = await response.json();
                if (result.success) {
                    loadNotifications(0); 
                } else {
                    throw new Error(result.error || 'Failed to mark notifications as read');
                }
            } catch (error) {
                console.error('Error marking notifications as read:', error);
                alert('Failed to mark notifications as read');
            }
        }

        function filterData() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const county = document.getElementById('countyFilter').value;
            const rows = document.querySelectorAll('#censusTable tbody tr');
            
            rows.forEach(row => {
                const familyCode = row.cells[0].textContent.toLowerCase();
                const headName = row.cells[1].textContent.toLowerCase();
                const headId = row.cells[2].textContent;
                const rowCounty = row.cells[3].textContent;
                
                const matchesSearch = familyCode.includes(search) || headName.includes(search) || headId.includes(search);
                const matchesCounty = !county || rowCounty === county;
                
                row.style.display = (matchesSearch && matchesCounty) ? '' : 'none';
            });
        }

        function clearFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('countyFilter').value = '';
            filterData();
        }


        function initHelpdesk() {

            document.querySelector('.helpdesk-toggle').addEventListener('click', () => {
                document.querySelector('.helpdesk-panel').classList.toggle('active');
            });
            
        
            document.querySelector('.close-helpdesk').addEventListener('click', () => {
                document.querySelector('.helpdesk-panel').classList.remove('active');
            });
            
            
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const tab = e.target.dataset.tab;
                    switchTab(tab);
                });
            });
            
    
            document.querySelectorAll('.category-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const category = e.target.dataset.category;
                    filterFAQs(category);
                });
            });
            
            
            document.getElementById('support-form').addEventListener('submit', (e) => {
                e.preventDefault();
                submitSupportTicket();
            });
        }

        function switchTab(tabName) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
            
            document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
            document.querySelector(`#${tabName}-tab`).classList.add('active');
        }

        async function filterFAQs(category) {
            document.querySelectorAll('.category-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelector(`[data-category="${category}"]`).classList.add('active');
            
            await loadFAQs(category);
        }

        async function loadFAQs(category = 'all') {
            const faqsList = document.getElementById('faqs-list');
            faqsList.innerHTML = '<div class="loading-text">Loading FAQs...</div>';
            
            try {
                const url = category === 'all' ? 'get-faqs.php' : `get-faqs.php?category=${category}`;
                const response = await fetch(url);
                
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                
                const result = await response.json();
                
                if (result.success) {
                    if (result.data.length === 0) {
                        faqsList.innerHTML = '<div class="no-faqs">No FAQs found for this category.</div>';
                    } else {
                        faqsList.innerHTML = result.data.map(faq => `
                            <div class="faq-item">
                                <div class="faq-question">${escapeHtml(faq.question)}</div>
                                <div class="faq-answer">${escapeHtml(faq.answer)}</div>
                            </div>
                        `).join('');
                    }
                } else {
                    throw new Error(result.error || 'Failed to load FAQs');
                }
            } catch (error) {
                console.error('Error loading FAQs:', error);
                faqsList.innerHTML = `<div class="error-text">Failed to load FAQs. Please try again.</div>`;
            }
        }

        async function submitSupportTicket() {
            const form = document.getElementById('support-form');
            const formData = new FormData(form);
            const messageDiv = document.getElementById('form-message');
            
            const ticketData = {
                user_id: 1, 
                subject: formData.get('subject'),
                message: formData.get('message'),
                priority: formData.get('priority')
            };
            
            messageDiv.innerHTML = '<div class="loading-text">Submitting ticket...</div>';
            messageDiv.className = 'form-message';
            
            try {
                const response = await fetch('submit-ticket.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(ticketData)
                });
                
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                
                const result = await response.json();
                
                if (result.success) {
                    messageDiv.innerHTML = `<div class="success-message">
                        ✅ ${escapeHtml(result.message)}<br>
                        <small>Reference: ${escapeHtml(result.reference)}</small>
                    </div>`;
                    messageDiv.className = 'form-message success';
                    form.reset();
                } else {
                    throw new Error(result.error || 'Failed to submit ticket');
                }
            } catch (error) {
                console.error('Error submitting ticket:', error);
                messageDiv.innerHTML = `<div class="error-message">❌ ${escapeHtml(error.message)}</div>`;
                messageDiv.className = 'form-message error';
            }
        }

        
        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    </script>
</body>
</html>
<?php 

if (isset($conn)) {
    $conn->close();
}
?>