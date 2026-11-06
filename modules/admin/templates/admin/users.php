<?php
/**
 * @var array $users
 * @var bool $created
 * @var string $error
 * @var \FlatCms\Http\TemplateContext $this
 */
ob_start();
?>
<?php if (!empty($created)): ?><p class="notice">Пользователь создан.</p><?php endif; ?>
<?php if (!empty($error)): ?><p class="error"><?= $this->e($error) ?></p><?php endif; ?>

<table class="table">
    <thead><tr><th>Логин</th><th>Роль</th><th>Создан</th></tr></thead>
    <tbody>
    <?php foreach ($users as $login => $user): ?>
        <tr>
            <td><?= $this->e($login) ?></td>
            <td><?= $this->e($user['role'] ?? 'editor') ?></td>
            <td><?= $this->e($user['created_at'] ?? '') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<h2>Добавить пользователя</h2>
<form method="post" action="<?= $this->e($this->url('/admin/users/create')) ?>">
    <label>Логин <input type="text" name="login" required></label>
    <label>Пароль <input type="password" name="password" minlength="8" required></label>
    <label>Роль
        <select name="role">
            <option value="editor">editor</option>
            <option value="admin">admin</option>
        </select>
    </label>
    <button type="submit">Создать</button>
</form>
<?php
$content = ob_get_clean();
echo $this->partial('admin/layout', ['content' => $content, 'title' => 'Пользователи', 'admin_user' => $admin_user ?? null]);
