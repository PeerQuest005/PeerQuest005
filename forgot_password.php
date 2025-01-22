<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/water.css@2/out/water.css">
</head>
<body>

    <h1>Forgot Password</h1>

    <form method="post" action="send_password_reset.php">
        <label for="email">Email</label>
        <input type="email" name="email" id="email" required>
        <button>Send</button>
    </form>

    <a a href="login.php"><button>Back to Login</button></a>
    



</body>
</html>
