<?php

namespace App\Services;

use App\Models\Repository;

/**
 * Parses Unreal Engine .uasset binary files for metadata, class identification,
 * and dependency tracking.
 *
 * Reference: UE4/UE5 FPackageFileSummary format
 * https://github.com/EpicGames/UnrealEngine (requires access)
 *
 * Header layout (simplified):
 *   Offset 0:   uint32 Tag (magic: 0x9E2A83C1)
 *   Offset 4:   int32  LegacyFileVersion
 *   Offset 8:   int32  LegacyUE3Version
 *   Offset 12:  int32  FileVersionUE4
 *   Offset 16:  int32  FileVersionLicenseeUE4
 *   Offset 20:  Custom version container (variable)
 *   ...
 *   Various offsets: NameCount, NameOffset, ExportCount, ImportCount, etc.
 */
class UnrealAssetService
{
    /** Unreal package magic number. */
    private const PACKAGE_MAGIC = 0x9E2A83C1;

    /**
     * Parse a .uasset file and extract metadata.
     *
     * @return array|null  Parsed metadata or null if not a valid uasset.
     */
    public function parse(string $contents): ?array
    {
        if (strlen($contents) < 40) {
            return null;
        }

        $magic = unpack('V', substr($contents, 0, 4))[1] ?? 0;

        if ($magic !== self::PACKAGE_MAGIC) {
            return null;
        }

        $meta = [
            'valid'                => true,
            'magic'                => sprintf('0x%08X', $magic),
            'legacy_file_version'  => unpack('l', substr($contents, 4, 4))[1] ?? 0,
            'legacy_ue3_version'   => unpack('l', substr($contents, 8, 4))[1] ?? 0,
            'file_version_ue4'     => unpack('l', substr($contents, 12, 4))[1] ?? 0,
            'file_version_licensee' => unpack('l', substr($contents, 16, 4))[1] ?? 0,
            'size'                 => strlen($contents),
        ];

        // Try to determine the engine version from the file version
        $meta['engine_version'] = $this->guessEngineVersion($meta['file_version_ue4']);

        // Parse the package summary header for counts and offsets
        $this->parsePackageSummary($contents, $meta);

        // Classify the asset type
        $meta['asset_class'] = $this->classifyAsset($contents, $meta);

        return $meta;
    }

    /**
     * Parse a .uasset from a repository at a given ref and path.
     */
    public function parseFromRepository(
        Repository $repository,
        NativeGitRepositoryService $git,
        string $ref,
        string $path,
    ): ?array {
        $contents = $git->readFile($repository, $ref, $path);

        if ($contents === null) {
            return null;
        }

        $meta = $this->parse($contents);

        if ($meta) {
            $meta['path'] = $path;
            $meta['ref'] = $ref;
        }

        return $meta;
    }

    /**
     * Identify the asset class (Blueprint, Material, Level, Texture, etc.)
     * from file extension and binary content heuristics.
     */
    public function classifyAsset(string $contents, array $meta = []): string
    {
        // Check for known class name strings in the binary data
        $classIndicators = [
            'BlueprintGeneratedClass' => 'Blueprint',
            'WidgetBlueprint'         => 'Widget Blueprint',
            'AnimBlueprint'           => 'Animation Blueprint',
            'MaterialInstanceConstant' => 'Material Instance',
            'Material'                => 'Material',
            'Texture2D'              => 'Texture',
            'TextureCube'            => 'Cubemap Texture',
            'StaticMesh'             => 'Static Mesh',
            'SkeletalMesh'           => 'Skeletal Mesh',
            'AnimSequence'           => 'Animation Sequence',
            'AnimMontage'            => 'Animation Montage',
            'SoundWave'              => 'Sound Wave',
            'SoundCue'               => 'Sound Cue',
            'ParticleSystem'         => 'Particle System',
            'NiagaraSystem'          => 'Niagara System',
            'NiagaraEmitter'         => 'Niagara Emitter',
            'DataTable'              => 'Data Table',
            'CurveFloat'             => 'Float Curve',
            'CurveLinearColor'       => 'Color Curve',
            'World'                  => 'Level/World',
            'Level'                  => 'Level',
            'UserDefinedStruct'      => 'Struct',
            'UserDefinedEnum'        => 'Enum',
            'Font'                   => 'Font',
            'FontFace'               => 'Font Face',
            'MediaSource'            => 'Media Source',
            'MediaPlayer'            => 'Media Player',
        ];

        // Search for class name strings in the binary (they appear as FName entries)
        foreach ($classIndicators as $needle => $className) {
            // UE stores strings as length-prefixed, but we can search for the ASCII
            if (str_contains($contents, $needle)) {
                return $className;
            }
        }

        return 'Unknown Asset';
    }

    /**
     * Extract dependency paths from a .uasset file.
     * Dependencies appear as FObjectImport entries referencing other packages.
     *
     * @return string[]  Array of dependency paths (e.g., "/Game/Textures/T_Hero")
     */
    public function extractDependencies(string $contents): array
    {
        $dependencies = [];

        // UE asset dependencies are stored as import table entries
        // They reference packages using /Game/, /Engine/, /Script/ paths
        // Search for path patterns in the binary
        if (preg_match_all('#(/(?:Game|Engine|Script)/[\w/]+)#', $contents, $matches)) {
            $dependencies = array_unique($matches[1]);
            sort($dependencies);
        }

        return $dependencies;
    }

    /**
     * Get a summary suitable for display in diff views.
     */
    public function summary(string $contents): array
    {
        $meta = $this->parse($contents);

        if (! $meta) {
            return ['error' => 'Not a valid Unreal asset'];
        }

        $deps = $this->extractDependencies($contents);

        return [
            'asset_class'    => $meta['asset_class'],
            'engine_version' => $meta['engine_version'],
            'file_version'   => $meta['file_version_ue4'],
            'size'           => $meta['size'],
            'name_count'     => $meta['name_count'] ?? null,
            'export_count'   => $meta['export_count'] ?? null,
            'import_count'   => $meta['import_count'] ?? null,
            'dependencies'   => array_slice($deps, 0, 20), // Limit for display
            'total_dependencies' => count($deps),
        ];
    }

    /**
     * Parse package summary counts from the header.
     */
    private function parsePackageSummary(string $contents, array &$meta): void
    {
        // After the custom version container, the offsets vary by UE version.
        // For UE4 with LegacyFileVersion < -7, the layout shifts.
        // We attempt to locate the name/export/import counts heuristically.

        $legacyVer = $meta['legacy_file_version'];

        if ($legacyVer >= -7 && strlen($contents) >= 45) {
            // UE4 legacy format: fixed offsets after version fields
            // These offsets are approximate and may vary with custom versions
            $offset = 20; // Start after first 20 bytes

            // Skip custom version container if present
            if ($legacyVer <= -2 && strlen($contents) >= $offset + 4) {
                $customVersionCount = unpack('l', substr($contents, $offset, 4))[1] ?? 0;
                $offset += 4;

                // Each custom version entry is 20 bytes (GUID + version)
                if ($customVersionCount > 0 && $customVersionCount < 1000) {
                    $offset += $customVersionCount * 20;
                }
            }

            // Try to read total header size and name/export/import counts
            if (strlen($contents) >= $offset + 24) {
                $meta['total_header_size'] = unpack('V', substr($contents, $offset, 4))[1] ?? null;
                $offset += 4;

                // Folder name (FString — int32 length + chars)
                if (strlen($contents) >= $offset + 4) {
                    $folderLen = unpack('l', substr($contents, $offset, 4))[1] ?? 0;
                    $offset += 4;
                    if ($folderLen > 0 && $folderLen < 1024) {
                        $offset += $folderLen;
                    }
                }

                // Package flags
                if (strlen($contents) >= $offset + 4) {
                    $meta['package_flags'] = unpack('V', substr($contents, $offset, 4))[1] ?? null;
                    $offset += 4;
                }

                // Name count and offset
                if (strlen($contents) >= $offset + 8) {
                    $meta['name_count']  = unpack('l', substr($contents, $offset, 4))[1] ?? null;
                    $meta['name_offset'] = unpack('l', substr($contents, $offset + 4, 4))[1] ?? null;
                    $offset += 8;
                }

                // Export count and offset
                if (strlen($contents) >= $offset + 8) {
                    $meta['export_count']  = unpack('l', substr($contents, $offset, 4))[1] ?? null;
                    $meta['export_offset'] = unpack('l', substr($contents, $offset + 4, 4))[1] ?? null;
                    $offset += 8;
                }

                // Import count and offset
                if (strlen($contents) >= $offset + 8) {
                    $meta['import_count']  = unpack('l', substr($contents, $offset, 4))[1] ?? null;
                    $meta['import_offset'] = unpack('l', substr($contents, $offset + 4, 4))[1] ?? null;
                }
            }
        }

        // Sanitize: negative or unreasonably large counts indicate parsing error
        foreach (['name_count', 'export_count', 'import_count'] as $key) {
            if (isset($meta[$key]) && ($meta[$key] < 0 || $meta[$key] > 1_000_000)) {
                $meta[$key] = null;
            }
        }
    }

    /**
     * Guess the Unreal Engine version from the UE4 file version number.
     */
    private function guessEngineVersion(int $fileVersionUE4): string
    {
        return match (true) {
            $fileVersionUE4 >= 1009 => 'UE 5.5+',
            $fileVersionUE4 >= 1008 => 'UE 5.4',
            $fileVersionUE4 >= 1007 => 'UE 5.3',
            $fileVersionUE4 >= 1004 => 'UE 5.2',
            $fileVersionUE4 >= 1002 => 'UE 5.1',
            $fileVersionUE4 >= 1000 => 'UE 5.0',
            $fileVersionUE4 >= 522  => 'UE 4.27',
            $fileVersionUE4 >= 518  => 'UE 4.26',
            $fileVersionUE4 >= 516  => 'UE 4.25',
            $fileVersionUE4 >= 514  => 'UE 4.24',
            $fileVersionUE4 >= 513  => 'UE 4.23',
            $fileVersionUE4 >= 510  => 'UE 4.22',
            $fileVersionUE4 >= 508  => 'UE 4.21',
            $fileVersionUE4 >= 505  => 'UE 4.20',
            $fileVersionUE4 >= 504  => 'UE 4.19',
            $fileVersionUE4 >= 503  => 'UE 4.18',
            $fileVersionUE4 > 0     => 'UE 4.x',
            default                 => 'Unknown',
        };
    }
}
