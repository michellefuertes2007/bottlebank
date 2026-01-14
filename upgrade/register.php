<?php
include 'includes/db_connect.php';
session_start();
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Check duplicates
    $check = $conn->prepare("SELECT * FROM user WHERE username = ? OR email = ?");
    $check->bind_param("ss", $username, $email);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        $error = "Username or email already taken.";
    } else {
        $stmt = $conn->prepare("INSERT INTO user (username, email, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $email, $password);
        if ($stmt->execute()) {
            header("Location: login.php?registered=1");
            exit();
        } else {
            $error = "Registration failed. Try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register | BottleBank</title>
<style>
body{display:flex;justify-content:center;align-items:center;height:100vh;font-family:Poppins,sans-serif;background:linear-gradient(135deg,#0077cc,#00c16e);}
.box{background:#fff;padding:35px;width:360px;border-radius:14px;box-shadow:0 10px 25px rgba(0,0,0,.2);}
h2{text-align:center;color:#0077cc;margin-bottom:10px}
input,button{width:100%;padding:10px;margin-top:10px;border-radius:8px;border:1px solid #ccc;}
button{background:#0077cc;color:#fff;font-weight:600;border:none;cursor:pointer;}
button:hover{background:#005fa3;}
.link{text-align:center;margin-top:14px;font-size:14px;}
.link a{text-decoration:none;color:#0077cc;font-weight:600;}
.link a:hover{text-decoration:underline;}
.error{color:#d93025;text-align:center;margin-bottom:8px}
</style>
</head>
<body>
<div class="box">
<h2>Create Account</h2>
<?php if($error): ?><div class="error"><?= $error ?></div><?php endif; ?>
<form method="post">
<input type="text" name="username" placeholder="Username" required>
<input type="email" name="email" placeholder="Email" required>
<input type="password" name="password" placeholder="Password" required>
<button type="submit">Register</button>
</form>
<div class="link">
Already have an account? <a href="login.php">Login</a>
</div>
</div>
</body>
</html>
