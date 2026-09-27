<?php
/**
 * @var string $title
 * @var string $message
 * @var string|null $trace
 */
?>
<div class="card">
    <h3><?= e($title) ?></h3>
    <p><?= e($message) ?></p>
    <div class="actions">
        <a class="btn btn-secondary" href="/">Kembali ke dashboard</a>
    </div>
    <?php if (! empty($trace)): ?>
        <details>
            <summary>Detail teknis</summary>
            <pre><?= e($trace) ?></pre>
        </details>
    <?php endif; ?>
</div>
