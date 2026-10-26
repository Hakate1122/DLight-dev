<?php

use App\Chat\Chat;
use App\Middleware\UserAuthencation;
use DLight\Application\Command;
use DLight\Application\DB;

UserAuthencation::sign();

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
            ->view('emails.mail');
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
    $port = isset($options['port']) ? (int) $options['port'] : 9501;

    $chat = new Chat($host, $port);
    $chat->start();
});

$cli->registerAlias('ws:server', 'websocket:server');

$cli->register('env', function () {
    $env = new Datahihi1\TinyEnv\TinyEnv(ROOT_DIR);
    dump($env);
    $env->load();
    dump($env);
    dd(env());
});

$cli->register('test:db', function () {
$users = DB::mapper()
    ->table('users')
    ->all();

dump($users);
});

$cli->register('mtest', function () {

});