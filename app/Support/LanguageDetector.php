<?php

namespace App\Support;

class LanguageDetector
{
    private const EXTENSIONS = [
        'php' => 'PHP',
        'phtml' => 'PHP',
        'blade.php' => 'Blade',

        'js' => 'JavaScript',
        'jsx' => 'JavaScript',
        'mjs' => 'JavaScript',
        'cjs' => 'JavaScript',
        'ts' => 'TypeScript',
        'tsx' => 'TypeScript',
        'vue' => 'Vue',
        'svelte' => 'Svelte',

        'py' => 'Python',
        'pyi' => 'Python',
        'rb' => 'Ruby',
        'rake' => 'Ruby',

        'go' => 'Go',
        'rs' => 'Rust',
        'java' => 'Java',
        'kt' => 'Kotlin',
        'kts' => 'Kotlin',
        'scala' => 'Scala',
        'groovy' => 'Groovy',

        'swift' => 'Swift',
        'm' => 'Objective-C',
        'mm' => 'Objective-C++',

        'c' => 'C',
        'h' => 'C',
        'cpp' => 'C++',
        'cc' => 'C++',
        'cxx' => 'C++',
        'hpp' => 'C++',
        'hh' => 'C++',
        'hxx' => 'C++',
        'cs' => 'C#',

        'sh' => 'Shell',
        'bash' => 'Shell',
        'zsh' => 'Shell',
        'fish' => 'Shell',
        'ps1' => 'PowerShell',
        'bat' => 'Batchfile',
        'cmd' => 'Batchfile',

        'html' => 'HTML',
        'htm' => 'HTML',
        'xhtml' => 'HTML',
        'css' => 'CSS',
        'scss' => 'SCSS',
        'sass' => 'Sass',
        'less' => 'Less',
        'styl' => 'Stylus',

        'md' => 'Markdown',
        'markdown' => 'Markdown',
        'mdx' => 'MDX',
        'rst' => 'reStructuredText',
        'tex' => 'TeX',

        'yaml' => 'YAML',
        'yml' => 'YAML',
        'toml' => 'TOML',
        'json' => 'JSON',
        'json5' => 'JSON',
        'xml' => 'XML',
        'ini' => 'INI',
        'csv' => 'CSV',

        'sql' => 'SQL',
        'psql' => 'SQL',
        'plsql' => 'SQL',

        'dart' => 'Dart',
        'ex' => 'Elixir',
        'exs' => 'Elixir',
        'erl' => 'Erlang',
        'hrl' => 'Erlang',
        'clj' => 'Clojure',
        'cljs' => 'Clojure',
        'hs' => 'Haskell',
        'lhs' => 'Haskell',
        'ml' => 'OCaml',
        'mli' => 'OCaml',
        'fs' => 'F#',
        'fsx' => 'F#',
        'lua' => 'Lua',
        'pl' => 'Perl',
        'pm' => 'Perl',
        'r' => 'R',
        'jl' => 'Julia',
        'nim' => 'Nim',
        'cr' => 'Crystal',
        'zig' => 'Zig',
        'v' => 'V',

        'hlsl' => 'HLSL',
        'glsl' => 'GLSL',
        'shader' => 'ShaderLab',
        'cg' => 'Cg',
        'asm' => 'Assembly',
        's' => 'Assembly',

        'proto' => 'Protocol Buffers',
        'graphql' => 'GraphQL',
        'gql' => 'GraphQL',
        'tf' => 'Terraform',
        'hcl' => 'HCL',

        'gd' => 'GDScript',
        'tscn' => 'Godot Scene',
    ];

    private const FILENAMES = [
        'dockerfile' => 'Dockerfile',
        'containerfile' => 'Dockerfile',
        'makefile' => 'Makefile',
        'gnumakefile' => 'Makefile',
        'rakefile' => 'Ruby',
        'gemfile' => 'Ruby',
        'cmakelists.txt' => 'CMake',
        'vagrantfile' => 'Ruby',
    ];

    private const EXCLUDED_SEGMENTS = [
        'node_modules',
        'vendor',
        'dist',
        'build',
        'out',
        '.next',
        '.nuxt',
        '.svelte-kit',
        'target',
        '.venv',
        'venv',
        '__pycache__',
        '.gradle',
        '.idea',
        '.vscode',
        'bower_components',
        'jspm_packages',
    ];

    public static function forPath(string $path): ?string
    {
        $basename = strtolower(basename($path));

        if (isset(self::FILENAMES[$basename])) {
            return self::FILENAMES[$basename];
        }

        if (str_ends_with($basename, '.blade.php')) {
            return self::EXTENSIONS['blade.php'];
        }

        $extension = strtolower(pathinfo($basename, PATHINFO_EXTENSION));

        if ($extension === '') {
            return null;
        }

        return self::EXTENSIONS[$extension] ?? null;
    }

    public static function isExcludedPath(string $path): bool
    {
        foreach (explode('/', $path) as $segment) {
            if ($segment !== '' && in_array($segment, self::EXCLUDED_SEGMENTS, true)) {
                return true;
            }
        }

        return false;
    }
}
