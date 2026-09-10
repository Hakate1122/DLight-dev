<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User List</title>
    <link rel="stylesheet" href="<?= asset('source/luma.one.css') ?>">
</head>
<body class="lmo-container">
    <div class="lmo-card" style="margin: 2rem 0; padding: 2rem;">
        <div class="lmo-row" style="justify-content: space-between; align-items: center; gap: 1rem;">
            <div>
                <p class="lmo-text-muted" style="margin-bottom: 0.5rem;">Quản lý người dùng</p>
                <h1 style="margin: 0; font-size: 2rem;">Danh sách người dùng</h1>
            </div>

            <a href="<?= route('user.add') ?>" class="lmo-btn lmo-btn-primary">Thêm người dùng</a>
        </div>

        <div class="lmo-table-wrap" style="margin-top: 1.75rem;">
            <table class="lmo-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên</th>
                        <th>Email</th>
                        <th>Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['id']) ?></td>
                            <td><?= htmlspecialchars($user['name']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td>
                                <div style="display: inline-flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
                                    <a href="<?= route('user.edit', ['id' => $user['id']]) ?>" class="lmo-btn lmo-btn-secondary lmo-btn-sm">Sửa</a>
                                    <form class="user-delete-form" data-user-name="<?= htmlspecialchars($user['name']) ?>" action="<?= route('user.delete', ['id' => $user['id']]) ?>" method="POST">
                                        <input type="hidden" name="_method" value="DELETE">
                                        <button type="submit" class="lmo-btn lmo-btn-danger lmo-btn-sm">Xóa</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="<?= asset('source/luma.one.js') ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.user-delete-form').forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    event.preventDefault();
                    var userName = form.getAttribute('data-user-name') || 'người dùng';

                    lmoConfirm('Bạn có chắc chắn muốn xóa ' + userName + '?').then(function (confirmed) {
                        if (confirmed) {
                            form.submit();
                        }
                    });
                });
            });

            var successMessage = <?= json_encode(getFlash('success')) ?>;
            var errorMessage = <?= json_encode(getFlash('error')) ?>;

            if (successMessage) {
                lmoToast(successMessage, { type: 'success', duration: 4500 });
            }
            if (errorMessage) {
                lmoToast(errorMessage, { type: 'error', duration: 4500 });
            }
        });
    </script>
</body>
</html>