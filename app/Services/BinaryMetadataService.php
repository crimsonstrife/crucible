<?php

namespace App\Services;

use App\Support\MimeDetector;

/**
 * Extracts metadata from binary file contents for display in diff views.
 *
 * Supports:
 * - Images: dimensions, color depth, format
 * - Audio: duration, sample rate, channels (basic header parsing)
 * - Unreal assets: class name, engine version (header parsing)
 */
class BinaryMetadataService
{
    /**
     * Extract metadata from raw file contents.
     *
     * @return array<string, mixed>|null  Metadata array or null if unsupported.
     */
    public function extract(string $filePath, string $contents): ?array
    {
        $category = MimeDetector::diffCategory($filePath);

        return match ($category) {
            'image'        => $this->extractImageMetadata($filePath, $contents),
            'audio'        => $this->extractAudioMetadata($filePath, $contents),
            'video'        => $this->extractVideoMetadata($filePath, $contents),
            'engine_asset' => $this->extractEngineAssetMetadata($filePath, $contents),
            'model3d'      => $this->extractModelMetadata($filePath, $contents),
            default        => $this->extractGenericMetadata($filePath, $contents),
        };
    }

    /**
     * Extract image metadata using GD/getimagesizefromstring.
     */
    private function extractImageMetadata(string $filePath, string $contents): ?array
    {
        $meta = [
            'type'      => 'image',
            'extension' => strtolower(pathinfo($filePath, PATHINFO_EXTENSION)),
            'size'      => strlen($contents),
            'mime_type' => MimeDetector::mimeFromExtension($filePath),
        ];

        $info = @getimagesizefromstring($contents);

        if ($info !== false) {
            $meta['width']  = $info[0];
            $meta['height'] = $info[1];
            $meta['bits']   = $info['bits'] ?? null;

            $imageTypes = [
                IMAGETYPE_GIF     => 'GIF',
                IMAGETYPE_JPEG    => 'JPEG',
                IMAGETYPE_PNG     => 'PNG',
                IMAGETYPE_BMP     => 'BMP',
                IMAGETYPE_WEBP    => 'WebP',
                IMAGETYPE_ICO     => 'ICO',
            ];
            $meta['format'] = $imageTypes[$info[2]] ?? strtoupper($meta['extension']);
        }

        return $meta;
    }

    /**
     * Extract basic audio metadata from file headers.
     */
    private function extractAudioMetadata(string $filePath, string $contents): ?array
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        $meta = [
            'type'      => 'audio',
            'extension' => $ext,
            'size'      => strlen($contents),
            'mime_type' => MimeDetector::mimeFromExtension($filePath),
        ];

        // WAV header parsing (RIFF format)
        if ($ext === 'wav' && strlen($contents) >= 44 && substr($contents, 0, 4) === 'RIFF') {
            $audioFormat = unpack('v', substr($contents, 20, 2))[1] ?? 0;
            $channels    = unpack('v', substr($contents, 22, 2))[1] ?? 0;
            $sampleRate  = unpack('V', substr($contents, 24, 4))[1] ?? 0;
            $bitsPerSample = unpack('v', substr($contents, 34, 2))[1] ?? 0;
            $dataSize    = unpack('V', substr($contents, 40, 4))[1] ?? 0;

            $meta['channels']        = $channels;
            $meta['sample_rate']     = $sampleRate;
            $meta['bits_per_sample'] = $bitsPerSample;
            $meta['format']          = $audioFormat === 1 ? 'PCM' : "Format #{$audioFormat}";

            if ($sampleRate > 0 && $channels > 0 && $bitsPerSample > 0) {
                $bytesPerSecond = $sampleRate * $channels * ($bitsPerSample / 8);
                if ($bytesPerSecond > 0) {
                    $meta['duration_seconds'] = round($dataSize / $bytesPerSecond, 2);
                }
            }
        }

        // MP3: check for ID3 tag to confirm format
        if ($ext === 'mp3') {
            if (str_starts_with($contents, 'ID3') || (ord($contents[0]) === 0xFF && (ord($contents[1]) & 0xE0) === 0xE0)) {
                $meta['format'] = 'MPEG Audio';
            }
        }

        return $meta;
    }

    /**
     * Extract basic video metadata.
     */
    private function extractVideoMetadata(string $filePath, string $contents): ?array
    {
        return [
            'type'      => 'video',
            'extension' => strtolower(pathinfo($filePath, PATHINFO_EXTENSION)),
            'size'      => strlen($contents),
            'mime_type' => MimeDetector::mimeFromExtension($filePath),
        ];
    }

    /**
     * Extract metadata from Unreal Engine .uasset and .umap files.
     * Parses the binary header for magic number, engine version, and class name.
     */
    private function extractEngineAssetMetadata(string $filePath, string $contents): ?array
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        $meta = [
            'type'      => 'engine_asset',
            'engine'    => $this->detectEngine($ext),
            'extension' => $ext,
            'size'      => strlen($contents),
        ];

        // Unreal .uasset header parsing
        if (in_array($ext, ['uasset', 'umap']) && strlen($contents) >= 28) {
            $magic = unpack('V', substr($contents, 0, 4))[1] ?? 0;

            // Unreal asset magic: 0x9E2A83C1
            if ($magic === 0x9E2A83C1) {
                $meta['valid_header'] = true;

                // Version info at offset 4-8
                $legacyVersion = unpack('l', substr($contents, 4, 4))[1] ?? 0;
                $meta['legacy_version'] = $legacyVersion;

                // File version at offset 12-16
                if (strlen($contents) >= 16) {
                    $fileVersionUE4 = unpack('l', substr($contents, 12, 4))[1] ?? 0;
                    $meta['file_version'] = $fileVersionUE4;
                }

                // Try to determine asset class from name table
                $meta['asset_type'] = match ($ext) {
                    'umap'   => 'Level/Map',
                    'uasset' => 'Asset',
                    default  => 'Unknown',
                };
            } else {
                $meta['valid_header'] = false;
            }
        }

        return $meta;
    }

    /**
     * Extract 3D model metadata.
     */
    private function extractModelMetadata(string $filePath, string $contents): ?array
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        $meta = [
            'type'      => 'model3d',
            'extension' => $ext,
            'size'      => strlen($contents),
            'mime_type' => MimeDetector::mimeFromExtension($filePath),
        ];

        // GLB: parse header for version and length
        if ($ext === 'glb' && strlen($contents) >= 12) {
            $magic = unpack('V', substr($contents, 0, 4))[1] ?? 0;
            if ($magic === 0x46546C67) { // 'glTF'
                $meta['format']  = 'glTF Binary';
                $meta['version'] = unpack('V', substr($contents, 4, 4))[1] ?? 0;
                $meta['length']  = unpack('V', substr($contents, 8, 4))[1] ?? 0;
            }
        }

        // GLTF: it's JSON, try to parse
        if ($ext === 'gltf') {
            $json = @json_decode($contents, true);
            if ($json) {
                $meta['format']  = 'glTF JSON';
                $meta['version'] = $json['asset']['version'] ?? null;
                $meta['meshes']  = isset($json['meshes']) ? count($json['meshes']) : null;
                $meta['nodes']   = isset($json['nodes']) ? count($json['nodes']) : null;
                $meta['materials'] = isset($json['materials']) ? count($json['materials']) : null;
            }
        }

        // OBJ: count vertices/faces
        if ($ext === 'obj') {
            $meta['format']   = 'Wavefront OBJ';
            $meta['vertices'] = substr_count($contents, "\nv ") + (str_starts_with($contents, 'v ') ? 1 : 0);
            $meta['faces']    = substr_count($contents, "\nf ") + (str_starts_with($contents, 'f ') ? 1 : 0);
        }

        return $meta;
    }

    /**
     * Generic binary metadata (just size and extension).
     */
    private function extractGenericMetadata(string $filePath, string $contents): ?array
    {
        return [
            'type'      => 'binary',
            'extension' => strtolower(pathinfo($filePath, PATHINFO_EXTENSION)),
            'size'      => strlen($contents),
            'mime_type' => MimeDetector::mimeFromExtension($filePath),
        ];
    }

    private function detectEngine(string $extension): string
    {
        return match ($extension) {
            'uasset', 'umap', 'ubulk', 'uexp' => 'Unreal Engine',
            'unity', 'prefab', 'asset', 'controller' => 'Unity',
            'tscn', 'tres', 'import' => 'Godot',
            default => 'Unknown',
        };
    }
}
