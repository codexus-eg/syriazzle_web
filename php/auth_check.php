<?php

if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me_selector']) && isset($_COOKIE['remember_me_authenticator'])) {
    error_log("Auth Check: Remember Me cookies found. Selector: " . $_COOKIE['remember_me_selector']);

    require_once 'db_connect.php';

    $selector = $_COOKIE['remember_me_selector'];
    $authenticator_from_cookie = $_COOKIE['remember_me_authenticator'];

    $stmt = $pdo->prepare(
        "SELECT u.id, u.username, u.email, r.hashed_authenticator, r.expires
         FROM users u
         JOIN remember_tokens r ON u.id = r.user_id
         WHERE r.selector = ? AND r.expires > NOW()"
    );
    $stmt->execute([$selector]);
    $token_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($token_data) {
        error_log("Auth Check: Selector found in DB. Expires: " . $token_data['expires']);

        if (password_verify($authenticator_from_cookie, $token_data['hashed_authenticator'])) {
            error_log("Auth Check: Authenticator verified. User ID: " . $token_data['id']);
            $_SESSION['user_id'] = $token_data['id'];
            $_SESSION['username'] = $token_data['username'];
            $_SESSION['user_email'] = $token_data['email'];

            $new_selector = bin2hex(random_bytes(16));

            $new_authenticator = random_bytes(32);
            $new_hashed_authenticator = password_hash(bin2hex($new_authenticator), PASSWORD_DEFAULT);

            $stmt_update = $pdo->prepare(
                "UPDATE remember_tokens
                 SET selector = ?, hashed_authenticator = ?, expires = ?
                 WHERE selector = ?"
            );

            $expires = new DateTime();
            $expires->modify('+1 year'); // يجب أن تكون مدة الصلاحية الجديدة مطابقة للأصلية
            $stmt_update->execute([$new_selector, $new_hashed_authenticator, $expires->format('Y-m-d H:i:s'), $selector]);


            setcookie(
                'remember_me_selector',
                $new_selector,
                [
                    'expires' => $expires->getTimestamp(),
                    'path' => '/',
                    'domain' => '.syriazzle.sy',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]
            );
            setcookie(
                'remember_me_authenticator',
                bin2hex($new_authenticator), // أرسل الـ authenticator الجديد بصيغة hex
                [
                    'expires' => $expires->getTimestamp(),
                    'path' => '/',
                    'domain' => '.syriazzle.sy',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]
            );

            error_log("Auth Check: Tokens renewed successfully and new cookies set.");

        } else {
            error_log("Auth Check: Authenticator mismatch. Possible attack. Clearing token for selector: " . $selector);
            $stmt_delete = $pdo->prepare("DELETE FROM remember_tokens WHERE selector = ?");
            $stmt_delete->execute([$selector]);

            setcookie('remember_me_selector', '', time() - 3600, '/', '.syriazzle.sy', true, true);
            setcookie('remember_me_authenticator', '', time() - 3600, '/', '.syriazzle.sy', true, true);
        }
    } else {
        error_log("Auth Check: Selector not found or expired. Clearing cookies.");
        setcookie('remember_me_selector', '', time() - 3600, '/', '.syriazzle.sy', true, true);
        setcookie('remember_me_authenticator', '', time() - 3600, '/', '.syriazzle.sy', true, true);
    }
}
?>