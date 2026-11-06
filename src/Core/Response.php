<?php

declare(strict_types=1);

namespace FlatCms\Core;

/**
 * Holds status, headers and body, and sends them exactly once.
 */
final class Response
{
    /** @var array<string, string> */
    private array $headers = [];

    private bool $sent = false;

    public function __construct(private string $body = '', private int $status = 200)
    {
    }

    public static function html(string $body, int $status = 200): self
    {
        return (new self($body, $status))->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return (new self(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}', $status))
            ->header('Content-Type', 'application/json; charset=UTF-8');
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return (new self('', $status))->header('Location', $url);
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function setBody(string $body): self
    {
        $this->body = $body;

        return $this;
    }

    public function isSent(): bool
    {
        return $this->sent;
    }

    /**
     * Emit status, headers and body. Safe to call once; later calls are ignored.
     */
    public function send(): void
    {
        if ($this->sent) {
            return;
        }
        $this->sent = true;

        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
        }

        echo $this->body;
    }
}
