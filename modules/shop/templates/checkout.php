<?php
/**
 * @var array $lines
 * @var float $total
 * @var string $currency
 * @var array $providers
 * @var array $errors
 * @var array $values
 * @var \FlatCms\App $app
 * @var \FlatCms\Http\TemplateContext $this
 */
ob_start();
?>
<section class="checkout">
    <h1>Оформление заказа</h1>

    <table class="checkout__summary">
        <?php foreach ($lines as $line): ?>
            <tr>
                <td><?= $this->e($line->title) ?> × <?= (int) $line->qty ?></td>
                <td><?= $this->e(number_format($line->total(), 0, '.', ' ')) ?> <?= $this->e($currency) ?></td>
            </tr>
        <?php endforeach; ?>
        <tr class="checkout__total">
            <td>Итого</td>
            <td><?= $this->e(number_format($total, 0, '.', ' ')) ?> <?= $this->e($currency) ?></td>
        </tr>
    </table>

    <form method="post" action="<?= $this->e($this->url('/checkout')) ?>">
        <label>Имя <input type="text" name="name" value="<?= $this->e($values['name'] ?? '') ?>">
            <?php if (isset($errors['name'])): ?><em class="error"><?= $this->e($errors['name']) ?></em><?php endif; ?>
        </label>
        <label>Email <input type="email" name="email" value="<?= $this->e($values['email'] ?? '') ?>">
            <?php if (isset($errors['email'])): ?><em class="error"><?= $this->e($errors['email']) ?></em><?php endif; ?>
        </label>
        <label>Телефон <input type="text" name="phone" value="<?= $this->e($values['phone'] ?? '') ?>">
            <?php if (isset($errors['phone'])): ?><em class="error"><?= $this->e($errors['phone']) ?></em><?php endif; ?>
        </label>
        <label>Адрес <input type="text" name="address" value="<?= $this->e($values['address'] ?? '') ?>">
            <?php if (isset($errors['address'])): ?><em class="error"><?= $this->e($errors['address']) ?></em><?php endif; ?>
        </label>
        <label>Комментарий <textarea name="comment" rows="3"><?= $this->e($values['comment'] ?? '') ?></textarea></label>

        <fieldset>
            <legend>Оплата</legend>
            <?php foreach ($providers as $name => $provider): ?>
                <label class="radio">
                    <input type="radio" name="provider" value="<?= $this->e($name) ?>"
                        <?= ($values['provider'] ?? '') === $name ? 'checked' : '' ?>>
                    <?= $this->e($provider->title()) ?>
                </label>
            <?php endforeach; ?>
            <?php if (isset($errors['provider'])): ?><em class="error"><?= $this->e($errors['provider']) ?></em><?php endif; ?>
        </fieldset>

        <button type="submit">Подтвердить заказ</button>
    </form>
</section>
<?php
$content = ob_get_clean();
echo $this->partial('layout', ['content' => $content, 'title' => 'Оформление заказа']);
