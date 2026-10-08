<?php

namespace App\Http;

class Request
{
    protected array $data = [];
    protected array $headers = [];

    public function __construct(array $query = [], array $request = [], array $attributes = [], array $cookies = [], array $files = [], array $server = [], $content = null)
    {
        $raw = file_get_contents('php://input');
        if (empty($raw) && php_sapi_name() === 'cli') {
            $raw = @file_get_contents('php://stdin');
        }
        $json = json_decode($raw, true);
        $this->data = array_merge($_GET, $_POST, is_array($json) ? $json : []);
        $this->headers = function_exists('getallheaders') ? (getallheaders() ?: []) : [];
    }

    public static function capture(): self
    {
        return new self();
    }

    public function all(?array $keys = null): array
    {
        if ($keys === null) return $this->data;
        return array_intersect_key($this->data, array_flip($keys));
    }

    public function input(?string $key = null, $default = null)
    {
        if ($key === null) return $this->data;
        return $this->data[$key] ?? $default;
    }

    public function get(?string $key = null, $default = null)
    {
        return $this->input($key, $default);
    }

    public function query(?string $key = null, $default = null)
    {
        if ($key === null) return $_GET;
        return $_GET[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function filled(string $key): bool
    {
        return !empty($this->data[$key]);
    }

    public function only($keys): array
    {
        $keys = is_array($keys) ? $keys : func_get_args();
        return array_intersect_key($this->data, array_flip($keys));
    }

    public function except($keys): array
    {
        $keys = is_array($keys) ? $keys : func_get_args();
        return array_diff_key($this->data, array_flip($keys));
    }

    public function header(?string $key = null, $default = null)
    {
        if ($key === null) return $this->headers;
        foreach ($this->headers as $k => $v) {
            if (strtolower($k) === strtolower($key)) return $v;
        }
        return $default;
    }

    public function bearerToken(): ?string
    {
        $auth = $this->header('Authorization') ?: $this->header('authorization');
        if ($auth && preg_match('/Bearer\s+(\S+)/i', $auth, $m)) {
            return $m[1];
        }
        return null;
    }

    public function method(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    public function file(string $key)
    {
        return $_FILES[$key] ?? null;
    }

    public function hasFile(string $key): bool
    {
        return isset($_FILES[$key]) && $_FILES[$key]['error'] === UPLOAD_ERR_OK;
    }

    public function isMethod(string $method): bool
    {
        return strtoupper($this->method()) === strtoupper($method);
    }
}

class JsonResponse
{
    public function __construct(public mixed $data = null, public int $status = 200, public array $headers = [])
    {
    }
}
