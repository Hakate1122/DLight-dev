<?php

declare(strict_types=1);

$router = new DLight\Application\Router();

$router->signApi('GET /', fn() => "Hello, World!")->name('api.home');

// Demo Send Mail
$router->signApi('GET /demo/mail', function () {
    try {
        $mail = new DLight\Application\Mail();
        $mail->to(email: 'datndph42403@gmail.com')
            ->subject(subject: 'Test Email from DLight Mailer 2.0')
            ->body('This is a test email sent from DLight Mailer 2.0.');
        $mail->send();
        return 'Email sent successfully!';
    } catch (Exception $e) {
        return 'Error sending email: ' . $e->getMessage();
    }
})->name('api.demo.mail');

$router->signApi('GET /demo/mail/2', function () {
        mail("test@test.com", "Test Email from DLight Mailer 2.0",
            "This is a test email sent from DLight Mailer 2.0.",
            "From: DLight Mailer <datahihi1100@gmail.com>");
        return 'Email sent successfully!';
})->name('api.demo.mail');

// Demo Redis Cache
$router->signApi('GET /demo/cache', function () {
    $cache = new \DLight\Application\Drive\Cache\Redis([
        'host'        => '127.0.0.1',
        'port'        => 6379,
        'prefix'      => 'dlight:',
        'default_ttl' => 60,
    ]);

    // set value
    $cache->set('counter', 0);
    // increment
    $cache->increment('counter');
    // read back
    $counter = $cache->get('counter', 0);

    // store complex value
    $cache->set('user:1', ['id' => 1, 'name' => 'Dat']);
    $user = $cache->get('user:1');

    // check exists
    $hasUser = $cache->has('user:1');

    // cleanup example
    $cache->delete('temp');

    return [
        'ok'       => true,
        'counter'  => $counter,
        'user'     => $user,
        'has_user' => $hasUser,
    ];
})->name('api.demo.cache');

