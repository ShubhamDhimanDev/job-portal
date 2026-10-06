<?php

namespace App\Enums;

enum InterviewType: string
{
    case FaceToFace = 'face_to_face';
    case Virtual = 'virtual';

    public function label(): string
    {
        return match ($this) {
            self::FaceToFace => 'Face to Face',
            self::Virtual => 'Virtual',
        };
    }
}
