<?php
session_start();
require 'db_connect.php';

$error = "";
$success = "";
if (isset($_GET['registered'])) {
    $success = "Account created successfully. Please login.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Fetch user
    $stmt = $conn->prepare("SELECT user_id, password FROM user WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();

        if (password_verify($password, $row['password'])) {
            // Password is correct
            session_regenerate_id(true);
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['username'] = $username;

            // Admin check
            if ($username === 'admin') {
                header("Location: admin/admin_panel.php"); // Admin goes to admin panel
            } else {
                header("Location: index.php"); // Regular users go to dashboard
            }
            exit();
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "User not found.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login | BottleBank</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
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
.success{color:#188038;text-align:center;margin-bottom:8px}
</style>
</head>
<body>
<div class="box">
<h2>BottleBank Login</h2>

<?php if($error): ?><div class="error"><?= $error ?></div><?php endif; ?>
<?php if($success): ?><div class="success"><?= $success ?></div><?php endif; ?>

<form method="post">
<input type="text" name="username" placeholder="Username" required>
<input type="password" name="password" placeholder="Password" required>
<button type="submit">Login</button>
</form>

<div class="link">
Don’t have an account? <a href="register.php">Sign up</a>
</div>
</div>
</body>
</html>
