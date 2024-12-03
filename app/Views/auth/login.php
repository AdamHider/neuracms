<?= $this->extend('layouts/'.$this->data['settings']['layout']) ?>
<?= $this->section('content') ?>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3>Login</h3>
                </div>
                <div class="card-body">
                    <form action="/auth/authenticate" method="post">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" id="username" placeholder="Enter your username" value="<?= set_value('username') ?>">
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" id="password" placeholder="Enter your password">
                        </div>
                        <?php if(session()->getFlashdata('msg')): ?>
                            <div class="alert alert-danger">
                                <?= session()->getFlashdata('msg') ?>
                            </div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary w-100">Login</button>
                    </form>
                </div>
            </div>
            <div class="mt-3 text-center">
                <a href="/auth/register">Don't have an account? Register here</a>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
