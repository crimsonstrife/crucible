<?php

namespace App\Support;

/**
 * Maps file extensions to MIME types and categorizes files for
 * visual diff rendering (images, audio, video, 3D models).
 */
class MimeDetector
{
    /**
     * Known image extensions that can be rendered in the browser.
     */
    private const IMAGE_EXTENSIONS = [
        'png', 'jpg', 'jpeg', 'gif', 'bmp', 'webp', 'svg', 'ico',
        'tga', 'tiff', 'tif',
    ];

    /**
     * Image formats the browser can display natively (without conversion).
     */
    private const BROWSER_IMAGE_EXTENSIONS = [
        'png', 'jpg', 'jpeg', 'gif', 'bmp', 'webp', 'svg', 'ico',
    ];

    /**
     * Audio extensions with browser playback support.
     */
    private const AUDIO_EXTENSIONS = [
        'mp3', 'wav', 'ogg', 'flac', 'aac', 'm4a', 'weba',
    ];

    /**
     * Video extensions with browser playback support.
     */
    private const VIDEO_EXTENSIONS = [
        'mp4', 'webm', 'ogv', 'mov', 'avi', 'mkv',
    ];

    /**
     * 3D model extensions.
     */
    private const MODEL_3D_EXTENSIONS = [
        'glb', 'gltf', 'obj', 'fbx', 'blend', 'max', 'mb', 'ma',
    ];

    /**
     * Game engine binary asset extensions.
     */
    private const ENGINE_ASSET_EXTENSIONS = [
        'uasset', 'umap', 'ubulk', 'uexp',      // Unreal Engine
        'unity', 'prefab', 'asset', 'controller', // Unity
        'tscn', 'tres', 'import',                 // Godot
    ];

    /**
     * Extension-to-MIME mapping for common types.
     */
    private const MIME_MAP = [
        // Images
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'tga' => 'image/x-tga',
        'tiff' => 'image/tiff',
        'tif' => 'image/tiff',
        'exr' => 'image/x-exr',
        'hdr' => 'image/vnd.radiance',
        'psd' => 'image/vnd.adobe.photoshop',
        // Audio
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'ogg' => 'audio/ogg',
        'flac' => 'audio/flac',
        'aac' => 'audio/aac',
        'm4a' => 'audio/mp4',
        'weba' => 'audio/webm',
        // Video
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'ogv' => 'video/ogg',
        'mov' => 'video/quicktime',
        'avi' => 'video/x-msvideo',
        'mkv' => 'video/x-matroska',
        // 3D Models
        'glb' => 'model/gltf-binary',
        'gltf' => 'model/gltf+json',
        'obj' => 'model/obj',
        'fbx' => 'application/octet-stream',
        // Game engine
        'uasset' => 'application/x-unreal-asset',
        'umap' => 'application/x-unreal-map',
        'ubulk' => 'application/x-unreal-bulk',
        'uexp' => 'application/x-unreal-export',
    ];

    /**
     * Detect the visual category for diff rendering.
     * Returns: 'image', 'audio', 'video', 'model3d', 'engine_asset', or null.
     */
    public static function diffCategory(string $filePath): ?string
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (in_array($ext, self::BROWSER_IMAGE_EXTENSIONS, true)) {
            return 'image';
        }

        if (in_array($ext, self::AUDIO_EXTENSIONS, true)) {
            return 'audio';
        }

        if (in_array($ext, self::VIDEO_EXTENSIONS, true)) {
            return 'video';
        }

        if (in_array($ext, self::MODEL_3D_EXTENSIONS, true)) {
            return 'model3d';
        }

        if (in_array($ext, self::ENGINE_ASSET_EXTENSIONS, true)) {
            return 'engine_asset';
        }

        return null;
    }

    /**
     * Get MIME type from file extension.
     */
    public static function mimeFromExtension(string $filePath): string
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return self::MIME_MAP[$ext] ?? 'application/octet-stream';
    }

    /**
     * Check if a file is an image the browser can render.
     */
    public static function isBrowserImage(string $filePath): bool
    {
        return self::diffCategory($filePath) === 'image';
    }

    /**
     * Check if a file is an audio type the browser can play.
     */
    public static function isAudio(string $filePath): bool
    {
        return self::diffCategory($filePath) === 'audio';
    }

    /**
     * Check if a file is a video type.
     */
    public static function isVideo(string $filePath): bool
    {
        return self::diffCategory($filePath) === 'video';
    }

    /**
     * Check if the file is a known binary asset from a game engine.
     */
    public static function isEngineAsset(string $filePath): bool
    {
        return self::diffCategory($filePath) === 'engine_asset';
    }

    /**
     * Get all supported image extensions.
     */
    public static function imageExtensions(): array
    {
        return self::IMAGE_EXTENSIONS;
    }
}
