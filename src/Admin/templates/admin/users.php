<?php
/**
 * @var array $users
 * @var bool $created
 * @var string $error
 * @var \FlatCms\Http\TemplateContext $this
 */
ob_start();
?>
<?php if (!empty($created)): ?><p class="notice"><?= $this->icon('check') ?> Пользователь создан.</p><?php endif; ?>
<?php if (!empty($error)): ?><p class="error"><?= $this->icon('warning') ?> <?= $this->e($error) ?></p><?php endif; ?>

<div class="panel p-0 mb-4">
<table class="table align-middle mb-0">
    <thead><tr><th>Логин</th><th>Роль</th><th>Создан</th></tr></thead>
    <tbody>
    <?php foreach ($users as $login => $user): ?>
        <tr>
            <td><span class="admin__avatar" style="display:inline-grid"><?= $this->e(mb_strtoupper(mb_substr((string) $login, 0, 1))) ?></span> <?= $this->e($login) ?></td>
            <td>
                <?php $role = $user['role'] ?? 'editor'; ?>
                <span class="badge <?= $role === 'admin' ? 'text-bg-primary' : 'text-bg-light' ?>"><?= $this->e($role) ?></span>
            </td>
            <td class="muted"><?= $this->e($user['created_at'] ?? '') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<h2><?= $this->icon('add') ?> Добавить пользователя</h2>
<form method="post" action="<?= $this->e($this->url('/admin/users/create')) ?>" class="panel">
    <label>Логин <input type="text" name="login" required></label>
    <label>Пароль <input type="password" name="password" minlength="8" required></label>
    <label>Роль
        <select name="role">
            <option value="editor">editor</option>
            <option value="admin">admin</option>
        </select>
    </label>
    <button type="submit" class="btn btn-primary"><?= $this->icon('save') ?> Создать</button>
</form>
<?php
$content = ob_get_clean();
echo $this->partial('admin/layout', ['content' => $content, 'title' => 'Пользователи', 'active' => 'users', 'admin_user' => $admin_user ?? null]);
