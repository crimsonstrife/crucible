<?php

namespace App\Support;

/**
 * Predefined LFS and lock policy templates for common game engines.
 *
 * Each template returns an array of entries with:
 *   - pattern: glob pattern for .gitattributes / LFS tracking
 *   - description: human-readable label
 *   - lockable: whether the file type should also have a mandatory lock policy
 */
class GameEngineTemplates
{
    /**
     * All available template names.
     *
     * @return string[]
     */
    public static function available(): array
    {
        return ['unreal', 'unity', 'godot', 'general'];
    }

    /**
     * Unreal Engine 5 asset patterns.
     */
    public static function unreal(): array
    {
        return [
            // Core Unreal assets (binary, non-mergeable)
            ['pattern' => '*.uasset',      'description' => 'Unreal asset',            'lockable' => true],
            ['pattern' => '*.umap',         'description' => 'Unreal map/level',        'lockable' => true],
            ['pattern' => '*.uproject',     'description' => 'Unreal project file',     'lockable' => true],
            ['pattern' => '*.uplugin',      'description' => 'Unreal plugin descriptor', 'lockable' => false],

            // 3D models & animation
            ['pattern' => '*.fbx',          'description' => 'FBX 3D model',            'lockable' => true],
            ['pattern' => '*.gltf',         'description' => 'glTF 3D model',           'lockable' => true],
            ['pattern' => '*.glb',          'description' => 'glTF binary',             'lockable' => true],
            ['pattern' => '*.abc',          'description' => 'Alembic cache',           'lockable' => true],
            ['pattern' => '*.obj',          'description' => 'OBJ 3D model',            'lockable' => false],

            // Textures & images
            ['pattern' => '*.png',          'description' => 'PNG image',               'lockable' => false],
            ['pattern' => '*.tga',          'description' => 'TGA texture',             'lockable' => false],
            ['pattern' => '*.exr',          'description' => 'OpenEXR HDR image',       'lockable' => false],
            ['pattern' => '*.hdr',          'description' => 'HDR image',               'lockable' => false],
            ['pattern' => '*.bmp',          'description' => 'BMP image',               'lockable' => false],
            ['pattern' => '*.jpg',          'description' => 'JPEG image',              'lockable' => false],
            ['pattern' => '*.jpeg',         'description' => 'JPEG image',              'lockable' => false],
            ['pattern' => '*.psd',          'description' => 'Photoshop document',      'lockable' => false],
            ['pattern' => '*.tif',          'description' => 'TIFF image',              'lockable' => false],
            ['pattern' => '*.tiff',         'description' => 'TIFF image',              'lockable' => false],

            // Audio
            ['pattern' => '*.wav',          'description' => 'WAV audio',               'lockable' => false],
            ['pattern' => '*.mp3',          'description' => 'MP3 audio',               'lockable' => false],
            ['pattern' => '*.ogg',          'description' => 'OGG audio',               'lockable' => false],

            // Video
            ['pattern' => '*.mp4',          'description' => 'MP4 video',               'lockable' => false],
            ['pattern' => '*.avi',          'description' => 'AVI video',               'lockable' => false],
            ['pattern' => '*.wmv',          'description' => 'WMV video',               'lockable' => false],

            // Fonts
            ['pattern' => '*.ttf',          'description' => 'TrueType font',           'lockable' => false],
            ['pattern' => '*.otf',          'description' => 'OpenType font',           'lockable' => false],

            // Compiled / intermediate
            ['pattern' => '*.ushaderbytecode', 'description' => 'Compiled shader',      'lockable' => false],
        ];
    }

    /**
     * Unity engine asset patterns.
     */
    public static function unity(): array
    {
        return [
            // Core Unity assets (binary, non-mergeable)
            ['pattern' => '*.unity',        'description' => 'Unity scene',             'lockable' => true],
            ['pattern' => '*.prefab',       'description' => 'Unity prefab',            'lockable' => true],
            ['pattern' => '*.asset',        'description' => 'Unity asset',             'lockable' => true],
            ['pattern' => '*.controller',   'description' => 'Animator controller',     'lockable' => true],
            ['pattern' => '*.anim',         'description' => 'Animation clip',          'lockable' => true],
            ['pattern' => '*.mask',         'description' => 'Avatar mask',             'lockable' => true],
            ['pattern' => '*.mat',          'description' => 'Material',                'lockable' => true],
            ['pattern' => '*.physicMaterial', 'description' => 'Physics material',      'lockable' => false],
            ['pattern' => '*.lighting',     'description' => 'Lighting data',           'lockable' => false],
            ['pattern' => '*.terrainlayer', 'description' => 'Terrain layer',           'lockable' => false],

            // 3D models
            ['pattern' => '*.fbx',          'description' => 'FBX 3D model',            'lockable' => true],
            ['pattern' => '*.blend',        'description' => 'Blender file',            'lockable' => true],
            ['pattern' => '*.obj',          'description' => 'OBJ 3D model',            'lockable' => false],

            // Textures & images
            ['pattern' => '*.png',          'description' => 'PNG image',               'lockable' => false],
            ['pattern' => '*.psd',          'description' => 'Photoshop document',      'lockable' => false],
            ['pattern' => '*.tga',          'description' => 'TGA texture',             'lockable' => false],
            ['pattern' => '*.exr',          'description' => 'OpenEXR HDR image',       'lockable' => false],
            ['pattern' => '*.jpg',          'description' => 'JPEG image',              'lockable' => false],
            ['pattern' => '*.jpeg',         'description' => 'JPEG image',              'lockable' => false],

            // Audio
            ['pattern' => '*.wav',          'description' => 'WAV audio',               'lockable' => false],
            ['pattern' => '*.mp3',          'description' => 'MP3 audio',               'lockable' => false],
            ['pattern' => '*.ogg',          'description' => 'OGG audio',               'lockable' => false],

            // Fonts
            ['pattern' => '*.ttf',          'description' => 'TrueType font',           'lockable' => false],
            ['pattern' => '*.otf',          'description' => 'OpenType font',           'lockable' => false],
        ];
    }

    /**
     * Godot engine asset patterns.
     */
    public static function godot(): array
    {
        return [
            // Godot-specific (many are text-based, so fewer lockables)
            ['pattern' => '*.tscn',         'description' => 'Godot scene',             'lockable' => false],
            ['pattern' => '*.tres',         'description' => 'Godot resource',          'lockable' => false],
            ['pattern' => '*.import',       'description' => 'Godot import config',     'lockable' => false],
            ['pattern' => '*.godot',        'description' => 'Godot project file',      'lockable' => false],

            // Common binary assets used with Godot
            ['pattern' => '*.png',          'description' => 'PNG image',               'lockable' => false],
            ['pattern' => '*.jpg',          'description' => 'JPEG image',              'lockable' => false],
            ['pattern' => '*.wav',          'description' => 'WAV audio',               'lockable' => false],
            ['pattern' => '*.ogg',          'description' => 'OGG audio',               'lockable' => false],
            ['pattern' => '*.mp3',          'description' => 'MP3 audio',               'lockable' => false],
            ['pattern' => '*.glb',          'description' => 'glTF binary',             'lockable' => true],
            ['pattern' => '*.gltf',         'description' => 'glTF 3D model',           'lockable' => true],
            ['pattern' => '*.fbx',          'description' => 'FBX 3D model',            'lockable' => true],
            ['pattern' => '*.blend',        'description' => 'Blender file',            'lockable' => true],
            ['pattern' => '*.ttf',          'description' => 'TrueType font',           'lockable' => false],
            ['pattern' => '*.otf',          'description' => 'OpenType font',           'lockable' => false],
        ];
    }

    /**
     * General binary file patterns (engine-agnostic).
     */
    public static function general(): array
    {
        return [
            // 3D / DCC
            ['pattern' => '*.psd',          'description' => 'Photoshop document',      'lockable' => false],
            ['pattern' => '*.blend',        'description' => 'Blender file',            'lockable' => true],
            ['pattern' => '*.max',          'description' => '3ds Max file',            'lockable' => true],
            ['pattern' => '*.mb',           'description' => 'Maya binary',             'lockable' => true],
            ['pattern' => '*.ma',           'description' => 'Maya ASCII',              'lockable' => false],
            ['pattern' => '*.fbx',          'description' => 'FBX 3D model',            'lockable' => true],
            ['pattern' => '*.obj',          'description' => 'OBJ 3D model',            'lockable' => false],
            ['pattern' => '*.glb',          'description' => 'glTF binary',             'lockable' => true],
            ['pattern' => '*.abc',          'description' => 'Alembic cache',           'lockable' => true],
            ['pattern' => '*.zpr',          'description' => 'ZBrush project',          'lockable' => true],
            ['pattern' => '*.spp',          'description' => 'Substance Painter',       'lockable' => true],
            ['pattern' => '*.sbsar',        'description' => 'Substance archive',       'lockable' => false],

            // Images
            ['pattern' => '*.png',          'description' => 'PNG image',               'lockable' => false],
            ['pattern' => '*.jpg',          'description' => 'JPEG image',              'lockable' => false],
            ['pattern' => '*.jpeg',         'description' => 'JPEG image',              'lockable' => false],
            ['pattern' => '*.tga',          'description' => 'TGA texture',             'lockable' => false],
            ['pattern' => '*.exr',          'description' => 'OpenEXR HDR image',       'lockable' => false],
            ['pattern' => '*.hdr',          'description' => 'HDR image',               'lockable' => false],
            ['pattern' => '*.tif',          'description' => 'TIFF image',              'lockable' => false],
            ['pattern' => '*.tiff',         'description' => 'TIFF image',              'lockable' => false],
            ['pattern' => '*.bmp',          'description' => 'BMP image',               'lockable' => false],
            ['pattern' => '*.svg',          'description' => 'SVG vector',              'lockable' => false],

            // Audio
            ['pattern' => '*.wav',          'description' => 'WAV audio',               'lockable' => false],
            ['pattern' => '*.mp3',          'description' => 'MP3 audio',               'lockable' => false],
            ['pattern' => '*.ogg',          'description' => 'OGG audio',               'lockable' => false],
            ['pattern' => '*.flac',         'description' => 'FLAC audio',              'lockable' => false],

            // Video
            ['pattern' => '*.mp4',          'description' => 'MP4 video',               'lockable' => false],
            ['pattern' => '*.avi',          'description' => 'AVI video',               'lockable' => false],
            ['pattern' => '*.mov',          'description' => 'QuickTime video',         'lockable' => false],
            ['pattern' => '*.webm',         'description' => 'WebM video',              'lockable' => false],

            // Archives
            ['pattern' => '*.zip',          'description' => 'ZIP archive',             'lockable' => false],
            ['pattern' => '*.tar.gz',       'description' => 'Gzip archive',            'lockable' => false],
            ['pattern' => '*.7z',           'description' => '7-Zip archive',           'lockable' => false],
            ['pattern' => '*.rar',          'description' => 'RAR archive',             'lockable' => false],

            // Fonts
            ['pattern' => '*.ttf',          'description' => 'TrueType font',           'lockable' => false],
            ['pattern' => '*.otf',          'description' => 'OpenType font',           'lockable' => false],
            ['pattern' => '*.woff',         'description' => 'Web Open Font Format',    'lockable' => false],
            ['pattern' => '*.woff2',        'description' => 'WOFF2 font',              'lockable' => false],

            // Documents
            ['pattern' => '*.pdf',          'description' => 'PDF document',            'lockable' => false],
            ['pattern' => '*.docx',         'description' => 'Word document',           'lockable' => false],
            ['pattern' => '*.xlsx',         'description' => 'Excel spreadsheet',       'lockable' => false],
        ];
    }

    /**
     * Get a template by name.
     */
    public static function get(string $name): ?array
    {
        return match ($name) {
            'unreal' => self::unreal(),
            'unity' => self::unity(),
            'godot' => self::godot(),
            'general' => self::general(),
            default => null,
        };
    }

    /**
     * Generate .gitattributes content from a set of template entries.
     */
    public static function toGitattributes(array $entries): string
    {
        $lines = ['# Generated by Crucible SCM', '# https://git-lfs.com/', ''];

        foreach ($entries as $entry) {
            $lines[] = "{$entry['pattern']} filter=lfs diff=lfs merge=lfs -text";
        }

        $lines[] = '';

        return implode("\n", $lines);
    }
}
