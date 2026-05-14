<?php

namespace App\Support\Pdf;

class LegacyPdfFormat
{
    public static function number(float $value, int $decimals): string
    {
        return number_format($value, $decimals, ',', '.');
    }

    public static function amountLiteral(float $value): string
    {
        return self::literalFromLegacyNumber(self::number($value, 2));
    }

    public static function literalFromLegacyNumber(string $number): string
    {
        $normalized = str_replace('.', '', $number);
        [$integer, $cents] = array_pad(explode(',', $normalized, 2), 2, '00');

        $integer = (int) $integer;
        $cents = str_pad(substr($cents, 0, 2), 2, '0');

        return ' '.self::milmillon($integer).' CON '.$cents.'/100';
    }

    private static function unidad(int $numero): string
    {
        return match ($numero) {
            9 => 'NUEVE',
            8 => 'OCHO',
            7 => 'SIETE',
            6 => 'SEIS',
            5 => 'CINCO',
            4 => 'CUATRO',
            3 => 'TRES',
            2 => 'DOS',
            1 => 'UNO',
            default => 'CERO',
        };
    }

    private static function decena(int $numero): string
    {
        if ($numero >= 90 && $numero <= 99) {
            return 'NOVENTA '.($numero > 90 ? 'Y '.self::unidad($numero - 90) : '');
        }
        if ($numero >= 80 && $numero <= 89) {
            return 'OCHENTA '.($numero > 80 ? 'Y '.self::unidad($numero - 80) : '');
        }
        if ($numero >= 70 && $numero <= 79) {
            return 'SETENTA '.($numero > 70 ? 'Y '.self::unidad($numero - 70) : '');
        }
        if ($numero >= 60 && $numero <= 69) {
            return 'SESENTA '.($numero > 60 ? 'Y '.self::unidad($numero - 60) : '');
        }
        if ($numero >= 50 && $numero <= 59) {
            return 'CINCUENTA '.($numero > 50 ? 'Y '.self::unidad($numero - 50) : '');
        }
        if ($numero >= 40 && $numero <= 49) {
            return 'CUARENTA '.($numero > 40 ? 'Y '.self::unidad($numero - 40) : '');
        }
        if ($numero >= 30 && $numero <= 39) {
            return 'TREINTA '.($numero > 30 ? 'Y '.self::unidad($numero - 30) : '');
        }
        if ($numero >= 20 && $numero <= 29) {
            return $numero === 20 ? 'VEINTE ' : 'VEINTI'.self::unidad($numero - 20);
        }
        if ($numero >= 10 && $numero <= 19) {
            return match ($numero) {
                10 => 'DIEZ ',
                11 => 'ONCE ',
                12 => 'DOCE ',
                13 => 'TRECE ',
                14 => 'CATORCE ',
                15 => 'QUINCE ',
                16 => 'DIECISEIS ',
                17 => 'DIECISIETE ',
                18 => 'DIECIOCHO ',
                default => 'DIECINUEVE ',
            };
        }

        return self::unidad($numero);
    }

    private static function centena(int $numero): string
    {
        if ($numero >= 900 && $numero <= 999) {
            return 'NOVECIENTOS '.($numero > 900 ? self::decena($numero - 900) : '');
        }
        if ($numero >= 800 && $numero <= 899) {
            return 'OCHOCIENTOS '.($numero > 800 ? self::decena($numero - 800) : '');
        }
        if ($numero >= 700 && $numero <= 799) {
            return 'SETECIENTOS '.($numero > 700 ? self::decena($numero - 700) : '');
        }
        if ($numero >= 600 && $numero <= 699) {
            return 'SEISCIENTOS '.($numero > 600 ? self::decena($numero - 600) : '');
        }
        if ($numero >= 500 && $numero <= 599) {
            return 'QUINIENTOS '.($numero > 500 ? self::decena($numero - 500) : '');
        }
        if ($numero >= 400 && $numero <= 499) {
            return 'CUATROCIENTOS '.($numero > 400 ? self::decena($numero - 400) : '');
        }
        if ($numero >= 300 && $numero <= 399) {
            return 'TRESCIENTOS '.($numero > 300 ? self::decena($numero - 300) : '');
        }
        if ($numero >= 200 && $numero <= 299) {
            return 'DOSCIENTOS '.($numero > 200 ? self::decena($numero - 200) : '');
        }
        if ($numero >= 100 && $numero <= 199) {
            return $numero === 100 ? 'CIEN ' : 'CIENTO '.self::decena($numero - 100);
        }

        return self::decena($numero);
    }

    private static function miles(int $numero): string
    {
        if ($numero >= 1000 && $numero < 2000) {
            return 'MIL '.self::centena($numero % 1000);
        }
        if ($numero >= 2000 && $numero < 10000) {
            return self::unidad((int) floor($numero / 1000)).' MIL '.self::centena($numero % 1000);
        }

        return self::centena($numero);
    }

    private static function decmiles(int $numero): string
    {
        if ($numero === 10000) {
            return 'DIEZ MIL';
        }
        if ($numero > 10000 && $numero < 20000) {
            return self::decena((int) floor($numero / 1000)).'MIL '.self::centena($numero % 1000);
        }
        if ($numero >= 20000 && $numero < 100000) {
            return self::decena((int) floor($numero / 1000)).' MIL '.self::miles($numero % 1000);
        }

        return self::miles($numero);
    }

    private static function cienmiles(int $numero): string
    {
        if ($numero === 100000) {
            return 'CIEN MIL';
        }
        if ($numero >= 100000 && $numero < 1000000) {
            return self::centena((int) floor($numero / 1000)).' MIL '.self::centena($numero % 1000);
        }

        return self::decmiles($numero);
    }

    private static function millon(int $numero): string
    {
        if ($numero >= 1000000 && $numero < 2000000) {
            return 'UN MILLON '.self::cienmiles($numero % 1000000);
        }
        if ($numero >= 2000000 && $numero < 10000000) {
            return self::unidad((int) floor($numero / 1000000)).' MILLONES '.self::cienmiles($numero % 1000000);
        }

        return self::cienmiles($numero);
    }

    private static function decmillon(int $numero): string
    {
        if ($numero === 10000000) {
            return 'DIEZ MILLONES';
        }
        if ($numero > 10000000 && $numero < 20000000) {
            return self::decena((int) floor($numero / 1000000)).'MILLONES '.self::cienmiles($numero % 1000000);
        }
        if ($numero >= 20000000 && $numero < 100000000) {
            return self::decena((int) floor($numero / 1000000)).' MILLONES '.self::millon($numero % 1000000);
        }

        return self::millon($numero);
    }

    private static function cienmillon(int $numero): string
    {
        if ($numero === 100000000) {
            return 'CIEN MILLONES';
        }
        if ($numero >= 100000000 && $numero < 1000000000) {
            return self::centena((int) floor($numero / 1000000)).' MILLONES '.self::millon($numero % 1000000);
        }

        return self::decmillon($numero);
    }

    private static function milmillon(int $numero): string
    {
        if ($numero >= 1000000000 && $numero < 2000000000) {
            return 'MIL '.self::cienmillon($numero % 1000000000);
        }
        if ($numero >= 2000000000 && $numero < 10000000000) {
            return self::unidad((int) floor($numero / 1000000000)).' MIL '.self::cienmillon($numero % 1000000000);
        }

        return self::cienmillon($numero);
    }
}
