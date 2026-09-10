<?php

use App\Chat\Chat;
use DLight\Application\Command;

if (!isset($cli) || !($cli instanceof Command)) {
    $cli = new Command();
}

$cli->register('hello', [\App\Command\Hello::class, 'handle']);
$cli->register('choice', [\App\Command\Hello::class, 'choice']);
$cli->register('hi', [\App\Command\Hello::class, 'handle']);

$cli->register('sample', [\App\Command\Sample::class, 'handle']);
$cli->register('quiz', [\App\Command\Sample::class, 'quiz']);
$cli->register('try-connect-sql', [\App\Command\Sample::class, 'tryConnectSQL']);
$cli->register('try-connect-db', [\App\Command\Sample::class, 'tryConnectDB']);

$cli->register('send:mail', function () {
    try {
        $mail = new DLight\Application\Mail();
        $mail->to(email: 'datd5400@gmail.com')
            ->subject(subject: 'Test Email from DLight Mailer 2.0')
            ->body('This is a test email sent from DLight Mailer 2.0.');
        $mail->send();
        echo cli_green("Email sent successfully.\n");
    } catch (Exception $e) {
        echo cli_red("Failed to send email: " . $e->getMessage() . "\n");
    }
});

$cli->register('benchmark:sort', [\App\Command\BenchmarkSort::class, 'handle']);

$cli->register('websocket:server', function () {

    $options = getopt('', ['host:', 'port:']);
    $host = $options['host'] ?? '0.0.0.0';
    $port = isset($options['port']) ? (int)$options['port'] : 9501;

    $chat = new Chat($host, $port);
    $chat->start();
});

$cli->registerAlias('ws:server', 'websocket:server');

$cli->register('env',function(){
    $env = new Datahihi1\TinyEnv\TinyEnv(ROOT_DIR);
    dump($env);
    $env->load();
    dump($env);
    dd(env());
});

$cli->register('test:symfony_console',function(){

// Khởi tạo output cho CLI
$output = new Symfony\Component\Console\Output\ConsoleOutput();

// Dữ liệu sản phẩm
$products = [
    [1, 'Laptop Gaming ASUS ROG', '25,000,000 đ', 12],
    [2, 'Bàn phím cơ Keychron K6', '1,850,000 đ', 45],
    [3, 'Chuột Logitech G304', '850,000 đ', 30],
    [4, 'Màn hình UltraWide LG 29"', '5,600,000 đ', 5],
];

// Khởi tạo bảng
$table = new Symfony\Component\Console\Helper\Table($output);

// Thiết lập tiêu đề và dữ liệu
$table
    ->setHeaders(['ID', 'Tên sản phẩm', 'Giá bán', 'Tồn kho'])
    ->setRows($products);

// Hiển thị bảng ra màn hình terminal
$output->writeln("<info>=== QUẢN LÝ SẢN PHẨM CLI ===</info>");
$table->render();
});