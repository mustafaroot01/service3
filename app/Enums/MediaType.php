<?php

namespace App\Enums;

enum MediaType: string
{
    case PERSONAL = 'personal';
    case ID_FRONT = 'id_front';
    case ID_BACK = 'id_back';
    case RESIDENCE_FRONT = 'residence_front';
    case RESIDENCE_BACK = 'residence_back';
    case WORK_SAMPLE = 'work_sample';

    public const WORK_SAMPLE_LIMIT = 4;

    public function label(): string
    {
        return match ($this) {
            self::PERSONAL => 'صورة شخصية',
            self::ID_FRONT => 'وجه البطاقة الوطنية',
            self::ID_BACK => 'ظهر البطاقة الوطنية',
            self::RESIDENCE_FRONT => 'وجه بطاقة السكن',
            self::RESIDENCE_BACK => 'ظهر بطاقة السكن',
            self::WORK_SAMPLE => 'نموذج عمل',
        };
    }

    public function holdsOneFile(): bool
    {
        return $this !== self::WORK_SAMPLE;
    }

    public function limit(): int
    {
        return $this->holdsOneFile() ? 1 : self::WORK_SAMPLE_LIMIT;
    }

    /**
     * @return array<int, self>
     */
    public static function singleFileTypes(): array
    {
        return array_values(array_filter(self::cases(), fn (self $type) => $type->holdsOneFile()));
    }

    /**
     * The documents an approved technician must have on file.
     *
     * @return array<int, self>
     */
    public static function requiredDocuments(): array
    {
        return self::singleFileTypes();
    }
}
