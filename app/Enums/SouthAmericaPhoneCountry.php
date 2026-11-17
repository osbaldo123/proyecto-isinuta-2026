<?php

namespace App\Enums;

enum SouthAmericaPhoneCountry: string
{
    case Bolivia = '+591';
    case Argentina = '+54';
    case Brasil = '+55';
    case Chile = '+56';
    case Colombia = '+57';
    case Ecuador = '+593';
    case Guyana = '+592';
    case Paraguay = '+595';
    case Peru = '+51';
    case Surinam = '+597';
    case Uruguay = '+598';
    case Venezuela = '+58';

    public function label(): string
    {
        return match ($this) {
            self::Bolivia => 'Bolivia',
            self::Argentina => 'Argentina',
            self::Brasil => 'Brasil',
            self::Chile => 'Chile',
            self::Colombia => 'Colombia',
            self::Ecuador => 'Ecuador',
            self::Guyana => 'Guyana',
            self::Paraguay => 'Paraguay',
            self::Peru => 'Perú',
            self::Surinam => 'Surinam',
            self::Uruguay => 'Uruguay',
            self::Venezuela => 'Venezuela',
        };
    }

    /**
     * @return array<int, array{value: string, label: string, dial: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $country): array => [
                'value' => $country->value,
                'label' => $country->label(),
                'dial' => $country->value,
            ],
            self::cases(),
        );
    }

    public static function isValid(string $code): bool
    {
        return in_array($code, array_column(self::options(), 'value'), true);
    }
}
