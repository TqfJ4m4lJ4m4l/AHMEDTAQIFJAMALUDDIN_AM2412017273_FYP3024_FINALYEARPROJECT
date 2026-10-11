<?php

function autoAssignRole(
    $conn,
    $user_ID,
    $name,
    $birthdate,
    $gender,
    $email
) {

    $email = strtolower(trim($email));

    if (str_ends_with($email, '@hctm.staff.edu.my')) {

        $role_Type = "Admin";

    } elseif (str_ends_with($email, '@hctm.edu.my')) {

        $role_Type = "Doctor";

    } else {

        $role_Type = "Patient";
    }


    $role_sql = "INSERT INTO roles
                 (role_Type, users_ID)
                 VALUES (?, ?)";

    $role_stmt = $conn->prepare($role_sql);

    $role_stmt->bind_param(
        "si",
        $role_Type,
        $user_ID
    );

    if (!$role_stmt->execute()) {

        throw new Exception(
            "Error creating role: " . $role_stmt->error
        );
    }

    $role_ID = $conn->insert_id;


    if ($role_Type == "Patient") {

        $sql = "INSERT INTO patients
                (
                    users_ID,
                    role_ID,
                    patient_Name,
                    patient_BirthDate,
                    patient_Gender
                )
                VALUES (?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "iisss",
            $user_ID,
            $role_ID,
            $name,
            $birthdate,
            $gender
        );

    } elseif ($role_Type == "Doctor") {

        $sql = "INSERT INTO doctors
                (
                    users_ID,
                    role_ID,
                    doctor_Name,
                    doctor_BirthDate,
                    doctor_Gender
                )
                VALUES (?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "iisss",
            $user_ID,
            $role_ID,
            $name,
            $birthdate,
            $gender
        );

    } else {

        $sql = "INSERT INTO admins
                (
                    users_ID,
                    role_ID,
                    admin_Name,
                    admin_BirthDate,
                    admin_Gender
                )
                VALUES (?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "iisss",
            $user_ID,
            $role_ID,
            $name,
            $birthdate,
            $gender
        );
    }


    if (!$stmt->execute()) {

        throw new Exception(
            "Error creating role account: " . $stmt->error
        );
    }
}

?>