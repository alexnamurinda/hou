<?php
declare(strict_types=1);

function membership_card_pdf_u16(string $data, int $offset): int
{
    return unpack('n', substr($data, $offset, 2))[1];
}

function membership_card_pdf_u32(string $data, int $offset): int
{
    return unpack('N', substr($data, $offset, 4))[1];
}

function membership_card_pdf_i16(string $data, int $offset): int
{
    $value = membership_card_pdf_u16($data, $offset);
    return $value >= 0x8000 ? $value - 0x10000 : $value;
}

function membership_card_pdf_ttf(string $path, array $characters): array
{
    $data = file_get_contents($path);
    if (!is_string($data) || strlen($data) < 12) {
        throw new RuntimeException('Poppins font could not be read.');
    }

    $tableCount = membership_card_pdf_u16($data, 4);
    $tables = [];
    for ($index = 0; $index < $tableCount; $index++) {
        $tableOffset = 12 + $index * 16;
        $tag = substr($data, $tableOffset, 4);
        $tables[$tag] = [
            'offset' => membership_card_pdf_u32($data, $tableOffset + 8),
            'length' => membership_card_pdf_u32($data, $tableOffset + 12),
        ];
    }
    foreach (['cmap', 'head', 'hhea', 'hmtx', 'maxp'] as $requiredTable) {
        if (!isset($tables[$requiredTable])) {
            throw new RuntimeException('Poppins font is missing a required TrueType table.');
        }
    }

    $cmapOffset = $tables['cmap']['offset'];
    $cmapSubtable = null;
    $cmapFormat = 0;
    $cmapCount = membership_card_pdf_u16($data, $cmapOffset + 2);
    for ($index = 0; $index < $cmapCount; $index++) {
        $entry = $cmapOffset + 4 + $index * 8;
        $platform = membership_card_pdf_u16($data, $entry);
        $encoding = membership_card_pdf_u16($data, $entry + 2);
        $subtable = $cmapOffset + membership_card_pdf_u32($data, $entry + 4);
        $format = membership_card_pdf_u16($data, $subtable);
        if (
            ($format === 4 && ($platform === 0 || ($platform === 3 && $encoding === 1)))
            || ($format === 12 && ($platform === 0 || ($platform === 3 && $encoding === 10)))
        ) {
            $cmapSubtable = $subtable;
            $cmapFormat = $format;
            if ($format === 12) {
                break;
            }
        }
    }
    if ($cmapSubtable === null) {
        throw new RuntimeException('Poppins font has no supported Unicode character map.');
    }

    $cmapGroups = [];
    $cmapSegments = [];
    if ($cmapFormat === 12) {
        $groupCount = membership_card_pdf_u32($data, $cmapSubtable + 12);
        for ($index = 0; $index < $groupCount; $index++) {
            $offset = $cmapSubtable + 16 + $index * 12;
            $cmapGroups[] = [
                membership_card_pdf_u32($data, $offset),
                membership_card_pdf_u32($data, $offset + 4),
                membership_card_pdf_u32($data, $offset + 8),
            ];
        }
    } else {
        $segmentCount = intdiv(membership_card_pdf_u16($data, $cmapSubtable + 6), 2);
        $endCodesOffset = $cmapSubtable + 14;
        $startCodesOffset = $endCodesOffset + 2 * $segmentCount + 2;
        $deltasOffset = $startCodesOffset + 2 * $segmentCount;
        $rangeOffsetsOffset = $deltasOffset + 2 * $segmentCount;
        for ($index = 0; $index < $segmentCount; $index++) {
            $cmapSegments[] = [
                'start' => membership_card_pdf_u16($data, $startCodesOffset + 2 * $index),
                'end' => membership_card_pdf_u16($data, $endCodesOffset + 2 * $index),
                'delta' => membership_card_pdf_i16($data, $deltasOffset + 2 * $index),
                'rangeOffset' => membership_card_pdf_u16($data, $rangeOffsetsOffset + 2 * $index),
                'rangeOffsetPosition' => $rangeOffsetsOffset + 2 * $index,
            ];
        }
    }

    $glyphCount = membership_card_pdf_u16($data, $tables['maxp']['offset'] + 4);
    $horizontalMetrics = membership_card_pdf_u16($data, $tables['hhea']['offset'] + 34);
    $horizontalMetricsOffset = $tables['hmtx']['offset'];
    $glyphWidths = [];
    $lastWidth = 0;
    for ($glyph = 0; $glyph < $glyphCount; $glyph++) {
        if ($glyph < $horizontalMetrics) {
            $lastWidth = membership_card_pdf_u16($data, $horizontalMetricsOffset + $glyph * 4);
        }
        $glyphWidths[$glyph] = $lastWidth;
    }

    $unitsPerEm = membership_card_pdf_u16($data, $tables['head']['offset'] + 18);
    if ($unitsPerEm < 1) {
        throw new RuntimeException('Poppins font has invalid character metrics.');
    }
    $glyphs = [];
    $widths = [];
    foreach ($characters as $character) {
        $codepoint = mb_ord($character, 'UTF-8');
        if (!is_int($codepoint) || $codepoint > 0xFFFF) {
            $codepoint = 63;
        }
        $glyph = 0;
        if ($cmapFormat === 12) {
            $low = 0;
            $high = count($cmapGroups) - 1;
            while ($low <= $high) {
                $middle = intdiv($low + $high, 2);
                [$start, $end, $startGlyph] = $cmapGroups[$middle];
                if ($codepoint < $start) {
                    $high = $middle - 1;
                } elseif ($codepoint > $end) {
                    $low = $middle + 1;
                } else {
                    $glyph = $startGlyph + $codepoint - $start;
                    break;
                }
            }
        } elseif ($codepoint <= 0xFFFF) {
            foreach ($cmapSegments as $segment) {
                if ($codepoint < $segment['start'] || $codepoint > $segment['end']) {
                    continue;
                }
                if ($segment['rangeOffset'] === 0) {
                    $glyph = ($codepoint + $segment['delta']) & 0xFFFF;
                } else {
                    $glyphOffset = $segment['rangeOffsetPosition'] + $segment['rangeOffset']
                        + 2 * ($codepoint - $segment['start']);
                    $glyph = membership_card_pdf_u16($data, $glyphOffset);
                    if ($glyph !== 0) {
                        $glyph = ($glyph + $segment['delta']) & 0xFFFF;
                    }
                }
                break;
            }
        }

        if ($glyph >= $glyphCount) {
            $glyph = 0;
        }
        $glyphs[$codepoint] = $glyph;
        $widths[$codepoint] = (int)round(1000 * $glyphWidths[$glyph] / $unitsPerEm);
    }

    $headOffset = $tables['head']['offset'];
    $hheaOffset = $tables['hhea']['offset'];
    return [
        'data' => $data,
        'glyphs' => $glyphs,
        'widths' => $widths,
        'unitsPerEm' => $unitsPerEm,
        'fontBox' => [
            membership_card_pdf_i16($data, $headOffset + 36),
            membership_card_pdf_i16($data, $headOffset + 38),
            membership_card_pdf_i16($data, $headOffset + 40),
            membership_card_pdf_i16($data, $headOffset + 42),
        ],
        'ascent' => membership_card_pdf_i16($data, $hheaOffset + 4),
        'descent' => membership_card_pdf_i16($data, $hheaOffset + 6),
    ];
}

function membership_card_pdf_text_width(array $font, string $text, float $fontSize): float
{
    preg_match_all('/./us', $text, $characters);
    $width = 0;
    foreach ($characters[0] as $character) {
        $codepoint = mb_ord($character, 'UTF-8');
        if (!is_int($codepoint) || $codepoint > 0xFFFF) {
            $codepoint = 63;
        }
        $width += $font['widths'][$codepoint] ?? 500;
    }
    return $width * $fontSize / 1000;
}

function membership_card_pdf_font(array &$objects, array $font, string $name): int
{
    $codepoints = array_keys($font['glyphs']);
    sort($codepoints, SORT_NUMERIC);
    $maximumCodepoint = $codepoints === [] ? 0 : max($codepoints);
    $cidToGlyph = str_repeat("\0", ($maximumCodepoint + 1) * 2);
    $widthEntries = [];
    $unicodeEntries = [];
    foreach ($codepoints as $codepoint) {
        $glyph = $font['glyphs'][$codepoint];
        $cidToGlyph[$codepoint * 2] = chr(($glyph >> 8) & 0xFF);
        $cidToGlyph[$codepoint * 2 + 1] = chr($glyph & 0xFF);
        $widthEntries[] = $codepoint . ' [' . $font['widths'][$codepoint] . ']';
        $unicode = iconv('UTF-8', 'UTF-16BE', mb_chr($codepoint, 'UTF-8'));
        if (is_string($unicode) && $unicode !== '') {
            $unicodeEntries[] = sprintf('<%04X> <%s>', $codepoint, strtoupper(bin2hex($unicode)));
        }
    }

    $cmap = "/CIDInit /ProcSet findresource begin\n12 dict begin\nbegincmap\n"
        . "/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n"
        . "/CMapName /Adobe-Identity-UCS def\n/CMapType 2 def\n"
        . "1 begincodespacerange\n<0000> <FFFF>\nendcodespacerange\n";
    foreach (array_chunk($unicodeEntries, 100) as $chunk) {
        $cmap .= count($chunk) . " beginbfchar\n" . implode("\n", $chunk) . "\nendbfchar\n";
    }
    $cmap .= "endcmap\nCMapName currentdict /CMap defineresource pop\nend\nend";

    $compressedFont = gzcompress($font['data']);
    $compressedCidMap = gzcompress($cidToGlyph);
    if (!is_string($compressedFont) || !is_string($compressedCidMap)) {
        throw new RuntimeException('Poppins font could not be prepared for the PDF.');
    }
    $fontDataId = membership_card_pdf_object(
        $objects,
        '<< /Length ' . strlen($compressedFont) . ' /Length1 ' . strlen($font['data'])
            . " /Filter /FlateDecode >>\nstream\n" . $compressedFont . "\nendstream"
    );
    $descriptorId = membership_card_pdf_object(
        $objects,
        '<< /Type /FontDescriptor /FontName /' . $name
            . ' /Flags 32 /FontBBox [' . implode(' ', $font['fontBox']) . ']'
            . ' /ItalicAngle 0 /Ascent ' . $font['ascent']
            . ' /Descent ' . $font['descent'] . ' /CapHeight 700 /StemV 120'
            . ' /FontFile2 ' . $fontDataId . ' 0 R >>'
    );
    $cidMapId = membership_card_pdf_object(
        $objects,
        '<< /Length ' . strlen($compressedCidMap)
            . " /Filter /FlateDecode >>\nstream\n" . $compressedCidMap . "\nendstream"
    );
    $cidFontId = membership_card_pdf_object(
        $objects,
        '<< /Type /Font /Subtype /CIDFontType2 /BaseFont /' . $name
            . ' /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >>'
            . ' /FontDescriptor ' . $descriptorId . ' 0 R /DW 500 /W ['
            . implode(' ', $widthEntries) . '] /CIDToGIDMap ' . $cidMapId . ' 0 R >>'
    );
    $cmapId = membership_card_pdf_object(
        $objects,
        '<< /Length ' . strlen($cmap) . " >>\nstream\n" . $cmap . "\nendstream"
    );
    return membership_card_pdf_object(
        $objects,
        '<< /Type /Font /Subtype /Type0 /BaseFont /' . $name
            . ' /Encoding /Identity-H /DescendantFonts [' . $cidFontId . ' 0 R]'
            . ' /ToUnicode ' . $cmapId . ' 0 R >>'
    );
}

function membership_card_pdf_object(array &$objects, string $value): int
{
    $objects[] = $value;
    return count($objects);
}

function membership_card_pdf_hex_text(array $font, string $text): string
{
    preg_match_all('/./us', $text, $characters);
    $hex = '';
    foreach ($characters[0] as $character) {
        $codepoint = mb_ord($character, 'UTF-8');
        if (!is_int($codepoint) || $codepoint > 0xFFFF) {
            $codepoint = 63;
        }
        $hex .= sprintf('%04X', $codepoint);
    }
    return $hex;
}

function membership_card_pdf_draw_text(
    array $font,
    string $text,
    string $fontName,
    float $fontSize,
    float $x,
    float $y,
    array $color
): string {
    [$red, $green, $blue] = $color;
    return sprintf(
        "%.3f %.3f %.3f rg\nBT /%s %.2f Tf %.2f %.2f Td <%s> Tj ET\n",
        $red,
        $green,
        $blue,
        $fontName,
        $fontSize,
        $x,
        $y,
        membership_card_pdf_hex_text($font, $text)
    );
}

function membership_card_pdf_image_data(string $path): array
{
    $png = file_get_contents($path);
    if (!is_string($png) || substr($png, 0, 8) !== "\x89PNG\r\n\x1a\n") {
        throw new RuntimeException('A membership card image could not be read.');
    }

    $width = 0;
    $height = 0;
    $compressed = '';
    $cursor = 8;
    $pngLength = strlen($png);
    while ($cursor + 12 <= $pngLength) {
        $chunkLength = membership_card_pdf_u32($png, $cursor);
        $chunkType = substr($png, $cursor + 4, 4);
        if ($chunkLength > $pngLength - $cursor - 12) {
            throw new RuntimeException('A membership card image is invalid.');
        }
        $chunkData = substr($png, $cursor + 8, $chunkLength);
        if ($chunkType === 'IHDR') {
            $width = membership_card_pdf_u32($chunkData, 0);
            $height = membership_card_pdf_u32($chunkData, 4);
            if (
                ord($chunkData[8]) !== 8
                || ord($chunkData[9]) !== 6
                || ord($chunkData[12]) !== 0
                || $width < 1
                || $height < 1
            ) {
                throw new RuntimeException('Membership card images must be non-interlaced 8-bit RGBA PNGs.');
            }
        } elseif ($chunkType === 'IDAT') {
            $compressed .= $chunkData;
        } elseif ($chunkType === 'IEND') {
            break;
        }
        $cursor += $chunkLength + 12;
    }

    $decoded = gzuncompress($compressed);
    $rowLength = $width * 4;
    if (!is_string($decoded) || strlen($decoded) !== ($rowLength + 1) * $height) {
        throw new RuntimeException('A membership card image could not be decoded.');
    }

    $rgb = '';
    $alpha = '';
    $previousRow = str_repeat("\0", $rowLength);
    for ($rowIndex = 0; $rowIndex < $height; $rowIndex++) {
        $rowOffset = $rowIndex * ($rowLength + 1);
        $filter = ord($decoded[$rowOffset]);
        $row = substr($decoded, $rowOffset + 1, $rowLength);
        $rgbRow = str_repeat("\0", $width * 3);
        $alphaRow = str_repeat("\0", $width);
        for ($index = 0; $index < $rowLength; $index++) {
            $left = $index >= 4 ? ord($row[$index - 4]) : 0;
            $above = ord($previousRow[$index]);
            $upperLeft = $index >= 4 ? ord($previousRow[$index - 4]) : 0;
            $predictor = match ($filter) {
                0 => 0,
                1 => $left,
                2 => $above,
                3 => intdiv($left + $above, 2),
                4 => membership_card_pdf_paeth($left, $above, $upperLeft),
                default => throw new RuntimeException('A membership card image has an invalid PNG filter.'),
            };
            $row[$index] = chr((ord($row[$index]) + $predictor) & 0xFF);
        }
        for ($pixel = 0; $pixel < $width; $pixel++) {
            $sourceOffset = $pixel * 4;
            $targetOffset = $pixel * 3;
            $rgbRow[$targetOffset] = $row[$sourceOffset];
            $rgbRow[$targetOffset + 1] = $row[$sourceOffset + 1];
            $rgbRow[$targetOffset + 2] = $row[$sourceOffset + 2];
            $alphaRow[$pixel] = $row[$sourceOffset + 3];
        }
        $rgb .= $rgbRow;
        $alpha .= $alphaRow;
        $previousRow = $row;
    }

    $rgb = gzcompress($rgb);
    $alpha = gzcompress($alpha);
    if (!is_string($rgb) || !is_string($alpha)) {
        throw new RuntimeException('A membership card image could not be prepared for the PDF.');
    }
    return ['width' => $width, 'height' => $height, 'rgb' => $rgb, 'alpha' => $alpha];
}

function membership_card_pdf_paeth(int $left, int $above, int $upperLeft): int
{
    $estimate = $left + $above - $upperLeft;
    $leftDistance = abs($estimate - $left);
    $aboveDistance = abs($estimate - $above);
    $upperLeftDistance = abs($estimate - $upperLeft);
    if ($leftDistance <= $aboveDistance && $leftDistance <= $upperLeftDistance) {
        return $left;
    }
    return $aboveDistance <= $upperLeftDistance ? $above : $upperLeft;
}

function membership_card_pdf_add_image(array &$objects, string $path): int
{
    $image = membership_card_pdf_image_data($path);
    $maskId = membership_card_pdf_object(
        $objects,
        '<< /Type /XObject /Subtype /Image /Width ' . $image['width']
            . ' /Height ' . $image['height'] . ' /ColorSpace /DeviceGray /BitsPerComponent 8'
            . ' /Filter /FlateDecode /Length ' . strlen($image['alpha'])
            . " >>\nstream\n" . $image['alpha'] . "\nendstream"
    );
    return membership_card_pdf_object(
        $objects,
        '<< /Type /XObject /Subtype /Image /Width ' . $image['width']
            . ' /Height ' . $image['height'] . ' /ColorSpace /DeviceRGB /BitsPerComponent 8'
            . ' /Filter /FlateDecode /SMask ' . $maskId . ' 0 R'
            . ' /Length ' . strlen($image['rgb']) . " >>\nstream\n" . $image['rgb'] . "\nendstream"
    );
}

function membership_card_pdf_round_path(float $x, float $y, float $width, float $height, float $radius): string
{
    $control = $radius * 0.55228475;
    return sprintf(
        "%.2f %.2f m %.2f %.2f l %.2f %.2f %.2f %.2f %.2f %.2f c "
            . "%.2f %.2f l %.2f %.2f %.2f %.2f %.2f %.2f c "
            . "%.2f %.2f l %.2f %.2f %.2f %.2f %.2f %.2f c "
            . "%.2f %.2f l %.2f %.2f %.2f %.2f %.2f %.2f c h",
        $x + $radius,
        $y,
        $x + $width - $radius,
        $y,
        $x + $width - $radius + $control,
        $y,
        $x + $width,
        $y + $radius - $control,
        $x + $width,
        $y + $radius,
        $x + $width,
        $y + $height - $radius,
        $x + $width,
        $y + $height - $radius + $control,
        $x + $width - $radius + $control,
        $y + $height,
        $x + $width - $radius,
        $y + $height,
        $x + $radius,
        $y + $height,
        $x + $radius - $control,
        $y + $height,
        $x,
        $y + $height - $radius + $control,
        $x,
        $y + $height - $radius,
        $x,
        $y + $radius,
        $x,
        $y + $radius - $control,
        $x + $radius - $control,
        $y,
        $x + $radius,
        $y
    );
}

function membership_card_pdf_curve(
    float $x1,
    float $y1,
    float $controlX1,
    float $controlY1,
    float $controlX2,
    float $controlY2,
    float $x2,
    float $y2
): string {
    return sprintf(
        "%.2f %.2f m %.2f %.2f %.2f %.2f %.2f %.2f c",
        $x1,
        $y1,
        $controlX1,
        $controlY1,
        $controlX2,
        $controlY2,
        $x2,
        $y2
    );
}

function membership_card_pdf_fit_size(array $font, string $text, float $maximumWidth, float $maximumSize): float
{
    $textWidth = membership_card_pdf_text_width($font, $text, 1);
    return $textWidth > 0 ? min($maximumSize, $maximumWidth / $textWidth) : $maximumSize;
}

function membership_card_pdf_fit_text(
    array $font,
    string $text,
    float $maximumWidth,
    float $maximumSize,
    float $minimumSize
): array {
    $size = membership_card_pdf_fit_size($font, $text, $maximumWidth, $maximumSize);
    if ($size >= $minimumSize) {
        return [$text, $size];
    }

    $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
    while (count($characters) > 3) {
        array_pop($characters);
        $shortened = implode('', $characters) . '...';
        $size = membership_card_pdf_fit_size($font, $shortened, $maximumWidth, $maximumSize);
        if ($size >= $minimumSize) {
            return [$shortened, $size];
        }
    }
    return [implode('', $characters), $minimumSize];
}

function membership_card_pdf(array $member): string
{
    $baseDirectory = dirname(__DIR__);
    $imagesDirectory = $baseDirectory . '/../images';
    $fontsDirectory = $baseDirectory . '/assets/fonts';
    $memberName = trim((string)($member['full_name'] ?? ''));
    $memberName = $memberName !== '' ? mb_strtoupper($memberName, 'UTF-8') : 'NOT PROVIDED';
    $registrationNumber = trim((string)($member['registration_number'] ?? ''));
    $registrationNumber = $registrationNumber !== '' ? $registrationNumber : 'NOT PROVIDED';
    $joinedAt = trim((string)($member['created_at'] ?? ''));
    $membershipDate = $joinedAt !== '' ? (new DateTimeImmutable($joinedAt))->format('M Y') : 'NOT PROVIDED';
    $cardTexts = [
        'HOTSPOT OPERATORS UNION',
        'MEMBERSHIP',
        'CARD',
        'ID:',
        $registrationNumber,
        'NAME:',
        $memberName,
        'SINCE:',
        strtoupper($membershipDate),
        '...',
        '?',
    ];
    $characters = [];
    foreach ($cardTexts as $text) {
        preg_match_all('/./us', $text, $matches);
        foreach ($matches[0] as $character) {
            $characters[$character] = $character;
        }
    }
    $characters = array_values($characters);

    $regularFont = membership_card_pdf_ttf($fontsDirectory . '/Poppins-Regular.ttf', $characters);
    $boldFont = membership_card_pdf_ttf($fontsDirectory . '/Poppins-ExtraBold.ttf', $characters);
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '',
    ];

    $logoId = membership_card_pdf_add_image($objects, $imagesDirectory . '/hou_logo.png');
    $avatarId = membership_card_pdf_add_image($objects, $imagesDirectory . '/user_icon.png');
    $regularFontId = membership_card_pdf_font($objects, $regularFont, 'Poppins-Regular');
    $boldFontId = membership_card_pdf_font($objects, $boldFont, 'Poppins-ExtraBold');

    $width = 792;
    $height = 500;
    $pageWidth = 85.6 * 72 / 25.4;
    $pageHeight = 53.98 * 72 / 25.4;
    $scaleX = $pageWidth / $width;
    $scaleY = $pageHeight / $height;
    $content = "q\n";
    $content .= membership_card_pdf_round_path(0, 0, $width, $height, 30) . " W n\n";
    $content .= "0.985 0.989 0.996 rg 0 0 792 500 re f\n";
    $content .= "1 1 1 rg 0 326 792 174 re f\n";
    $content .= "0.020 0.290 0.620 rg 0 150 792 176 re f\n";
    $content .= "0.91 0.94 0.97 rg 0 326 m 0 336 190 326 290 329 c "
        . "490 335 650 322 792 346 c 792 326 l h f\n";
    $content .= "q 145 0 0 145 29 343 cm /Logo Do Q\n";
    $content .= "q 145 0 0 145 29 343 cm /Logo Do Q\n";
    $brandSize = membership_card_pdf_fit_size($regularFont, 'HOTSPOT OPERATORS UNION', 420, 31);
    $content .= membership_card_pdf_draw_text(
        $regularFont,
        'HOTSPOT OPERATORS UNION',
        'F1',
        $brandSize,
        181,
        408,
        [0.05, 0.24, 0.49]
    );
    $content .= "q 120 0 0 120 637 359 cm /Avatar Do Q\n";

    $content .= membership_card_pdf_draw_text($boldFont, 'MEMBERSHIP', 'F2', 43, 70, 260, [1, 1, 1]);
    $content .= membership_card_pdf_draw_text($boldFont, 'CARD', 'F2', 43, 391, 260, [1, 0.60, 0.02]);
    $content .= "1 0.58 0.02 rg 70 238 42 4 re f\n";
    $content .= "0.25 0.68 0.95 rg 112 238 250 4 re f\n";
    $content .= membership_card_pdf_draw_text($regularFont, 'ID:', 'F1', 28, 70, 179, [1, 1, 1]);
    $registrationSize = membership_card_pdf_fit_size($boldFont, $registrationNumber, 435, 48);
    $content .= membership_card_pdf_draw_text(
        $boldFont,
        $registrationNumber,
        'F2',
        $registrationSize,
        144,
        171,
        [1, 1, 1]
    );

    $content .= "0.025 0.32 0.69 rg 376 0 m 510 0 681 22 792 157 c 792 0 l h f\n";
    $content .= "1 0.56 0.02 rg 408 0 m 544 5 701 57 792 157 c "
        . "792 176 l 701 83 547 24 408 12 c h f\n";
    $content .= "1 1 1 rg 401 0 m 535 4 691 43 792 145 c "
        . "792 151 l 692 61 535 17 401 5 c h f\n";

    $content .= membership_card_pdf_draw_text($regularFont, 'NAME:', 'F1', 18, 70, 119, [0.31, 0.36, 0.43]);
    [$displayName, $memberNameSize] = membership_card_pdf_fit_text($boldFont, $memberName, 345, 32, 19);
    $content .= membership_card_pdf_draw_text($boldFont, $displayName, 'F2', $memberNameSize, 70, 66, [0.05, 0.22, 0.47]);
    $content .= membership_card_pdf_draw_text($regularFont, 'SINCE:', 'F1', 18, 430, 119, [0.31, 0.36, 0.43]);
    $dateSize = membership_card_pdf_fit_size($boldFont, strtoupper($membershipDate), 300, 31);
    $content .= membership_card_pdf_draw_text(
        $boldFont,
        strtoupper($membershipDate),
        'F2',
        $dateSize,
        430,
        66,
        [0.05, 0.22, 0.47]
    );

    $content .= "0.72 0.75 0.79 RG 2 w\n"
        . membership_card_pdf_round_path(3, 3, 786, 494, 27) . " S\nQ\n";
    $content = sprintf("q %.8f 0 0 %.8f 0 0 cm\n", $scaleX, $scaleY) . $content . "Q\n";

    $contentId = membership_card_pdf_object(
        $objects,
        '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "endstream"
    );
    $objects[2] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '
        . sprintf('%.4f %.4f', $pageWidth, $pageHeight)
        . '] /Resources << /XObject << /Logo ' . $logoId . ' 0 R /Avatar ' . $avatarId
        . ' 0 R >> /Font << /F1 ' . $regularFontId . ' 0 R /F2 ' . $boldFontId
        . ' 0 R >> >> /Contents ' . $contentId . ' 0 R >>';

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $index => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
    }
    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    foreach (array_slice($offsets, 1) as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }
    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n"
        . "startxref\n$xrefOffset\n%%EOF\n";
    return $pdf;
}
