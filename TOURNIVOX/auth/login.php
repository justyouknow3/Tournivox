<?php
session_start();

require_once "database.php";

$error = "";
$message = "";

if (isset($_GET["registered"]) && $_GET["registered"] == "1") {

    $message = "Registration successful. You can now log in.";

}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($username === "" || $password === "") {

        $error = "Please enter your username and password.";

    } else {

        $loginUser = $conn->prepare(
            "SELECT
                user_id,
                first_name,
                last_name,
                username,
                email,
                password,
                role,
                status
             FROM users
             WHERE username = ? OR email = ?
             LIMIT 1"
        );

        $loginUser->bind_param(
            "ss",
            $username,
            $username
        );

        $loginUser->execute();

        $loginUser->store_result();

        if ($loginUser->num_rows === 1) {

            $loginUser->bind_result(
                $userId,
                $firstName,
                $lastName,
                $dbUsername,
                $email,
                $hashedPassword,
                $role,
                $status
            );

            $loginUser->fetch();


            if ($status !== "active") {

                $error = "Your account is inactive.";

            } elseif (password_verify($password, $hashedPassword)) {


                session_regenerate_id(true);


                $_SESSION["user_id"] = $userId;

                $_SESSION["first_name"] = $firstName;

                $_SESSION["last_name"] = $lastName;

                $_SESSION["username"] = $dbUsername;

                $_SESSION["email"] = $email;

                $_SESSION["role"] = $role;


                /*
                |--------------------------------------------------------------------------
                | ROLE REDIRECTION
                |--------------------------------------------------------------------------
                */

                if ($role === "bracket_admin") {

                    header(
                        "Location: ../bracketing/index.php"
                    );

                    exit;

                }


                if ($role === "team_captain") {

                    header(
                        "Location: ../index.php"
                    );

                    exit;

                }


                if ($role === "staff") {

                    header(
                        "Location: ../index.php"
                    );

                    exit;

                }


                if ($role === "broadcast_operator") {

                    header(
                        "Location: ../index.php"
                    );

                    exit;

                }


                if ($role === "organizer") {

                    header(
                        "Location: ../index.php"
                    );

                    exit;

                }


                if ($role === "admin") {

                    header(
                        "Location: ../index.php"
                    );

                    exit;

                }


                /*
                 * Fallback
                 */

                header(
                    "Location: ../index.php"
                );

                exit;


            } else {

                $error = "Invalid username/email or password.";

            }


        } else {

            $error = "Invalid username/email or password.";

        }


        $loginUser->close();

    }

}
?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        TOURNIVOX | User Login
    </title>

</head>


<body>


    <h1>
        TOURNIVOX
    </h1>


    <h2>
        User Login
    </h2>


    <?php if ($message !== ""): ?>

        <p>
            <?php echo htmlspecialchars($message); ?>
        </p>

    <?php endif; ?>


    <?php if ($error !== ""): ?>

        <p>
            <?php echo htmlspecialchars($error); ?>
        </p>

    <?php endif; ?>


    <form
        method="POST"
        action=""
    >


        <div>

            <label for="username">

                Username or Email

            </label>

            <br>

            <input
                type="text"
                id="username"
                name="username"
                value="<?php echo htmlspecialchars($_POST["username"] ?? ""); ?>"
                required
            >

        </div>


        <br>


        <div>

            <label for="password">

                Password

            </label>

            <br>

            <input
                type="password"
                id="password"
                name="password"
                required
            >

        </div>


        <br>


        <div>

            <label>

                <input
                    type="checkbox"
                    name="remember"
                >

                Remember me

            </label>

        </div>


        <br>


        <button type="submit">

            Login

        </button>


    </form>


    <br>


    <p>

        Don't have an account?

        <a href="register.php">

            Register Here

        </a>

    </p>


    <a href="../index.php">

        Back to Home

    </a>


</body>

</html>