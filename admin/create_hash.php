    <?php
    $password_to_hash = 'admin123';
    $hashed_password = password_hash($password_to_hash, PASSWORD_DEFAULT);

    echo "كلمة المرور: " . $password_to_hash . "<br>";
    echo "الهاش المشفر الصحيح هو:<br>";
    echo "<textarea rows='3' cols='70' readonly>" . htmlspecialchars($hashed_password) . "</textarea>";
    ?>