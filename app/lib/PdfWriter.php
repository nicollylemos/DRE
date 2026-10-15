<?php
/**
 * PdfWriter — gerador de PDF mínimo (texto Helvetica, linhas e retângulos),
 * suficiente pro relatório DRE. Substitui o pdfkit do Node, sem Composer.
 * Coordenadas em pontos, com Y contado a partir do TOPO da página (como no pdfkit).
 */
final class PdfWriter {
    public const W = 595.28, H = 841.89; // A4
    private array $pages = [];
    private string $cur = '';
    private string $font = 'F1';
    private float $size = 10;

    // Larguras (1/1000 em) dos caracteres 32..126 — AFM padrão Helvetica / Helvetica-Bold.
    private const WR = [278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584];
    private const WB = [278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,611,611,389,556,333,611,556,778,556,556,500,389,280,389,584];

    public function addPage(): void { if ($this->cur !== '') $this->pages[] = $this->cur; $this->cur = ' '; }

    public function font(bool $bold, float $size): void { $this->font = $bold ? 'F2' : 'F1'; $this->size = $size; }

    private static function rgb(string $hex): string {
        $h = ltrim($hex, '#');
        return sprintf('%.3F %.3F %.3F', hexdec(substr($h, 0, 2)) / 255, hexdec(substr($h, 2, 2)) / 255, hexdec(substr($h, 4, 2)) / 255);
    }
    private static function enc(string $s): string {
        $s = function_exists('iconv') ? (string) @iconv('UTF-8', 'Windows-1252//TRANSLIT', $s) : mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
        return strtr($s, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '', "\n" => ' ']);
    }

    public function width(string $s): float {
        $w = 0; $tab = $this->font === 'F2' ? self::WB : self::WR;
        $s = function_exists('iconv') ? (string) @iconv('UTF-8', 'Windows-1252//TRANSLIT', $s) : $s;
        for ($i = 0, $n = strlen($s); $i < $n; $i++) { $o = ord($s[$i]); $w += ($o >= 32 && $o <= 126) ? $tab[$o - 32] : 556; }
        return $w * $this->size / 1000;
    }

    public function text(float $x, float $y, string $s, string $color = '#1a1a1a'): void {
        $py = self::H - $y - $this->size; // y = topo da linha
        $this->cur .= sprintf("BT %s rg /%s %.2F Tf %.2F %.2F Td (%s) Tj ET\n", self::rgb($color), $this->font, $this->size, $x, $py, self::enc($s));
    }
    public function textRight(float $xRight, float $y, string $s, string $color = '#1a1a1a'): void { $this->text($xRight - $this->width($s), $y, $s, $color); }
    public function textCenter(float $y, string $s, string $color = '#1a1a1a'): void { $this->text((self::W - $this->width($s)) / 2, $y, $s, $color); }

    public function line(float $x1, float $y1, float $x2, float $y2, string $color = '#e0e0e0', float $w = 1): void {
        $this->cur .= sprintf("%s RG %.2F w %.2F %.2F m %.2F %.2F l S\n", self::rgb($color), $w, $x1, self::H - $y1, $x2, self::H - $y2);
    }
    public function rect(float $x, float $y, float $w, float $h, string $fill): void {
        $this->cur .= sprintf("%s rg %.2F %.2F %.2F %.2F re f\n", self::rgb($fill), $x, self::H - $y - $h, $w, $h);
    }

    public function output(): string {
        if ($this->cur !== '') { $this->pages[] = $this->cur; $this->cur = ''; }
        $objs = [];
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $kids = []; $n = 5;
        foreach ($this->pages as $content) {
            $pid = $n++; $cid = $n++;
            $kids[] = "$pid 0 R";
            $objs[$pid] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>', self::W, self::H, $cid);
            $objs[$cid] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
        }
        $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
        ksort($objs);
        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n"; $off = [];
        foreach ($objs as $i => $o) { $off[$i] = strlen($out); $out .= "$i 0 obj\n$o\nendobj\n"; }
        $xref = strlen($out);
        $out .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
        foreach ($off as $o) $out .= sprintf("%010d 00000 n \n", $o);
        return $out . 'trailer << /Size ' . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
    }
}
