<?php
/**
 * @var array $errors
 * @var array $values
 * @var bool $sent
 * @var array $site
 * @var \FlatCms\App $app
 * @var \FlatCms\Http\TemplateContext $this
 */
ob_start();
?>
<section class="contact">
    <h1>Контакты</h1>

    <?php if (!empty($sent)): ?>
        <p class="notice">Спасибо! Сообщение отправлено.</p>
    <?php endif; ?>

    <form method="post" action="<?= $this->e($this->url('/contact')) ?>">
        <label>
            Имя
            <input type="text" name="name" value="<?= $this->e($values['name'] ?? '') ?>">
            <?php if (isset($errors['name'])): ?><em class="error"><?= $this->e($errors['name']) ?></em><?php endif; ?>
        </label>

        <label>
            Email
            <input type="email" name="email" value="<?= $this->e($values['email'] ?? '') ?>">
            <?php if (isset($errors['email'])): ?><em class="error"><?= $this->e($errors['email']) ?></em><?php endif; ?>
        </label>

        <label>
            Сообщение
            <textarea name="message" rows="6"><?= $this->e($values['message'] ?? '') ?></textarea>
            <?php if (isset($errors['message'])): ?><em class="error"><?= $this->e($errors['message']) ?></em><?php endif; ?>
        </label>

        <button type="submit">Отправить</button>
    </form>
</section>
<?php
$content = ob_get_clean();
echo $this->partial('layout', ['content' => $content, 'title' => 'Контакты']);
