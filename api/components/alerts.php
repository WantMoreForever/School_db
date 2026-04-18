<?php
// components/alerts.php
?>
<?php if (!empty($success_msg)): ?>
    <div class="alert-success fade-up">✅ <?= htmlspecialchars($success_msg) ?></div>
<?php endif; ?>

<?php if (!empty($error_msg)): ?>
    <div class="alert-error fade-up">❌ <?= htmlspecialchars($error_msg) ?></div>
<?php endif; ?>

<?php if (!empty($db_error)): ?>
    <div class="db-error-banner">
        ⚠ 数据库连接失败：<?= htmlspecialchars($db_error) ?>
    </div>
<?php endif; ?>