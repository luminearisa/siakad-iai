<?php
/**
 * @var bool $hasUsers
 */
?>
<div class="login-wrap">
    <div class="login-card">
        <h1>Integrator SIAKAD</h1>
        <p class="sub">Menarik data akademik dari SIAKAD lalu mengirimkannya ke Neo Feeder PDDikti.</p>

        <?php if (! $hasUsers): ?>
            <div class="flash flash-error">
                Belum ada akun operator. Jalankan:<br>
                <code>php bin/console user:create "Nama" operator@&lt;domain-kampus&gt; "&lt;sandi-kuat-anda&gt;"</code>
            </div>
        <?php else: ?>
            <form method="post" action="/login">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required autofocus>
                </div>
                <div class="field">
                    <label for="password">Kata sandi</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button class="btn" style="width:100%" type="submit">Masuk</button>
            </form>
        <?php endif; ?>
    </div>
</div>
