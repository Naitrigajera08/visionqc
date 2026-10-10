<?php
$footerUser = function_exists('auth_user') ? auth_user() : null;
$footerIsAdmin = is_array($footerUser)
    && isset($footerUser['role'])
    && strcasecmp((string) $footerUser['role'], 'Administrator') === 0;
?>
<footer class="site-footer">
    <div class="site-footer-inner">
        <div>
            <a class="site-footer-brand" href="<?= $footerUser !== null ? 'quality_control_dashboard.php' : 'login.php' ?>">VisionQC</a>
            <p class="site-footer-copy">Quality intelligence · &copy; <?= date('Y') ?> VisionQC</p>
        </div>
        <nav class="site-footer-nav" aria-label="Footer navigation">
            <?php if ($footerUser !== null): ?>
                <a href="quality_control_dashboard.php">Overview</a>
                <a href="workspace.php?view=live-monitoring">Live monitoring</a>
                <a href="workspace.php?view=product-inspection">Inspections</a>
                <a href="operators.php">Operators</a>
                <?php if ($footerIsAdmin): ?>
                    <a href="admin.php">Admin panel</a>
                    <a href="records.php">Record management</a>
                <?php endif; ?>
                <a href="profile.php">Profile</a>
            <?php else: ?>
                <a href="login.php">Sign in</a>
                <a href="login.php?mode=signup">Create account</a>
                <a href="login.php?mode=admin">Administrator sign in</a>
            <?php endif; ?>
        </nav>
    </div>
</footer>
