<?php

namespace App\Results;

/**
 * Svenska Friidrottsförbundet's nine districts since 1 January 2022, and the 23 former districts
 * they consist of. The federation club list still uses the former names.
 * Source: friidrottsstatistik.se/aboutdistricts.php
 */
final class Districts
{
    public const MAP = [
        'Norra Norrland' => ['Norrbotten', 'Västerbotten'],
        'Södra Norrland' => ['Jämtland-Härjedalen', 'Jämtland/Härjedalen', 'Medelpad', 'Ångermanland'],
        'Mittsvenska' => ['Dalarna', 'Gästrikland', 'Hälsingland', 'Uppland'],
        'Södra Svealand' => ['Närke', 'Södermanland', 'Värmland', 'Västmanland'],
        'Gotland-Stockholm' => ['Gotland', 'Stockholm'],
        'Västsvenska' => ['Bohuslän-Dal', 'Halland', 'Västergötland', 'Västsvenska'],
        'Östsvenska' => ['Blekinge', 'Småland', 'Östergötland'],
        'Göteborg' => ['Göteborg'],
        'Skåne' => ['Skåne'],
    ];

    /** @return list<string> */
    public static function names(): array
    {
        return array_keys(self::MAP);
    }

    /**
     * District names as found in the club list. A name that is not one of the nine is kept as is.
     *
     * @param  list<string>  $districts
     * @return list<string>
     */
    public static function expand(array $districts): array
    {
        return array_values(array_unique(array_merge([], ...array_map(fn ($d) => self::MAP[$d] ?? [$d], $districts))));
    }
}
