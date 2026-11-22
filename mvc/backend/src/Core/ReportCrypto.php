<?php
declare(strict_types=1);
namespace Demo\Core;

use RuntimeException;

/**
 * Cifrado autenticado del JSON técnico de informes (AES-256-GCM).
 *
 * La clave es privada del servidor: data_encryption_key = base64(random_bytes(32)).
 * El contexto autentica el identificador del informe, evitando intercambios de filas.
 * No modifica la representación JSON antes de calcular el hash de revisión.
 */
final class ReportCrypto {
    private const PREFIX = 'enc:v1:';
    private string $key;

    public function __construct(string $base64Key) {
        if (!function_exists('openssl_encrypt') || !function_exists('openssl_decrypt')) {
            throw new RuntimeException('El servidor no dispone de OpenSSL para cifrar informes');
        }
        $key = base64_decode($base64Key, true);
        if ($key === false || strlen($key) !== 32 || base64_encode($key) !== $base64Key) {
            throw new RuntimeException('Falta la clave privada de cifrado de informes o su formato es inválido');
        }
        $this->key = $key;
    }

    public static function isEncrypted(string $stored): bool {
        return str_starts_with($stored, self::PREFIX);
    }

    public function encrypt(int $reportId, string $json): string {
        if ($reportId < 1) throw new RuntimeException('ID del informe inválido');
        json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $json, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA,
            $nonce, $tag, $this->aad($reportId), 16
        );
        if ($ciphertext === false || strlen($tag) !== 16) {
            throw new RuntimeException('No se pudo cifrar el informe');
        }
        return self::PREFIX . base64_encode($nonce . $tag . $ciphertext);
    }

    /**
     * El texto claro heredado SOLO puede leerse durante una migración explícita
     * (allowLegacy=true); nunca se utiliza para nuevos guardados.
     */
    public function decrypt(int $reportId, string $stored, bool $allowLegacy=false): string {
        if ($reportId < 1) throw new RuntimeException('ID del informe inválido');
        if (!self::isEncrypted($stored)) {
            if (!$allowLegacy) throw new RuntimeException('El informe no está cifrado: migración pendiente');
            json_decode($stored, true, 512, JSON_THROW_ON_ERROR);
            return $stored;
        }
        $encoded = substr($stored, strlen(self::PREFIX));
        $packed = base64_decode($encoded, true);
        if ($packed === false || strlen($packed) < 29 || base64_encode($packed) !== $encoded) {
            throw new RuntimeException('El registro cifrado está dañado');
        }
        $nonce = substr($packed, 0, 12);
        $tag = substr($packed, 12, 16);
        $ciphertext = substr($packed, 28);
        $plaintext = openssl_decrypt(
            $ciphertext, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA,
            $nonce, $tag, $this->aad($reportId)
        );
        if (!is_string($plaintext)) throw new RuntimeException('No se pudo autenticar el informe cifrado');
        json_decode($plaintext, true, 512, JSON_THROW_ON_ERROR);
        return $plaintext;
    }

    private function aad(int $reportId): string {
        return 'controlab:informe_datos:v1:' . $reportId;
    }
}
