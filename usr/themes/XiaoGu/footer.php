<footer class="site-footer">
    <span>© <?php echo date('Y'); ?> 小古有趣 · 记录生活，分享有趣</span>
    <?php $icpBeianNumber = trim((string) $this->options->icpBeianNumber); ?>
    <?php if ($icpBeianNumber !== ''): ?>
        <a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener noreferrer">
            <?php echo htmlspecialchars($icpBeianNumber, ENT_QUOTES, 'UTF-8'); ?>
        </a>
    <?php endif; ?>
</footer>
