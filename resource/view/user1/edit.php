<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sửa người dùng</title>
    <link rel="stylesheet" href="<?= asset('source/luma.one.css') ?>">
</head>
<body class="lmo-container">
    <?php
        $toastError = '';
        if (!empty($error)) {
            if (is_array($error)) {
                foreach ($error as $messages) {
                    if (is_array($messages)) {
                        $toastError = (string) reset($messages);
                        break;
                    }
                    $toastError = (string) $messages;
                    break;
                }
            } else {
                $toastError = (string) $error;
            }
        }
    ?>

    <div class="lmo-card" style="margin: 2rem auto; padding: 2rem; max-width: 640px;">
        <div class="lmo-row" style="justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 1rem;">
            <div>
                <p class="lmo-text-muted" style="margin-bottom: 0.5rem;">Cập nhật thông tin</p>
                <h1 style="margin: 0; font-size: 2rem;">Sửa người dùng</h1>
            </div>
            <a href="<?= route('user.list') ?>" class="lmo-btn lmo-btn-secondary lmo-btn-sm">Quay lại danh sách</a>
        </div>

        <?php if (!empty($error)): ?>
            <div class="lmo-alert lmo-alert-danger" role="alert" style="margin-bottom: 1rem;">
                <strong>Lỗi:</strong>
                <div style="margin-top: 0.5rem;">
                    <?php if (is_array($error)): ?>
                        <?php foreach ($error as $messages): ?>
                            <?php if (is_array($messages)): ?>
                                <?php foreach ($messages as $msg): ?>
                                    <p style="margin: 0.25rem 0;"><?= htmlspecialchars((string)$msg) ?></p>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="margin: 0.25rem 0;"><?= htmlspecialchars((string)$messages) ?></p>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="margin: 0.25rem 0;"><?= htmlspecialchars((string)$error) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <form class="lmo-form" action="<?= route('user.update', ['id' => $user['id']]) ?>" method="POST">
            <div class="lmo-form-row">
                <label class="lmo-label" for="name">Tên</label>
                <input class="lmo-input" type="text" id="name" name="name" value="<?= htmlspecialchars($user['name']) ?>" placeholder="Nhập tên người dùng">
            </div>

            <div class="lmo-form-row">
                <label class="lmo-label" for="email">Email</label>
                <input class="lmo-input" type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" placeholder="Nhập địa chỉ email">
            </div>

            <div class="lmo-form-actions" style="margin-top: 1.5rem;">
                <button type="submit" class="lmo-btn lmo-btn-primary">Cập nhật</button>
            </div>
        </form>
    </div>

    <script src="<?= asset('source/luma.one.js') ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var toastError = <?= json_encode($toastError) ?>;
            if (toastError) {
                lmoToast(toastError, { type: 'error', duration: 4500 });
            }
        });
    </script>
</body>
</html>