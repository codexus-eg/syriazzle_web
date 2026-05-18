<?php
header('Content-Type: application/json');

require_once 'php/db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'الطلب غير مسموح.']);
    exit;
}

$email_or_phone = trim($_POST['email_or_phone'] ?? '');
$password = $_POST['password'] ?? '';
$remember_me = isset($_POST['remember_me']) && $_POST['remember_me'] === '1';

if (empty($email_or_phone) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'يرجى إدخال البريد الإلكتروني/رقم الهاتف وكلمة المرور.']);
    exit;
}

$stmt = $pdo->prepare("SELECT id, username, email, password FROM users WHERE email = ? OR phone = ?");
$stmt->execute([$email_or_phone, $email_or_phone]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($password, $user['password'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'البريد الإلكتروني/رقم الهاتف أو كلمة المرور غير صحيحة.']);
    exit;
}

$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['user_email'] = $user['email'];

if ($remember_me) {

    $selector = bin2hex(random_bytes(16));
    $authenticator = random_bytes(32);
    $hashed_authenticator = password_hash(bin2hex($authenticator), PASSWORD_DEFAULT);


    $expires = new DateTime();
    $expires->modify('+1 year');
    $expires_timestamp = $expires->getTimestamp();

    $stmt_insert = $pdo->prepare("INSERT INTO remember_tokens (user_id, selector, hashed_authenticator, expires) VALUES (?, ?, ?, ?)");
    $stmt_insert->execute([$user['id'], $selector, $hashed_authenticator, $expires->format('Y-m-d H:i:s')]);

    setcookie(
        'remember_me_selector',
        $selector,
        [
            'expires' => $expires_timestamp,
            'path' => '/',
            'domain' => '.syriazzle.sy',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ]
    );
    setcookie(
        'remember_me_authenticator',
        bin2hex($authenticator),
        [
            'expires' => $expires_timestamp,
            'path' => '/',
            'domain' => '.syriazzle.sy',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ]
    );
}

http_response_code(200);
echo json_encode(['success' => true, 'message' => 'تم تسجيل الدخول بنجاح!']);
?>