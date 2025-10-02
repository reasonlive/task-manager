<?php
declare(strict_types=1);
namespace App\Core\Auth;

use App\Core\Env;
use App\Core\Http\Request;
use InvalidArgumentException;
use RuntimeException;

class JwtService
{
    private string $secretKey;
    private string $algorithm;
    private int $expirationTime;
    private int $leeway = 60; // 60 секунд для учета расхождения времени

    public function __construct(
        string $secretKey = 'default_secret_key',
        string $algorithm = 'HS256',
        int $expirationTime = 3600
    ) {
        if (empty($secretKey)) {
            throw new InvalidArgumentException('Secret key cannot be empty');
        }

        $this->secretKey = Env::get('TOKEN_KEY', $secretKey);
        $this->algorithm = $algorithm;
        $this->expirationTime = (int)Env::get('TOKEN_LIFETIME', $expirationTime);

        // Проверяем поддержку алгоритма при создании
        if (!$this->isAlgorithmSupported()) {
            throw new RuntimeException('Unsupported algorithm: ' . $algorithm);
        }
    }

    /**
     * Создание JWT токена
     */
    public function createToken(array $payload): string
    {
        $header = $this->encode(json_encode([
            'typ' => 'JWT',
            'alg' => $this->algorithm
        ]));

        // Добавляем стандартные claims
        $payload['iat'] = time();
        $payload['exp'] = time() + $this->expirationTime;

        $payloadEncoded = $this->encode(json_encode($payload));

        $signature = $this->sign("$header.$payloadEncoded");

        return "$header.$payloadEncoded.$signature";
    }

    /**
     * Валидация и декодирование JWT токена
     */
    public function validateToken(string $token): array
    {
        // Базовая проверка формата
        if (empty($token)) {
            throw new InvalidArgumentException('Token cannot be empty');
        }

        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new InvalidArgumentException('Invalid token format');
        }

        [$header, $payload, $signature] = $parts;

        // Декодируем header для проверки алгоритма
        $decodedHeader = $this->decode($header);
        if (!isset($decodedHeader['alg']) || $decodedHeader['alg'] !== $this->algorithm) {
            throw new RuntimeException('Invalid algorithm');
        }

        // Проверяем подпись
        if (!$this->verifySignature("$header.$payload", $signature)) {
            throw new RuntimeException('Invalid token signature');
        }

        $decodedPayload = $this->decode($payload);
        $currentTime = time();

        // Проверяем expiration time
        if (isset($decodedPayload['exp']) && $decodedPayload['exp'] < ($currentTime - $this->leeway)) {
            throw new RuntimeException('Token has expired');
        }

        // Проверяем issued at time
        if (isset($decodedPayload['iat']) && $decodedPayload['iat'] > ($currentTime + $this->leeway)) {
            throw new RuntimeException('Invalid token issue time');
        }

        // Проверяем not before time если установлен
        if (isset($decodedPayload['nbf']) && $decodedPayload['nbf'] > ($currentTime + $this->leeway)) {
            throw new RuntimeException('Token not yet valid');
        }

        return $decodedPayload;
    }

    /**
     * Обновление токена
     */
    public function refreshToken(string $token): string
    {
        $payload = $this->validateToken($token);

        // Удаляем стандартные claims для создания нового токена
        unset($payload['iat'], $payload['exp'], $payload['nbf']);

        return $this->createToken($payload);
    }

    /**
     * Получение payload из токена без валидации
     * ВНИМАНИЕ: Используйте только для отладочных целей!
     */
    public function getPayload(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new InvalidArgumentException('Invalid token format');
        }

        return $this->decode($parts[1]);
    }

    /**
     * Кодирование данных в base64url
     */
    private function encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Декодирование данных из base64url
     */
    private function decode(string $data): array
    {
        $decoded = base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '='));

        if ($decoded === false) {
            throw new RuntimeException('Ошибка декодирования base64');
        }

        $result = json_decode($decoded, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Ошибка декодирования JSON: ' . json_last_error_msg());
        }

        return $result;
    }

    /**
     * Создание подписи
     */
    private function sign(string $data): string
    {
        $hash = hash_hmac(
            $this->getHashAlgorithm(),
            $data,
            $this->secretKey,
            true
        );

        if ($hash === false) {
            throw new RuntimeException('Ошибка создания подписи');
        }

        return $this->encode($hash);
    }

    /**
     * Проверка подписи
     */
    private function verifySignature(string $data, string $signature): bool
    {
        $expectedSignature = $this->sign($data);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Получение алгоритма хеширования
     */
    private function getHashAlgorithm(): string
    {
        $algorithms = [
            'HS256' => 'sha256',
            'HS384' => 'sha384',
            'HS512' => 'sha512'
        ];

        if (!isset($algorithms[$this->algorithm])) {
            throw new RuntimeException('Неподдерживаемый алгоритм: ' . $this->algorithm);
        }

        return $algorithms[$this->algorithm];
    }

    /**
     * Проверка возможности использования алгоритма
     */
    public function isAlgorithmSupported(): bool
    {
        try {
            $this->getHashAlgorithm();
            return true;
        } catch (RuntimeException $e) {
            return false;
        }
    }

    public function extractTokenFromRequest(Request $request): ?string
    {
        $authorizationHeader = $request->getAuthorizationHeader();

        if (!$authorizationHeader) {
            return null;
        }

        // Поддерживаем форматы: "Bearer {token}" или просто "{token}"
        if (preg_match('/Bearer\s+(.*)$/i', $authorizationHeader, $matches)) {
            return trim($matches[1]);
        }

        return trim($authorizationHeader);
    }
}