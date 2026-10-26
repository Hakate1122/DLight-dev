<?php

namespace DLight\Application;

use DLight\Application\View;

use function is_bool;
use function is_array;
use function in_array;

/**
 * **Secure SMTP Mailer**
 *
 * Secure Mail class supporting multiple SMTP providers, CC, BCC, and attachments.
 */
class Mail
{
    private $smtp_host = "localhost";
    private $smtp_port = 587;
    private $smtp_encryption = 'tls';
    private $smtp_auth = true;
    private $smtp_timeout = 10;
    private $ehlo_domain = 'localhost';
    private $username;
    private $password;
    private $from;
    private $from_name;

    private $to = [];
    private $cc = [];
    private $bcc = [];
    
    private $subject;
    private $body;
    private $attachments = [];
    private const SERVICE_PRESETS = [
        'gmail' => ['host' => 'smtp.gmail.com', 'port' => 587, 'encryption' => 'tls', 'auth' => true],
        'google' => ['host' => 'smtp.gmail.com', 'port' => 587, 'encryption' => 'tls', 'auth' => true],
        'googlemail' => ['host' => 'smtp.gmail.com', 'port' => 587, 'encryption' => 'tls', 'auth' => true],
        'outlook' => ['host' => 'smtp.office365.com', 'port' => 587, 'encryption' => 'tls', 'auth' => true],
        'office365' => ['host' => 'smtp.office365.com', 'port' => 587, 'encryption' => 'tls', 'auth' => true],
        'hotmail' => ['host' => 'smtp.office365.com', 'port' => 587, 'encryption' => 'tls', 'auth' => true],
        'yahoo' => ['host' => 'smtp.mail.yahoo.com', 'port' => 587, 'encryption' => 'tls', 'auth' => true],
        'zoho' => ['host' => 'smtp.zoho.com', 'port' => 587, 'encryption' => 'tls', 'auth' => true],
        'mailgun' => ['host' => 'smtp.mailgun.org', 'port' => 587, 'encryption' => 'tls', 'auth' => true],
        'sendgrid' => ['host' => 'smtp.sendgrid.net', 'port' => 587, 'encryption' => 'tls', 'auth' => true],
        'ses' => ['host' => 'email-smtp.us-east-1.amazonaws.com', 'port' => 587, 'encryption' => 'tls', 'auth' => true],
        'aws' => ['host' => 'email-smtp.us-east-1.amazonaws.com', 'port' => 587, 'encryption' => 'tls', 'auth' => true],
    ];

    /**
     * Constructor to initialize SMTP settings. If no config is provided, it will attempt to load from environment variables by TinyEnv or from a config file at ROOT_DIR/config/mail.php. The config array can contain keys like host, port, username, password, from, and fromname.
     * 
     * @param array|null $config Optional configuration array with keys:
     *                           - host: SMTP server host
     *                           - port: SMTP server port
     *                           - username: SMTP username
     *                           - password: SMTP password
     *                           - from: From email address
     *                           - fromname: From name
     */
    public function __construct(?array $config = null)
    {
        if ($config === null && file_exists(ROOT_DIR . '/config/mail.php')) {
            $config = include_once ROOT_DIR . '/config/mail.php';
        }
        $config = is_array($config) ? $config : [];

        $service = strtolower(trim((string) (env('MAIL_SERVICE') ?? $config['service'] ?? '')));
        if ($service !== '' && isset(self::SERVICE_PRESETS[$service])) {
            $preset = self::SERVICE_PRESETS[$service];
            $this->smtp_host = $preset['host'];
            $this->smtp_port = $preset['port'];
            $this->smtp_encryption = $preset['encryption'];
            $this->smtp_auth = $preset['auth'];
        }

        $this->smtp_host = env('MAIL_HOST') ?? $config['host'] ?? $this->smtp_host;
        $this->smtp_port = (int) (env('MAIL_PORT') ?? $config['port'] ?? $this->smtp_port);
        $this->smtp_encryption = strtolower((string) (env('MAIL_ENCRYPTION') ?? $config['encryption'] ?? $this->smtp_encryption));
        $this->smtp_auth = $this->toBool(env('MAIL_AUTH') ?? $config['auth'] ?? $this->smtp_auth);
        $this->smtp_timeout = (int) (env('MAIL_TIMEOUT') ?? $config['timeout'] ?? $this->smtp_timeout);
        $this->ehlo_domain = (string) (env('MAIL_EHLO_DOMAIN') ?? $config['ehlo_domain'] ?? gethostname() ?: 'localhost');

        $this->username   = env('MAIL_USERNAME') ?? $config['username'] ?? '';
        $this->password   = env('MAIL_PASSWORD') ?? $config['password'] ?? '';
        $this->from       = env('MAIL_FROM_ADDRESS') ?? $config['from'] ?? $this->username;
        $this->from_name  = env('MAIL_FROM_NAME') ?? $config['fromname'] ?? "No-Reply";
    }

    private function toBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value === 1;
        }
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Sanitize input to prevent Header Injection
     * 
     * @param string $string The input string to sanitize
     * @return string The sanitized string
     */
    private function sanitize(string $string): string
    {
        return str_replace(["\r", "\n"], "", trim($string));
    }

    /* ----- SMTP Communication Helpers ----- */

    /**
     * Send a line to the SMTP server
     * 
     * @param resource $fp The file pointer to the SMTP connection
     * @param string $line The line to send
     */
    private function sendLine($fp, string $line): void
    {
        fwrite($fp, $line . "\r\n");
    }

    /**
     * Read a line from the SMTP server
     * 
     * @param resource $fp The file pointer to the SMTP connection
     * @return string The line read from the server
     * @throws \RuntimeException if reading fails
     */
    private function getLine($fp): string
    {
        $line = fgets($fp, 515);
        if ($line === false) {
            throw new \RuntimeException("SMTP read failed");
        }
        return $line;
    }

    /**
     * Read multiline response from the SMTP server until the last line is reached
     * 
     * @param resource $fp The file pointer to the SMTP connection
     */
    private function getMultiline($fp): void
    {
        while (($line = fgets($fp, 515)) !== false) {
            if (substr($line, 3, 1) !== '-') {
                break;
            }
        }
    }

    /* ----- Recipient Methods ----- */

    /**
     * Add a recipient email address
     * 
     * @param string $email The recipient email address to add
     * @return self Returns the Mail instance for method chaining
     */
    public function to(string $email): self
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->to[] = $email;
        }
        return $this;
    }

    /**
     * Add a CC email address
     * 
     * @param string $email The CC email address to add
     * @return self Returns the Mail instance for method chaining
     */
    public function cc(string $email): self
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->cc[] = $email;
        }
        return $this;
    }

    /**
     * Add a BCC email address
     * 
     * @param string $email The BCC email address to add
     * @return self Returns the Mail instance for method chaining
     */
    public function bcc(string $email): self
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->bcc[] = $email;
        }
        return $this;
    }

    /* ----- Email Content Methods ----- */

    /**
     * Set the email subject
     * 
     * @param string $subject The subject of the email
     * @return self Returns the Mail instance for method chaining
     */
    public function subject(string $subject): self
    {
        $this->subject = $this->sanitize($subject);
        return $this;
    }

    /**
     * Set the email body (HTML)
     * 
     * @param string $body The HTML content of the email body
     * @return self Returns the Mail instance for method chaining
     */
    public function body(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    /**
     * Add an attachment file path with optional original filename.
     * Accepts either a single string (file path) or a file path plus a
     * user-provided filename (useful for uploaded files where tmp_name differs).
     * 
     * @param string $filePath The file path of the attachment
     * @param string|null $fileName Optional original filename to use in the email
     * @return self Returns the Mail instance for method chaining
     */
    public function addAttachment(string $filePath, ?string $fileName = null): self
    {
        if (file_exists($filePath)) {
            $this->attachments[] = [
                'path' => $filePath,
                'name' => $fileName ?? basename($filePath),
            ];
        }
        return $this;
    }

    /**
     * Set the email body as HTML
     * 
     * @param string $html The HTML content to set as the email body
     * @return self Returns the Mail instance for method chaining
     */
    public function html(string $html): self
    {
        $this->body = $html;
        return $this;
    }

    /**
     * Set the email body as plain text (converted to HTML)
     * 
     * @param string $text The plain text content to set as the email body
     * @return self Returns the Mail instance for method chaining
     */
    public function text(string $text): self
    {
        $this->body = nl2br(htmlspecialchars($text));
        return $this;
    }

    /**
     * Set the email body from a view template
     * 
     * @param string $viewName The name of the view template to render
     * @param array|null $data Optional data to pass to the view for rendering
     * @return self Returns the Mail instance for method chaining
     */
    public function view(string $viewName, ?array $data = null): self
    {
        $view = new View();
        $this->body = $view->render($viewName, $data);
        return $this;
    }

    /* ----- Send Email ----- */

    /**
     * Send the email via SMTP
     * 
     * @return bool True on success
     * @throws \RuntimeException on failure
     */
    public function send(): bool
    {
        if ($this->smtp_auth && (empty($this->username) || empty($this->password))) {
            throw new \RuntimeException("SMTP credentials not configured.");
        }

        // 1. Setup Secure Context (Anti-MITM)
        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => true,
                'verify_peer_name'  => true,
                'allow_self_signed' => false
            ]
        ]);

        $errno = $errstr = null;
        $transport = $this->smtp_encryption === 'ssl' ? 'ssl' : 'tcp';
        $fp = @stream_socket_client(
            "{$transport}://{$this->smtp_host}:{$this->smtp_port}",
            $errno, $errstr, $this->smtp_timeout, STREAM_CLIENT_CONNECT, $context
        );

        if (!$fp) {
            // Log error securely instead of showing to user
            throw new \RuntimeException("Could not connect to Mail Server: $errstr ($errno).");
        }

        // 2. Handshake & Auth
        $this->getLine($fp);
        $this->sendLine($fp, "EHLO " . $this->ehlo_domain);
        $this->getMultiline($fp);

        // STARTTLS for explicit TLS mode
        if ($this->smtp_encryption === 'tls') {
            $this->sendLine($fp, "STARTTLS");
            $tlsResp = $this->getLine($fp);
            if (!str_contains($tlsResp, '220')) {
                throw new \RuntimeException("Server does not support STARTTLS.");
            }
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException("TLS negotiation failed.");
            }

            $this->sendLine($fp, "EHLO " . $this->ehlo_domain);
            $this->getMultiline($fp);
        }

        // AUTH
        if ($this->smtp_auth) {
            $this->sendLine($fp, "AUTH LOGIN");
            $this->getLine($fp);
            $this->sendLine($fp, base64_encode($this->username));
            $this->getLine($fp);
            $this->sendLine($fp, base64_encode($this->password));
            $resp = $this->getLine($fp);

            if (!str_contains($resp, '235')) {
                throw new \RuntimeException("SMTP authentication failed. Check username/password.");
            }
        }

        // 3. Send Recipients (To + CC + BCC)
        // MAIL FROM
        $this->sendLine($fp, "MAIL FROM:<{$this->from}>");
        $this->getLine($fp);

        // RCPT TO (Send to everyone, but BCC is hidden in header later)
        $allRecipients = array_merge($this->to, $this->cc, $this->bcc);
        if ($allRecipients === []) {
            throw new \RuntimeException("No recipients specified.");
        }

        foreach ($allRecipients as $rcpt) {
            $this->sendLine($fp, "RCPT TO:<$rcpt>");
            $this->getLine($fp);
        }

        // 4. Build Payload (Data)
        $this->sendLine($fp, "DATA");
        $this->getLine($fp);

        // Generate a unique boundary for multipart
        $boundary = "dlight_" . md5(uniqid(time()));

        // --- Headers ---
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Date: " . date("r") . "\r\n";
        $headers .= "From: " . $this->sanitize($this->from_name) . " <{$this->from}>\r\n";
        $headers .= "Subject: {$this->subject}\r\n";
        
        // Visible Recipients in Header
        if (!empty($this->to)) {
            $headers .= "To: " . implode(", ", $this->to) . "\r\n";
        }
        if (!empty($this->cc)) {
            $headers .= "Cc: " . implode(", ", $this->cc) . "\r\n";
        }
        // BCC header is intentionally OMITTED for privacy

        // Content-Type for Mixed (Body + Attachments)
        $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";
        $headers .= "\r\n"; // End of main headers

        fwrite($fp, $headers);

        // --- Body Part ---
        $message  = "--{$boundary}\r\n";
        $message .= "Content-Type: text/html; charset=\"UTF-8\"\r\n";
        $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $message .= $this->body . "\r\n\r\n";
        fwrite($fp, $message);

        // --- Attachment Parts ---
        foreach ($this->attachments as $attEntry) {
            // Support both new array format and legacy string path
            if (is_array($attEntry)) {
                $filePath = $attEntry['path'];
                $fileName = $attEntry['name'];
            } else {
                $filePath = $attEntry;
                $fileName = basename($filePath);
            }

            if (file_exists($filePath)) {
                $fileData = chunk_split(base64_encode(file_get_contents($filePath)));

                $att  = "--{$boundary}\r\n";
                $att .= "Content-Type: application/octet-stream; name=\"{$fileName}\"\r\n";
                $att .= "Content-Disposition: attachment; filename=\"{$fileName}\"\r\n";
                $att .= "Content-Transfer-Encoding: base64\r\n\r\n";
                $att .= $fileData . "\r\n\r\n";

                fwrite($fp, $att);
            }
        }

        // End of Data
        fwrite($fp, "--{$boundary}--\r\n");
        fwrite($fp, ".\r\n");
        $this->getLine($fp);

        // Quit
        $this->sendLine($fp, "QUIT");
        fclose($fp);

        return true;
    }
}