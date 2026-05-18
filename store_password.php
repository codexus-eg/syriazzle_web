<?php
// simple_hash.php
$hash = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if ($password === '') {
        $error = 'الرجاء إدخال كلمة المرور.';
    } else {
        // توليد هاش آمن
        $hash = password_hash($password, PASSWORD_DEFAULT);
    }
}
?>
<!doctype html>
<html lang="ar">
<head>
  <meta charset="utf-8">
  <title>تشفير كلمة المرور بسيط</title>
  <style>
    body{font-family:Arial, sans-serif;direction:rtl;padding:20px;background:#f5f5f5}
    .box{max-width:600px;margin:0 auto;background:#fff;padding:18px;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,.08)}
    input,textarea,button{width:100%;padding:10px;margin-top:8px;box-sizing:border-box}
    label{font-weight:700}
    .note{font-size:13px;color:#555;margin-top:8px}
    .sql{font-family:monospace;background:#f0f0f0;padding:8px;border-radius:6px;word-break:break-all}
    .error{color:#b00020}
  </style>
  <script>
    function copyHash() {
      const t = document.getElementById('hashArea');
      if (!t) return;
      t.select();
      document.execCommand('copy');
      alert('تم نسخ الهَاش إلى الحافظة');
    }
  </script>
</head>
<body>
  <div class="box">
    <h2>أدخل كلمة المرور ثم اضغط "تشفير"</h2>

    <?php if ($error): ?>
      <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <label>كلمة المرور</label>
      <input type="password" name="password" required>
      <button type="submit">تشفير</button>
    </form>

    <?php if ($hash): ?>
      <h3>الهاش الناتج (انسخّه وضعه في عمود password في قاعدة البيانات):</h3>
      <textarea id="hashArea" rows="3" readonly><?= htmlspecialchars($hash) ?></textarea>
      <button onclick="copyHash()" style="margin-top:8px;">نسخ الهَاش</button>

      <p class="note">مثال SQL لتحديث كلمة مرور مستخدم موجود (غيّر اسم المستخدم حسب حاجتك):</p>
      <div class="sql"><?= "UPDATE users SET password = '" . htmlspecialchars($hash, ENT_QUOTES) . "' WHERE username = 'اسم_المستخدم';" ?></div>

      <p class="note"><strong>ملاحظة مهمة:</strong> عند تسجيل الدخول في تطبيقك استخدم `password_verify($enteredPassword, $storedHash)` للمقارنة — لا تقارن النصوص مباشرة.</p>

      <pre style="background:#f8f8f8;padding:8px;border-radius:6px">مثال تحقق عند تسجيل الدخول:
if (password_verify($enteredPassword, $storedHash)) {
    // صحيح - اسمح بالدخول
} else {
    // خطأ
}</pre>
    <?php endif; ?>

    <p class="note">ملاحظة أمان: هذا سكربت بسيط لأغراض تجريبية. لا تستخدمه كما هو في بيئة عامة دون HTTPS وإجراءات أمان أخرى.</p>
  </div>
</body>
</html>
