<?php

namespace DLight\Application;

use function call_user_func_array;
use function chr;
use function in_array;
use function is_callable;
use function ord;
use function strlen;

/**
 * **WebSocket Server**
 * 
 * A simple WebSocket server implementation in PHP using low-level sockets.
 * Handles handshake, frame parsing (RFC 6455), and event-based messaging.
 */
class WebSocket
{
    protected \Socket $master;
    protected array $clients = [];

    /** @var callable|null */
    public $onOpen;
    /** @var callable|null */
    public $onMessage;
    /** @var callable|null */
    public $onClose;

    /**
     * Constructor to initialize the WebSocket server.
     *
     * @param string $host The host to bind the server to (default: '0.0.0.0')
     * @param int $port The port to bind the server to (default: 9501)
     */
    public function __construct(protected string $host = '0.0.0.0', protected int $port = 9501)
    {
        if (php_sapi_name() !== 'cli') {
            die(cli_red("WebSocket server can only be run from the command line."));
        }
        if(!extension_loaded('sockets')) {
            die(cli_red("The sockets extension is required to run the WebSocket server."));
        }
    }

    /**
     * Start the WebSocket server.
     */
    public function start(): void
    {
        $this->master = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($this->master, SOL_SOCKET, SO_REUSEADDR, 1);
        
        if (!@socket_bind($this->master, $this->host, $this->port)) {
            $err = socket_last_error($this->master);
            $this->logSocketError('Bind failed', $err);
            exit;
        }

        socket_listen($this->master);

        if (PHP_SAPI === 'cli') {
            echo "WebSocket server started at ws://{$this->host}:{$this->port}\n";
        }

        $this->loop();
    }

    /**
     * Main server loop using socket_select for efficient I/O.
     * @return never
     */
    private function loop()
    {
        while (true) {
            $read = array_merge([$this->master], $this->clients);
            $write = $except = [];

            // Wait for activity on any socket
            if (@socket_select($read, $write, $except, null) < 1) {
                continue;
            }

            // New connection request
            if (in_array($this->master, $read, true)) {
                $client = socket_accept($this->master);
                if ($client) {
                    if ($this->handshake($client)) {
                        $this->clients[spl_object_id($client)] = $client;
                        $this->triggerEvent('onOpen', [$client]);
                    } else {
                        @socket_close($client);
                    }
                }
                unset($read[array_search($this->master, $read, true)]);
            }

            // Handle client messages
            foreach ($read as $client) {
                $frame = $this->readFrame($client);

                if ($frame === null) {
                    $this->disconnect($client);
                    continue;
                }

                switch ($frame['type']) {
                    case 'text':
                        $this->triggerEvent('onMessage', [$client, $frame['payload']]);
                        break;
                    case 'ping':
                        $this->sendPong($client);
                        break;
                    case 'close':
                        $this->disconnect($client);
                        break;
                }
            }
        }
    }

    // ─────────────────────────────────────────────
    // RFC 6455 HANDSHAKE
    // ─────────────────────────────────────────────

    /**
     * Perform the WebSocket handshake with a new client.
     *
     * @param \Socket $client The client socket to perform the handshake with.
     * @return bool True if the handshake was successful, false otherwise.
     */
    private function handshake(\Socket $client): bool
    {
        $request = socket_read($client, 8192);
        if (!$request) {
            return false;
        }

        if (!preg_match("/Sec-WebSocket-Key:\s*(.+)\r\n/i", $request, $match)) {
            return false;
        }

        $key = trim($match[1]);
        $accept = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));

        $header = "HTTP/1.1 101 Switching Protocols\r\n" .
                  "Upgrade: websocket\r\n" .
                  "Connection: Upgrade\r\n" .
                  "Sec-WebSocket-Accept: $accept\r\n\r\n";

        return @socket_write($client, $header) !== false;
    }

    // ─────────────────────────────────────────────
    // FRAME PARSING
    // ─────────────────────────────────────────────
    /**
     * Read exactly $length bytes from a socket (TCP may deliver partial reads).
     *
     * @return string|null Full buffer or null on EOF/error.
     */
    private function readBytes(\Socket $client, int $length): ?string
    {
        if ($length < 0) {
            return null;
        }
        if ($length === 0) {
            return '';
        }
        $buffer = '';
        while (strlen($buffer) < $length) {
            $chunk = @socket_read($client, $length - strlen($buffer), PHP_BINARY_READ);
            if ($chunk === false || $chunk === '') {
                return null;
            }
            $buffer .= $chunk;
        }
        return $buffer;
    }

    /**
     * Read and parse a WebSocket frame from a client.
     *
     * @param \Socket $client The client socket to read from.
     * @return array|null An associative array with 'type' and 'payload' keys, or null on failure.
     */
    private function readFrame(\Socket $client): ?array
    {
        $header = $this->readBytes($client, 2);
        if ($header === null) {
            return null;
        }

        $byte1 = ord($header[0]);
        $byte2 = ord($header[1]);

        $opcode = $byte1 & 0x0F;
        $masked = ($byte2 & 0x80) !== 0;
        $len = $byte2 & 0x7F;

        if (!$masked) {
            return null;
        } // Specification requires client frames to be masked

        // Handle extended payload length
        if ($len === 126) {
            $ext = $this->readBytes($client, 2);
            if ($ext === null) {
                return null;
            }
            $len = unpack('n', $ext)[1];
        } elseif ($len === 127) {
            $ext = $this->readBytes($client, 8);
            if ($ext === null) {
                return null;
            }
            $len = unpack('J', $ext)[1];
        }

        // Read masking key (4 bytes)
        $mask = $this->readBytes($client, 4);
        if ($mask === null) {
            return null;
        }

        $payload = $this->readBytes($client, $len);
        if ($payload === null) {
            return null;
        }

        // Unmask payload
        $decoded = '';
        for ($i = 0; $i < $len; $i++) {
            $decoded .= $payload[$i] ^ $mask[$i % 4];
        }

        return match ($opcode) {
            0x1 => ['type' => 'text', 'payload' => $decoded],
            0x8 => ['type' => 'close'],
            0x9 => ['type' => 'ping'],
            0xA => ['type' => 'pong'],
            default => null,
        };
    }

    // ─────────────────────────────────────────────
    // COMMUNICATION METHODS
    // ─────────────────────────────────────────────

    /**
     * Send a text message to a client.
     *
     * @param \Socket $client The client socket to send the message to.
     * @param string $message The message to send.
     * @return bool True on success, false on failure.
     */
    public function send(\Socket $client, string $message): bool
    {
        $frame = chr(0x81); // Final fragment, text frame
        $len = strlen($message);

        if ($len <= 125) {
            $frame .= chr($len);
        } elseif ($len <= 65535) {
            $frame .= chr(126) . pack('n', $len);
        } else {
            $frame .= chr(127) . pack('J', $len);
        }

        $data = $frame . $message;
        return @socket_write($client, $data, strlen($data)) !== false;
    }

    /**
     * Send a Pong frame in response to a Ping.
     *
     * @param \Socket $client The client socket to send the Pong to.
     */
    private function sendPong(\Socket $client): void
    {
        @socket_write($client, chr(0x8A) . chr(0x00));
    }

    /**
     * Send an RFC 6455 Close frame (server → client: unmasked).
     * Without this, clients that sent Close often report code 1006 / wasClean false.
     */
    private function sendCloseFrame(\Socket $client, int $code = 1000, string $reason = ''): void
    {
        $payload = pack('n', $code) . $reason;
        if (strlen($payload) > 125) {
            $payload = pack('n', $code) . substr($reason, 0, 125 - 2);
        }
        if (strlen($payload) < 2) {
            $payload = pack('n', 1000);
        }
        $len = strlen($payload);
        $frame = chr(0x88) . chr($len) . $payload;
        @socket_write($client, $frame, strlen($frame));
    }

    // ─────────────────────────────────────────────
    // EVENT TRIGGERING AND UTILITY METHODS
    // ─────────────────────────────────────────────
    /**
     * Trigger an event callback if it is set.
     *
     * @param string $event The name of the event ('onOpen', 'onMessage', 'onClose').
     * @param array $args The arguments to pass to the event callback.
     */
    private function triggerEvent(string $event, array $args): void
    {
        // Public $onOpen / $onMessage / $onClose shadow methods of the same name; reading
        // $this->$event only sees the property. Prefer an assigned callable, else invoke method.
        $handler = match ($event) {
            'onOpen' => $this->onOpen,
            'onMessage' => $this->onMessage,
            'onClose' => $this->onClose,
            default => null,
        };

        if ($handler !== null && is_callable($handler)) {
            call_user_func_array($handler, $args);

            return;
        }

        if (is_callable([$this, $event])) {
            call_user_func_array([$this, $event], $args);
        }
    }

    private function logSocketError(string $context, int $errno): void
    {
        $msg = socket_strerror($errno);
        echo "[Error] $context: ($errno) $msg\n";
    }

    /**
     * Broadcast a message to all connected clients except the sender.
     *
     * @param string $message The message to broadcast.
     * @param \Socket|null $except The client socket to exclude from broadcasting.
     */
    protected function broadcast(string $message, ?\Socket $except = null): void
    {
        $exceptId = $except instanceof \Socket ? spl_object_id($except) : null;

        foreach ($this->clients as $client) {
            $clientId = spl_object_id($client);
            if ($clientId !== $exceptId) {
                $this->send($client, $message);
            }
        }
    }

    /**
     * Disconnect a client and clean up resources.
     *
     * @param \Socket $client The client socket to disconnect.
     */
    private function disconnect(\Socket $client): void
    {
        $this->triggerEvent('onClose', [$client]);
        $this->sendCloseFrame($client);
        unset($this->clients[spl_object_id($client)]);
        @socket_close($client);
    }

    /**
     * Handle new client connection.
     *
     * @param \Socket $client The client socket.
     */
    protected function onOpen(\Socket $client): void
    {
        echo "Client connected\n";
    }

    /**
     * Handle incoming message from a client.
     *
     * @param \Socket $client The client socket.
     * @param string $message The received message.
     */
    protected function onMessage(\Socket $client, string $message): void
    {
        $this->broadcast("Client says: $message", $client);
    }

    /**
     * Handle client disconnection.
     *
     * @param \Socket $client The client socket.
     */
    protected function onClose(\Socket $client): void
    {
        echo "Client disconnected\n";
    }
}
