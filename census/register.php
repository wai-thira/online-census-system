<?php

session_start();
require 'db_connection.php';

if($_SERVER["REQUEST_METHOD"]== "POST"){

    $fullname= $_POST['fullname'];
    $email= $_POST['email'];
    $plain_password= $_POST['password'];
  

    $password_hash= password_hash($plain_password, PASSWORD_DEFAULT);
    $sql= "INSERT INTO users1 (fullname, email, password_hash) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $fullname, $email, $password_hash);
      

    if($stmt->execute()){
        

echo "<script>
                    alert('USER REGISTERED SUCCESSFULLY');
                    window.location.href = 'login.html';
                </script>";


    }
    else{
        if($conn->errno== 1062){

echo "<script>
                    alert('EMAIL ALREADY REGISTERED');
                    window.location.href = 'login.html';
                </script>";

        }
        else{
 echo "ERROR:" . $sql. "<br>" . $conn->error;
        }
    }
    $stmt->close();
}
$conn->close();

?>

