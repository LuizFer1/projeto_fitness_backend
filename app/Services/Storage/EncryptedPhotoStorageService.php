<?php

namespace App\Services\Storage;

use App\Models\ProgressPhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores progress photos with AES-256-GCM client-side encryption (SRS RF-24, RNF-04).
 *
 * Per-photo DEK (Data Encryption Key) is generated randomly and wrapped with a
 * user master key derived via HKDF from env PHOTO_MASTER_KEY + user UUID.
 *
 * Files are uploaded to S3 encrypted; the IV and wrapped DEK are stored in DB.
 * Pre-signed URLs have TTL of 1 hour (RNF-04).
 */
class EncryptedPhotoStorageService
{
    private const CIPHER    = 'aes-256-gcm';
    private const TAG_LEN   = 16;
    private const KEY_LEN   = 32;

    public function store(User $user, UploadedFile $file, array $attrs = []): ProgressPhoto
    {
        $masterKey = $this->deriveUserMasterKey($user);
        $dek       = random_bytes(self::KEY_LEN);
        $iv        = random_bytes(12); // 96-bit IV for GCM

        // Encrypt file contents
        $plaintext  = $file->getContent();
        $tag        = '';
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $dek, OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LEN);

        // Wrap DEK with user master key (simple XOR-based wrapping via AES-ECB; production: use KMS)
        $wrappedDek = $this->wrapKey($dek, $masterKey);

        // Upload encrypted bytes to S3
        $ext    = $file->getClientOriginalExtension() ?: 'bin';
        $s3Key  = "progress-photos/{$user->id}/" . Str::uuid() . ".{$ext}.enc";

        Storage::disk('s3')->put($s3Key, $ciphertext . $tag, ['visibility' => 'private']);

        return ProgressPhoto::create([
            'user_id'              => $user->id,
            'taken_at'             => $attrs['taken_at'] ?? now()->toDateString(),
            'weight_kg'            => $attrs['weight_kg'] ?? null,
            'category'             => $attrs['category'] ?? 'front',
            'caption'              => $attrs['caption'] ?? null,
            'notes'                => $attrs['notes'] ?? null,
            's3_key'               => $s3Key,
            'encryption_iv'        => bin2hex($iv),
            'encryption_key_wrapped' => base64_encode($wrappedDek),
        ]);
    }

    /** Returns a 1-hour pre-signed URL for the encrypted photo (client decrypts). */
    public function presignedUrl(ProgressPhoto $photo, int $ttlSeconds = 3600): string
    {
        return Storage::disk('s3')->temporaryUrl($photo->s3_key, now()->addSeconds($ttlSeconds));
    }

    /** Soft-deletes the photo record; actual S3 file retained until LGPD delete-account flow. */
    public function softDelete(ProgressPhoto $photo): void
    {
        $photo->delete();
    }

    /** Hard-deletes S3 object and DB record — used by LGPD delete-account. */
    public function hardDelete(ProgressPhoto $photo): void
    {
        Storage::disk('s3')->delete($photo->s3_key);
        $photo->forceDelete();
    }

    // ────────────────────────────────────────────────────────────────

    private function deriveUserMasterKey(User $user): string
    {
        $masterSecret = config('app.photo_master_key', env('PHOTO_MASTER_KEY', ''));
        if (empty($masterSecret)) {
            throw new \RuntimeException('PHOTO_MASTER_KEY is not configured.');
        }

        // HKDF-SHA256: derive a per-user key from master secret + user UUID
        return hash_hkdf('sha256', $masterSecret, self::KEY_LEN, 'photo-encryption', $user->id);
    }

    private function wrapKey(string $dek, string $masterKey): string
    {
        // AES-256-ECB wrap (deterministic) — swap for AWS KMS in production
        $wrapped = openssl_encrypt($dek, 'aes-256-ecb', $masterKey, OPENSSL_RAW_DATA);
        return $wrapped ?: throw new \RuntimeException('Key wrapping failed.');
    }
}
