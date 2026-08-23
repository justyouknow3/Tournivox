<?php

// checking if the dataabse xampp is supported by the argon2id




if (defined("PASSWORD_ARGON2ID")) {
    echo "Argon2id is supported.";
} else {
    echo "Argon2id is NOT supported.";
}

?>