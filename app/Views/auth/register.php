<?= $this->extend('layouts/'.$this->data['settings']['layout']) ?>
<?= $this->section('content') ?>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3>Register</h3>
                </div>
                <div class="card-body">
                    <form action="/auth/store" method="post">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" id="username" placeholder="Enter your username" <?= set_value('username') ?>>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" id="email" placeholder="Enter your email" <?= set_value('email') ?>>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" id="password" placeholder="Enter your password">
                        </div>
                        <?php if(isset($this->data['validation'])): ?>
                            <div class="alert alert-danger">
                                <?= $validation->listErrors() ?>
                            </div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary w-100">Register</button>
                    </form>
                </div>
            </div>
            <div class="mt-3 text-center">
                <a href="/auth/login">Already have an account? Login here</a>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
