<?php
session_start();
require 'db_connection.php';
require 'census-period-check.php';

error_log("Census registration attempt - Checking if active...");


if (!isCensusPeriodActive($conn)) {
    $message = getCensusMessage($conn);
    error_log("Census registration blocked - Period not active");
    

    header('Content-Type: text/html; charset=utf-8');
    
    die("
    <!DOCTYPE html>
    <html>
    <head>
        <title>Census Registration Closed</title>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                margin: 0; 
                padding: 20px; 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .message-container { 
                max-width: 600px; 
                background: white; 
                padding: 40px; 
                border-radius: 15px; 
                text-align: center; 
                box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            }
            .warning { 
                background: #fff3cd; 
                border: 2px solid #ffeaa7; 
                color: #856404; 
                padding: 30px; 
                border-radius: 10px; 
                margin-bottom: 20px; 
            }
            .btn { 
                background: #006400; 
                color: white; 
                padding: 12px 25px; 
                text-decoration: none; 
                border-radius: 6px; 
                display: inline-block; 
                margin: 10px;
                font-weight: bold;
                transition: all 0.3s ease;
            }
            .btn:hover {
                background: #008000;
                transform: translateY(-2px);
            }
            h2 { color: #856404; margin-bottom: 20px; }
        </style>
    </head>
    <body>
        <div class='message-container'>
            <div class='warning'>
                <h2>📋 Census Registration Closed</h2>
                <p style='font-size: 1.1em; line-height: 1.6;'>{$message}</p>
            </div>
            <a href='landing-page.html' class='btn'>Return to Homepage</a>
        </div>
    </body>
    </html>");
}

error_log("Census registration allowed - Period is active");


error_reporting(E_ALL);
ini_set('display_errors', 1);


function prepareStatement($conn, $sql, $error_message) {
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception("$error_message: " . $conn->error);
    }
    return $stmt;
}


function validateAndFormatName($name) {
   
    $name = trim(preg_replace('/\s+/', ' ', $name));
    

    if (empty($name)) {
        throw new Exception("Name cannot be empty");
    }
    

    if (strlen($name) < 2) {
        throw new Exception("Name must be at least 2 characters long");
    }
    
 
    if (!preg_match('/^[A-Za-z\s\-\']+$/', $name)) {
        throw new Exception("Name can only contain letters, spaces, hyphens, and apostrophes");
    }
    
    
    if (strpos($name, ' ') === false) {
       
        error_log("Warning: Single name provided: " . $name);
        
    }
    
   
    $formattedName = implode(' ', array_map(function($part) {
        return ucwords(strtolower($part));
    }, explode(' ', $name)));
    
    return $formattedName;
}


function calculateAge($date_of_birth) {
    $dob = new DateTime($date_of_birth);
    $today = new DateTime();
    return $today->diff($dob)->y;
}

function canRegisterIndependently($id_number, $conn) {
    if (empty($id_number) || strpos($id_number, 'MINOR_') === 0) {
        return true; 
    }
    
    $sql = "SELECT can_register_independently FROM family_members WHERE id_number = ? ORDER BY registration_date DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return true; 
    }
    
    $stmt->bind_param("s", $id_number);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['can_register_independently'] == 1;
    }
    
    return true; 
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    error_log("POST Data: " . print_r($_POST, true));
    
    if (!isCensusPeriodActive($conn)) {
        $message = getCensusMessage($conn);
        die("
        <!DOCTYPE html>
        <html>
        <head>
            <title>Census Registration Closed</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
                .message-container { max-width: 600px; margin: 50px auto; background: white; padding: 30px; border-radius: 8px; text-align: center; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
                .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
                .btn { background: #006400; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; margin: 10px; }
            </style>
        </head>
        <body>
            <div class='message-container'>
                <div class='warning'>
                    <h2>📋 Census Registration Closed</h2>
                    <p>{$message}</p>
                </div>
                <a href='landing-page.html' class='btn'>Return to Homepage</a>
            </div>
        </body>
        </html>");
    }


    $household_head_id = trim($_POST['household_head_id']);
    $family_identifier = trim($_POST['family_identifier']);
    $county = $_POST['county'];
    $sub_county = $_POST['sub_county'];
    $ward = $_POST['ward'];
    $phone_number = trim($_POST['phone_number']);



    $registration_type = isset($_POST['assisted_registration']) && $_POST['assisted_registration'] ? 'assisted' : 'self';
$helper_phone = trim($_POST['helper_phone'] ?? '');
$helper_location = trim($_POST['helper_location'] ?? '');

    $full_name = trim($_POST['full_name']);
    $id_number = trim($_POST['id_number']);
    $date_of_birth = $_POST['date_of_birth'];
    $gender = $_POST['gender'];
    $marital_status = $_POST['marital_status'] ?? 'Single';
    $spouse_name = trim($_POST['spouse_name'] ?? '');
    $spouse_id = trim($_POST['spouse_id'] ?? '');
    $education_level = $_POST['education_level'] ?? null;
    $occupation = trim($_POST['occupation'] ?? '');
    $monthly_income = $_POST['monthly_income'] ?? null;

    $user_id = $_SESSION['user_id'] ?? 1;

    try {

        $full_name = validateAndFormatName($full_name);
        
        $head_age = calculateAge($date_of_birth);
        if ($head_age < 18) {
            throw new Exception("Household head must be 18 years or older. Current age: {$head_age} years");
        }

  
        if ($marital_status === 'Married') {
            if (empty($spouse_name)) {
                throw new Exception("Spouse name is required for married individuals");
            }
            
 
            $spouse_name = validateAndFormatName($spouse_name);
            
            
            $spouse_date_of_birth = $_POST['spouse_date_of_birth'] ?? $date_of_birth;
            $spouse_gender = $_POST['spouse_gender'] ?? ($gender === 'Male' ? 'Female' : 'Male');
            $spouse_education_level = $_POST['spouse_education_level'] ?? $education_level;
            $spouse_occupation = trim($_POST['spouse_occupation'] ?? '');
            $spouse_monthly_income = $_POST['spouse_monthly_income'] ?? $monthly_income;
            
         
            $spouse_age = calculateAge($spouse_date_of_birth);
            if ($spouse_age < 18) {
                throw new Exception("Spouse must be 18 years or older. Current age: {$spouse_age} years");
            }
            
           
            if (empty($spouse_id)) {
                $spouse_id = "SPOUSE_" . $id_number;
            }
        } else {
            $spouse_name = '';
            $spouse_id = '';
            $spouse_date_of_birth = null;
            $spouse_gender = '';
            $spouse_education_level = null;
            $spouse_occupation = '';
            $spouse_monthly_income = null;
        }

        if (!empty($phone_number) && !preg_match('/^[0-9]{10}$/', $phone_number)) {
            throw new Exception("Invalid phone number format. Please enter a 10-digit phone number.");
        }
        
        if (!empty($phone_number)) {
            $check_phone_sql = "SELECT household_id FROM households WHERE phone_number = ?";
            $check_phone_stmt = prepareStatement($conn, $check_phone_sql, "Failed to prepare phone check statement");
            $check_phone_stmt->bind_param("s", $phone_number);
            
            if (!$check_phone_stmt->execute()) {
                throw new Exception("Failed to execute phone check: " . $check_phone_stmt->error);
            }
            
            $result = $check_phone_stmt->get_result();
            if ($result->num_rows > 0) {
                throw new Exception("This phone number is already registered. Please use a different phone number.");
            }
            $check_phone_stmt->close();
        }

        $check_sql = "SELECT head_id FROM household_heads WHERE id_number = ?";
        $check_stmt = prepareStatement($conn, $check_sql, "Failed to prepare ID check statement");
        $check_stmt->bind_param("s", $id_number);
        
        if (!$check_stmt->execute()) {
            throw new Exception("Failed to execute ID check: " . $check_stmt->error);
        }
        
        $result = $check_stmt->get_result();
        if ($result->num_rows > 0) {
            throw new Exception("ID number already exists in our records as a household head.");
        }
        $check_stmt->close();


        $check_head_sql = "SELECT household_id FROM households WHERE household_head_id = ?";
        $check_head_stmt = prepareStatement($conn, $check_head_sql, "Failed to prepare head ID check statement");
        $check_head_stmt->bind_param("s", $household_head_id);
        
        if (!$check_head_stmt->execute()) {
            throw new Exception("Failed to execute head ID check: " . $check_head_stmt->error);
        }
        
        $result = $check_head_stmt->get_result();
        if ($result->num_rows > 0) {
            throw new Exception("Household head ID already exists in our records.");
        }
        $check_head_stmt->close();

     
        $conn->autocommit(FALSE);

        $household_sql = "INSERT INTO households (family_identifier, household_head_id, county, sub_county, ward, phone_number, registration_type, helper_phone, helper_location, registration_date) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

                 $household_stmt = prepareStatement($conn, $household_sql, "Failed to prepare household insert");
$household_stmt->bind_param("sssssssss", $family_identifier, $household_head_id, $county, $sub_county, $ward, $phone_number, $registration_type, $helper_phone, $helper_location);
        
        if (!$household_stmt->execute()) {
            throw new Exception("Error inserting household: " . $household_stmt->error);
        }
        
        $household_id = $conn->insert_id;
        $household_stmt->close();

       
        $head_sql = "INSERT INTO household_heads (id_number, full_name, date_of_birth, gender, marital_status, education_level, occupation, monthly_income, household_id, is_adult, spouse_name, spouse_id, spouse_date_of_birth, spouse_gender, spouse_occupation, spouse_education_level, spouse_monthly_income) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?)";
        $head_stmt = prepareStatement($conn, $head_sql, "Failed to prepare household head insert");
        $head_stmt->bind_param("ssssssssisssssss", $id_number, $full_name, $date_of_birth, $gender, $marital_status, $education_level, $occupation, $monthly_income, $household_id, $spouse_name, $spouse_id, $spouse_date_of_birth, $spouse_gender, $spouse_occupation, $spouse_education_level, $spouse_monthly_income);
        
        if (!$head_stmt->execute()) {
            throw new Exception("Error inserting household head: " . $head_stmt->error);
        }
        $head_stmt->close();

        $head_member_sql = "INSERT INTO family_members (family_identifier, full_name, id_number, date_of_birth, gender, relationship_to_head, education_level, occupation, is_minor, marital_status, is_adult, can_register_independently, age_at_registration, spouse_name, spouse_id, registration_date) 
                           VALUES (?, ?, ?, ?, ?, 'Head', ?, ?, 0, ?, 1, 1, ?, ?, ?, NOW())";
        $head_member_stmt = prepareStatement($conn, $head_member_sql, "Failed to prepare head as family member insert");
        $head_member_stmt->bind_param("ssssssssiss", $family_identifier, $full_name, $id_number, $date_of_birth, $gender, $education_level, $occupation, $marital_status, $head_age, $spouse_name, $spouse_id);
        
        if (!$head_member_stmt->execute()) {
            throw new Exception("Error inserting household head as family member: " . $head_member_stmt->error);
        }
        $head_member_stmt->close();

        
        $total_members = 1; 
        $minor_count = 0;
        $adult_count = 1; 
        
        if ($marital_status === 'Married' && !empty($spouse_name) && !empty($spouse_id)) {
            try {
                $spouse_name = validateAndFormatName($spouse_name);
                $spouse_age = calculateAge($spouse_date_of_birth);
                $is_minor_spouse = 0;
                $is_adult_spouse = 1;

                $spouse_sql = "INSERT INTO family_members (family_identifier, full_name, id_number, date_of_birth, gender, relationship_to_head, education_level, occupation, is_minor, is_adult, can_register_independently, age_at_registration, registration_date) 
                              VALUES (?, ?, ?, ?, ?, 'Spouse', ?, ?, ?, ?, ?, ?, NOW())";
                $spouse_stmt = prepareStatement($conn, $spouse_sql, "Failed to prepare spouse insert");
                $spouse_stmt->bind_param("sssssssiiii", $family_identifier, $spouse_name, $spouse_id, $spouse_date_of_birth, $spouse_gender, $spouse_education_level, $spouse_occupation, $is_minor_spouse, $is_adult_spouse, $is_adult_spouse, $spouse_age);

                if (!$spouse_stmt->execute()) {
                    throw new Exception("Error inserting spouse: " . $spouse_stmt->error);
                }
                $spouse_stmt->close();
                $total_members++;
                $adult_count++;
            } catch (Exception $e) {
                
                error_log("Spouse registration warning: " . $e->getMessage());
            }
        }


        if (isset($_POST['members']) && is_array($_POST['members'])) {
            foreach ($_POST['members'] as $index => $member) {
                if (!empty($member['name']) && !empty($member['date_of_birth']) && !empty($member['gender']) && !empty($member['relationship'])) {
                    
                    $member_name = trim($member['name']);
                    $member_dob = $member['date_of_birth'];
                    $member_gender = $member['gender'];
                    $member_relationship = $member['relationship'];
                    $member_education = $member['education_level'] ?? null;
                    $member_id_number = trim($member['id_number'] ?? '');
           
                    $member_name = validateAndFormatName($member_name);
                    
                    $member_age = calculateAge($member_dob);
                    $is_minor_member = ($member_age < 18) ? 1 : 0;
                    $is_adult_member = ($member_age >= 18) ? 1 : 0;
                    $can_register_independently = $is_adult_member ? 1 : 0;
                    
                    
                    $allowed_relationships = ['Son', 'Daughter', 'Adopted Child', 'Foster Child', 'Step Child'];
                    if (!in_array($member_relationship, $allowed_relationships)) {
                        throw new Exception("Relationship '{$member_relationship}' is not allowed for household members. Only children/dependents are allowed.");
                    }
                    
                    
                    if ($is_adult_member && !empty($member_id_number)) {
                        if (!canRegisterIndependently($member_id_number, $conn)) {
                            throw new Exception("Adult family member '{$member_name}' is already registered as a dependent in another household");
                        }
                    }
                    
                    
                    if ($is_minor_member) {
                        $minor_count++;
                    } else {
                        $adult_count++;
                    }
                    
                
                    if (!$is_minor_member && (empty($member_id_number) || strlen($member_id_number) !== 8)) {
                        throw new Exception("Adult family member '{$member_name}' must have a valid 8-digit ID number");
                    }
                    
                    if (!$is_minor_member && !empty($member_id_number)) {
                        $check_member_sql = "SELECT member_id FROM family_members WHERE id_number = ? AND relationship_to_head != 'Head'";
                        $check_member_stmt = prepareStatement($conn, $check_member_sql, "Failed to prepare member ID check");
                        $check_member_stmt->bind_param("s", $member_id_number);
                        
                        if (!$check_member_stmt->execute()) {
                            throw new Exception("Failed to execute member ID check: " . $check_member_stmt->error);
                        }
                        
                        $result = $check_member_stmt->get_result();
                        if ($result->num_rows > 0) {
                            throw new Exception("ID number {$member_id_number} for '{$member_name}' already exists in the system as a family member");
                        }
                        $check_member_stmt->close();
                    }
                    
                    
                    if ($is_minor_member && empty($member_id_number)) {
                        $member_id_number = "MINOR_" . $family_identifier . "_" . ($index + 1);
                    }
                    
               
                    $member_sql = "INSERT INTO family_members (family_identifier, full_name, id_number, date_of_birth, gender, relationship_to_head, education_level, is_minor, is_adult, can_register_independently, age_at_registration, registration_date) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    $member_stmt = prepareStatement($conn, $member_sql, "Failed to prepare family member insert");
                    $member_stmt->bind_param("sssssssiiii", $family_identifier, $member_name, $member_id_number, $member_dob, $member_gender, $member_relationship, $member_education, $is_minor_member, $is_adult_member, $can_register_independently, $member_age);
                    
                    if (!$member_stmt->execute()) {
                        throw new Exception("Error inserting family member '{$member_name}': " . $member_stmt->error);
                    }
                    $member_stmt->close();
                    
                    $total_members++;
                }
            }
        }

        $update_household_sql = "UPDATE households SET total_members = ?, adult_count = ?, minor_count = ? WHERE household_id = ?";
        $update_household_stmt = prepareStatement($conn, $update_household_sql, "Failed to prepare household update");
        $update_household_stmt->bind_param("iiii", $total_members, $adult_count, $minor_count, $household_id);
        
        if (!$update_household_stmt->execute()) {
            throw new Exception("Error updating household counts: " . $update_household_stmt->error);
        }
        $update_household_stmt->close();

        $audit_sql = "INSERT INTO audit_log (family_identifier, action, description, user_ip) 
                     VALUES (?, 'REGISTRATION', ?, ?)";
        $audit_stmt = prepareStatement($conn, $audit_sql, "Failed to prepare audit log insert");
        $description = "Nuclear family registered: {$total_members} members ({$adult_count} adults, {$minor_count} minors)";
        $user_ip = $_SERVER['REMOTE_ADDR'];
        $audit_stmt->bind_param("sss", $family_identifier, $description, $user_ip);
        
        if (!$audit_stmt->execute()) {
            throw new Exception("Error inserting audit log: " . $audit_stmt->error);
        }
        $audit_stmt->close();

        $conn->commit();
        $conn->autocommit(TRUE);

        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Submission Successful</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    margin: 0; 
                    padding: 20px; 
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                }
                .form-container { 
                    max-width: 800px; 
                    margin: 20px auto; 
                    background: white; 
                    padding: 30px; 
                    border-radius: 15px; 
                    box-shadow: 0 10px 30px rgba(0,0,0,0.2); 
                }
                .message { 
                    text-align: center; 
                    padding: 20px; 
                    margin-bottom: 20px; 
                }
                .success { 
                    background-color: #d4edda; 
                    border: 2px solid #c3e6cb; 
                    color: #155724; 
                    border-radius: 10px; 
                }
                .family-summary { 
                    background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
                    padding: 25px; 
                    border-radius: 10px; 
                    margin: 25px 0; 
                    text-align: left; 
                    border-left: 5px solid #2196f3;
                }
                .form-actions { 
                    text-align: center; 
                    margin-top: 30px; 
                }
                .btn { 
                    padding: 14px 28px; 
                    margin: 0 10px; 
                    border: none; 
                    border-radius: 8px; 
                    cursor: pointer; 
                    font-weight: bold; 
                    text-decoration: none; 
                    display: inline-block;
                    transition: all 0.3s ease;
                    font-size: 16px;
                }
                .btn-primary { 
                    background: linear-gradient(135deg, #006400 0%, #228B22 100%);
                    color: white; 
                }
                .btn-primary:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 5px 15px rgba(0,100,0,0.3);
                }
                .btn-secondary { 
                    background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
                    color: white; 
                }
                .btn-secondary:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 5px 15px rgba(108,117,125,0.3);
                }
                .family-code { 
                    font-size: 2em; 
                    font-weight: bold; 
                    color: #006400; 
                    margin: 15px 0;
                    background: #f8f9fa;
                    padding: 10px;
                    border-radius: 8px;
                    display: inline-block;
                }
                .info-box { 
                    background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
                    padding: 20px; 
                    border-radius: 10px; 
                    margin: 20px 0; 
                    text-align: left;
                    border-left: 5px solid #ffc107;
                }
                .member-breakdown {
                    background: #f8f9fa;
                    padding: 15px;
                    border-radius: 8px;
                    margin: 15px 0;
                }
                h3 { color: #155724; margin-bottom: 20px; }
                h4 { color: #0d47a1; margin-bottom: 15px; }
            </style>
        </head>
        <body>
            <div class='form-container'>
                <div class='message success'>
                    <h3>✅ Nuclear Family Census Data Submitted Successfully!</h3>
                    <p style='font-size: 1.1em; margin-bottom: 20px;'>Thank you for participating in the National Population Census.</p>
                    
                    <div class='family-summary'>
                        <h4>🏠 Household Registration Summary</h4>
                        <p><strong>Family Code:</strong> <span class='family-code'>{$family_identifier}</span></p>
                        <div class='member-breakdown'>
                            <p><strong>Household Head:</strong> {$full_name} (ID: {$id_number})</p>
                            <p><strong>Location:</strong> {$county} County, {$sub_county} Sub-County, {$ward} Ward</p>
                            <p><strong>Phone Number:</strong> " . ($phone_number ? $phone_number : 'Not provided') . "</p>
                            <p><strong>Marital Status:</strong> {$marital_status}</p>";
        
        echo "              </div>
                        <div style='background: #e8f5e8; padding: 15px; border-radius: 8px; margin-top: 15px;'>
                            <p><strong>Total Family Members:</strong> <span style='font-size: 1.2em;'>{$total_members}</span></p>
                            <p><strong>Adults (18+ years):</strong> {$adult_count} members</p>
                            <p><strong>Minors (&lt;18 years):</strong> {$minor_count} members</p>
                        </div>
                    </div>
                    
                    <div class='info-box'>
                        <h4>📋 Important Registration Information</h4>
                        <p><strong>Adult Children (18+ years):</strong> Remain listed in this household for census accuracy</p>
                        <p><strong>Next Census Period:</strong> Adult children can register their own households while maintaining this historical record</p>
                        <p><strong>Data Integrity:</strong> This approach ensures accurate demographic analysis of living arrangements</p>
                        <p><strong>Family Code:</strong> Keep this code safe - it's your unique household identifier</p>
                    </div>
                    
                    <p style='font-size: 1.1em; margin-top: 20px;'><strong>🔐 Important:</strong> Your Family Code is your access key for future updates and verification.</p>
                </div>
                
                <div class='form-actions'>
                    <a href='census-form.html' class='btn btn-primary'>Register Another Household</a>
                   <a href='family-login.php?registered=1' class='btn btn-primary'>View Household Details</a>
                    <a href='landing-page.html' class='btn btn-secondary'>Return to Homepage</a>
                </div>
            </div>
        </body>
        </html>";

    } catch (Exception $e) {
        
        $conn->rollback();
        $conn->autocommit(TRUE);
        
        echo "<div style='color: red; padding: 20px; border: 2px solid red; margin: 20px; border-radius: 10px; background: #fee;'>
                <h3 style='color: #c00;'>Registration Error</h3>
                <p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
                <p style='margin-top: 15px; font-size: 0.9em; color: #666;'>Please check your information and try again.</p>
              </div>";
        
        echo "<div style='text-align: center; margin: 20px;'>
                <a href='javascript:history.back()' style='background: #006400; color: white; padding: 12px 25px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;'>
                    ← Go Back & Correct Data
                </a>
              </div>";
    }
} else {

    header("Location: census-form.html");
    exit();
}

$conn->close();
?>