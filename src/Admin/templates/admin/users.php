<?php
/**
 * Users page. The list is the main content; the "add user" form stays collapsed
 * behind a button so the page does not lead with a form.
 *
 * @var array $users
 * @var bool $created
 * @var string $error
 * @var \FlatCms\Http\TemplateContext $this
 */
ob_start();
?>
<?php if (!empty($created)): ?><p class="notice"><?= $this->icon('check') ?> Пользователь создан.</p><?php endif; ?>
<?php if (!empty($error)): ?><p class="error"><?= $this->icon('warning') ?> <?= $this->e($error) ?></p><?php endif; ?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h2 class="mb-0"><?= $this->icon('users') ?> Пользователи</h2>
    <button class="btn btn-primary btn-sm ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#user-form" aria-expanded="false" aria-controls="user-form">
        <?= $this->icon('add') ?> Добавить пользователя
    </button>
</div>

<div class="collapse mb-4" id="user-form">
    <form method="post" action="<?= $this->e($this->url('/admin/users/create')) ?>" class="panel">
        <h2><?= $this->icon('add') ?> Новый пользователь</h2>
        <div class="form-grid">
            <label>Логин
                <input type="text" name="login" required>
            </label>
            <label>Пароль
                <input type="password" name="password" minlength="8" required>
            </label>
            <label class="form-field--full">Роль
                <select name="role">
                    <option value="editor">editor — редактирует контент</option>
                    <option value="admin">admin — полный доступ</option>
                </select>
            </label>
        </div>
        <button type="submit" class="btn btn-primary mt-2"><?= $this->icon('save') ?> Создать</button>
    </form>
</div>

<div class="panel p-0">
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
<?php
$content = ob_get_clean();
echo $this->partial('admin/layout', ['content' => $content, 'title' => 'Пользователи', 'active' => 'users', 'admin_user' => $admin_user ?? null]);
