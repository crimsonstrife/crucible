<?php

namespace App\Enums;

enum EngineType: string
{
    case Unreal = 'unreal';
    case Unity  = 'unity';
    case Godot  = 'godot';

    public function label(): string
    {
        return match ($this) {
            self::Unreal => 'Unreal Engine',
            self::Unity  => 'Unity',
            self::Godot  => 'Godot',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Unreal => 'UE',
            self::Unity  => 'U3D',
            self::Godot  => 'GD',
        };
    }

    /**
     * File markers that indicate this engine is in use.
     *
     * @return string[]
     */
    public function markers(): array
    {
        return match ($this) {
            self::Unreal => ['*.uproject'],
            self::Unity  => ['ProjectSettings/ProjectVersion.txt', 'Assets/'],
            self::Godot  => ['project.godot'],
        };
    }

    /**
     * The corresponding template name in GameEngineTemplates.
     */
    public function templateName(): string
    {
        return $this->value;
    }
}
