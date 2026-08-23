<?php
session_start();

require_once "database.php";

$error = "";

$firstName = "";
$lastName = "";
$username = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $firstName = trim($_POST["first_name"] ?? "");
    $lastName = trim($_POST["last_name"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    $role = "team_captain";
    $status = "active";


    if (
        $firstName === "" ||
        $lastName === "" ||
        $username === "" ||
        $email === "" ||
        $password === "" ||
        $confirmPassword === ""
    ) {

        $error = "Please complete all fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } elseif (strlen($username) < 3) {

        $error = "Username must be at least 3 characters.";

    } elseif (strlen($password) < 8) {

        $error = "Password must be at least 8 characters.";

    } elseif ($password !== $confirmPassword) {

        $error = "Passwords do not match.";

    } else {

        $checkUsername = $conn->prepare(
            "SELECT user_id
             FROM users
             WHERE username = ?
             LIMIT 1"
        );

        $checkUsername->bind_param(
            "s",
            $username
        );

        $checkUsername->execute();
        $checkUsername->store_result();


        if ($checkUsername->num_rows > 0) {

            $error = "Username is already taken.";

        } else {

            $checkEmail = $conn->prepare(
                "SELECT user_id
                 FROM users
                 WHERE email = ?
                 LIMIT 1"
            );

            $checkEmail->bind_param(
                "s",
                $email
            );

            $checkEmail->execute();
            $checkEmail->store_result();


            if ($checkEmail->num_rows > 0) {

                $error = "Email is already registered.";

            } else {

                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_ARGON2ID
                );


                $insertUser = $conn->prepare(
                    "INSERT INTO users
                    (
                        first_name,
                        last_name,
                        username,
                        email,
                        password,
                        role,
                        status
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?)"
                );


                $insertUser->bind_param(
                    "sssssss",
                    $firstName,
                    $lastName,
                    $username,
                    $email,
                    $hashedPassword,
                    $role,
                    $status
                );


                if ($insertUser->execute()) {

                    header(
                        "Location: login.php?registered=1"
                    );

                    exit;

                } else {

                    $error = "Registration failed. Please try again.";

                }


                $insertUser->close();
            }


            $checkEmail->close();
        }


        $checkUsername->close();
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

    <title>TOURNIVOX | Register</title>


    <style>

        .input-error {
            border: 2px solid red;
        }

        .input-success {
            border: 2px solid green;
        }

        .field-message {
            font-size: 14px;
            margin-top: 5px;
        }

        .error-message {
            color: red;
        }

        .success-message {
            color: green;
        }

    </style>

</head>


<body>

    <h1>TOURNIVOX</h1>

    <h2>Create Account</h2>


    <?php if ($error !== ""): ?>

        <p>
            <?php echo htmlspecialchars($error); ?>
        </p>

    <?php endif; ?>


    <form
        method="POST"
        action=""
        id="registerForm"
    >


        <div>

            <label for="first_name">
                First Name
            </label>

            <br>

            <input
                type="text"
                id="first_name"
                name="first_name"
                value="<?php echo htmlspecialchars($firstName); ?>"
                required
            >

        </div>


        <br>


        <div>

            <label for="last_name">
                Last Name
            </label>

            <br>

            <input
                type="text"
                id="last_name"
                name="last_name"
                value="<?php echo htmlspecialchars($lastName); ?>"
                required
            >

        </div>


        <br>


        <div>

            <label for="username">
                Username
            </label>

            <br>

            <input
                type="text"
                id="username"
                name="username"
                value="<?php echo htmlspecialchars($username); ?>"
                required
            >

            <div
                id="usernameMessage"
                class="field-message"
            ></div>

        </div>


        <br>


        <div>

            <label for="email">
                Email
            </label>

            <br>

            <input
                type="email"
                id="email"
                name="email"
                value="<?php echo htmlspecialchars($email); ?>"
                required
            >

            <div
                id="emailMessage"
                class="field-message"
            ></div>

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

            <label for="confirm_password">
                Confirm Password
            </label>

            <br>

            <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                required
            >

        </div>


        <br>


        <button
            type="submit"
            id="registerButton"
        >
            Register
        </button>

    </form>


    <br>


    <p>

        Already have an account?

        <a href="login.php">
            Login here
        </a>

    </p>


    <a href="../index.php">
        Back to Home
    </a>


<script>

const usernameInput =
    document.getElementById("username");

const emailInput =
    document.getElementById("email");

const usernameMessage =
    document.getElementById("usernameMessage");

const emailMessage =
    document.getElementById("emailMessage");

const registerButton =
    document.getElementById("registerButton");


let usernameTaken = false;
let emailTaken = false;

let usernameTimer;
let emailTimer;


function updateRegisterButton() {

    registerButton.disabled =
        usernameTaken || emailTaken;

}


async function checkAvailability(
    type,
    value,
    input,
    message
) {

    if (value.trim() === "") {

        input.classList.remove(
            "input-error",
            "input-success"
        );

        message.textContent = "";

        return;
    }


    if (
        type === "username" &&
        value.length < 3
    ) {

        input.classList.remove(
            "input-success"
        );

        input.classList.add(
            "input-error"
        );

        message.textContent =
            "Username must be at least 3 characters.";

        message.className =
            "field-message error-message";

        usernameTaken = true;

        updateRegisterButton();

        return;
    }


    if (type === "email") {

        const emailPattern =
            /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (!emailPattern.test(value)) {

            input.classList.remove(
                "input-success"
            );

            input.classList.add(
                "input-error"
            );

            message.textContent =
                "Enter a valid email address.";

            message.className =
                "field-message error-message";

            emailTaken = true;

            updateRegisterButton();

            return;
        }

    }


    try {

        const response = await fetch(
            "check_availability.php?type=" +
            encodeURIComponent(type) +
            "&value=" +
            encodeURIComponent(value)
        );


        const data = await response.json();


        if (data.taken) {

            input.classList.remove(
                "input-success"
            );

            input.classList.add(
                "input-error"
            );


            if (type === "username") {

                message.textContent =
                    "Username is already taken.";

                usernameTaken = true;

            } else {

                message.textContent =
                    "Email is already registered.";

                emailTaken = true;

            }


            message.className =
                "field-message error-message";


        } else {

            input.classList.remove(
                "input-error"
            );

            input.classList.add(
                "input-success"
            );


            if (type === "username") {

                message.textContent =
                    "Username is available.";

                usernameTaken = false;

            } else {

                message.textContent =
                    "Email is available.";

                emailTaken = false;

            }


            message.className =
                "field-message success-message";

        }


        updateRegisterButton();


    } catch (error) {

        console.error(
            "Availability check failed:",
            error
        );

    }

}


usernameInput.addEventListener(
    "input",
    function () {

        clearTimeout(usernameTimer);


        usernameTimer = setTimeout(
            function () {

                checkAvailability(
                    "username",
                    usernameInput.value.trim(),
                    usernameInput,
                    usernameMessage
                );

            },
            400
        );

    }
);


emailInput.addEventListener(
    "input",
    function () {

        clearTimeout(emailTimer);


        emailTimer = setTimeout(
            function () {

                checkAvailability(
                    "email",
                    emailInput.value.trim(),
                    emailInput,
                    emailMessage
                );

            },
            400
        );

    }
);

</script>


</body>

</html>