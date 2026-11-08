<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Genera reportes analíticos en formato PDF binario (PDF 1.4 ISO nativo).
 * No depende de librerías externas; misma técnica que LaboratorioPdfService.
 */
class ReportePdfService
{
    private array $pages = [];

    private string $stream = '';

    private int $pageWidth = 842;   // A4 Landscape

    private int $pageHeight = 595;

    private int $currentY = 560;

    private int $pageNumber = 1;

    /**
     * Genera PDF binario para el reporte de filtros (Tipo 1).
     *
     * @param  array<string, mixed>  $kpis
     * @param  array<int, array<string, mixed>>  $filas
     * @param  array<string, mixed>  $filtros
     * @param  list<string>  $columnas
     */
    public function generarReporteFiltros(
        string $titulo,
        array $kpis,
        array $filas,
        array $filtros,
        array $columnas
    ): string {
        $this->reset();
        $this->drawPageHeader($titulo, $filtros);
        $this->drawKpis($kpis);
        $this->drawTable($columnas, $filas, $titulo);
        $this->drawFooter();

        return $this->buildPdf();
    }

    /**
     * Genera PDF binario para el constructor personalizado (Tipo 2).
     *
     * @param  list<string>  $columnas
     * @param  list<array<string, mixed>>  $filas
     */
    public function generarReportePersonalizado(
        string $titulo,
        array $columnas,
        array $filas,
        string $agrupacion
    ): string {
        $this->reset();
        $subtitulo = "Agrupado por: {$agrupacion} · ".count($filas).' registros';
        $this->drawPageHeader($titulo, [], $subtitulo);
        $this->drawTable($columnas, $filas, $titulo);
        $this->drawFooter();

        return $this->buildPdf();
    }

    /**
     * Genera PDF binario para resultado de consulta IA (Tipo 3).
     *
     * @param  list<string>  $columnas
     * @param  list<array<string, mixed>>  $filas
     */
    public function generarReporteIa(
        string $titulo,
        string $pregunta,
        array $columnas,
        array $filas
    ): string {
        $this->reset();
        $subtitulo = mb_substr($pregunta, 0, 120).(mb_strlen($pregunta) > 120 ? '…' : '');
        $this->drawPageHeader($titulo, [], "Consulta IA: {$subtitulo}");
        $this->drawTable($columnas, $filas, $titulo);
        $this->drawFooter();

        return $this->buildPdf();
    }

    // ──────────────────────────────────────────────────────────────────
    // Helpers de dibujado
    // ──────────────────────────────────────────────────────────────────

    private function reset(): void
    {
        $this->pages = [];
        $this->stream = '';
        $this->pageNumber = 1;
        $this->currentY = 560;
    }

    /** @param array<string, mixed> $filtros */
    private function drawPageHeader(string $titulo, array $filtros = [], string $subtitulo = ''): void
    {
        $fecha = Carbon::now()->format('d/m/Y H:i');
        // Franja Medical Blue
        $this->stream .= "0.051 0.486 0.486 rg\n0 570 842 25 re f\n";
        $this->stream .= "BT /F1 10 Tf 1 1 1 rg 12 578 Td ({$this->esc('MediCita Pro — Reporte Analítico')}) Tj ET\n";
        $this->stream .= "BT /F1 7 Tf 1 1 1 rg 650 578 Td ({$this->esc($fecha)}) Tj ET\n";
        // Título principal
        $this->stream .= "BT /F2 13 Tf 0.055 0.388 0.388 rg 12 548 Td ({$this->esc($titulo)}) Tj ET\n";
        $this->currentY = 538;
        if ($subtitulo !== '') {
            $this->stream .= "BT /F1 8 Tf 0.28 0.28 0.28 rg 12 {$this->currentY} Td ({$this->esc($subtitulo)}) Tj ET\n";
            $this->currentY -= 12;
        }
        // Filtros aplicados
        if (! empty($filtros)) {
            $partes = [];
            if (! empty($filtros['fecha_inicio'])) {
                $partes[] = 'Desde: '.$filtros['fecha_inicio'];
            }
            if (! empty($filtros['fecha_fin'])) {
                $partes[] = 'Hasta: '.$filtros['fecha_fin'];
            }
            if (! empty($filtros['estado'])) {
                $partes[] = 'Estado: '.ucfirst(str_replace('_', ' ', $filtros['estado']));
            }
            if (! empty($partes)) {
                $linea = implode('  ·  ', $partes);
                $this->stream .= "BT /F1 7 Tf 0.4 0.4 0.4 rg 12 {$this->currentY} Td ({$this->esc($linea)}) Tj ET\n";
                $this->currentY -= 12;
            }
        }
        // Línea separadora
        $this->currentY -= 4;
        $this->stream .= "0.055 0.388 0.388 rg\n12 {$this->currentY} 818 1 re f\n";
        $this->currentY -= 10;
    }

    /** @param array<string, mixed> $kpis */
    private function drawKpis(array $kpis): void
    {
        $items = [
            ['Total Citas', (string) ($kpis['total_citas'] ?? 0)],
            ['Atendidas', ($kpis['completadas'] ?? 0).' ('.($kpis['tasa_asistencia'] ?? 0).'%)'],
            ['Canceladas', ($kpis['canceladas'] ?? 0).' ('.($kpis['tasa_cancelacion'] ?? 0).'%)'],
            ['Inasistencias', ($kpis['inasistencias'] ?? 0).' ('.($kpis['tasa_no_show'] ?? 0).'%)'],
            ['Ocupación Slots', ($kpis['tasa_ocupacion'] ?? 0).'%'],
            ['Cupos Recup.', (string) ($kpis['cupos_recuperados'] ?? 0)],
        ];

        $cardW = 130;
        $x = 12;
        $y = $this->currentY;

        foreach ($items as $item) {
            // Fondo
            $this->stream .= "0.97 0.98 0.99 rg {$x} ".($y - 36)." {$cardW} 38 re f\n";
            $this->stream .= "0.8 0.85 0.9 rg {$x} ".($y - 36)." {$cardW} 38 re S\n";
            $this->stream .= 'BT /F1 6 Tf 0.3 0.3 0.3 rg '.($x + 4).' '.($y - 10)." Td ({$this->esc($item[0])}) Tj ET\n";
            $this->stream .= 'BT /F2 10 Tf 0.055 0.388 0.388 rg '.($x + 4).' '.($y - 24)." Td ({$this->esc($item[1])}) Tj ET\n";
            $x += $cardW + 6;
        }

        $this->currentY = $y - 46;
    }

    /**
     * @param  list<string>  $columnas
     * @param  list<array<string, mixed>>  $filas
     */
    private function drawTable(array $columnas, array $filas, string $titulo): void
    {
        if (empty($columnas)) {
            return;
        }

        $this->currentY -= 6;
        $colCount = count($columnas);
        $usable = 818;
        $colW = max(60, (int) floor($usable / $colCount));

        // Cabecera de tabla
        $this->stream .= "0.055 0.388 0.388 rg\n12 ".($this->currentY - 14)." {$usable} 16 re f\n";
        $x = 12;
        foreach ($columnas as $col) {
            $label = mb_strtoupper(str_replace('_', ' ', (string) $col));
            $label = mb_substr($label, 0, 18);
            $this->stream .= 'BT /F2 6 Tf 1 1 1 rg '.($x + 2).' '.($this->currentY - 10)." Td ({$this->esc($label)}) Tj ET\n";
            $x += $colW;
        }
        $this->currentY -= 16;

        // Filas
        $rowNum = 0;
        foreach ($filas as $fila) {
            if ($this->currentY < 30) {
                $this->drawFooter();
                $this->pages[] = $this->stream;
                $this->stream = '';
                $this->pageNumber++;
                $this->currentY = 560;
                $this->drawPageHeader($titulo);
                // Re-dibujar cabecera de tabla
                $this->stream .= "0.055 0.388 0.388 rg\n12 ".($this->currentY - 14)." {$usable} 16 re f\n";
                $x = 12;
                foreach ($columnas as $col) {
                    $label = mb_strtoupper(str_replace('_', ' ', (string) $col));
                    $label = mb_substr($label, 0, 18);
                    $this->stream .= 'BT /F2 6 Tf 1 1 1 rg '.($x + 2).' '.($this->currentY - 10)." Td ({$this->esc($label)}) Tj ET\n";
                    $x += $colW;
                }
                $this->currentY -= 16;
                $rowNum = 0;
            }

            $bg = $rowNum % 2 === 0 ? '0.98 0.99 0.99' : '1 1 1';
            $this->stream .= "{$bg} rg\n12 ".($this->currentY - 12)." {$usable} 13 re f\n";
            $this->stream .= "0.88 0.9 0.92 rg\n12 ".($this->currentY - 12)." {$usable} 13 re S\n";

            $x = 12;
            foreach ($columnas as $col) {
                $val = isset($fila[$col]) && $fila[$col] !== null ? (string) $fila[$col] : '—';
                $val = mb_substr($val, 0, 25);
                $this->stream .= 'BT /F1 6 Tf 0.1 0.1 0.1 rg '.($x + 2).' '.($this->currentY - 9)." Td ({$this->esc($val)}) Tj ET\n";
                $x += $colW;
            }
            $this->currentY -= 13;
            $rowNum++;
        }

        if (empty($filas)) {
            $this->stream .= "BT /F1 8 Tf 0.4 0.4 0.4 rg 300 {$this->currentY} Td (Sin datos para los filtros seleccionados.) Tj ET\n";
            $this->currentY -= 14;
        }
    }

    private function drawFooter(): void
    {
        $this->stream .= "0.055 0.388 0.388 rg\n12 8 818 1 re f\n";
        $this->stream .= "BT /F1 6 Tf 0.3 0.3 0.3 rg 12 2 Td (MediCita Pro - Reporte Generado Automaticamente - Pagina {$this->pageNumber}) Tj ET\n";
    }

    private function esc(string $text): string
    {
        $search = ["\xe2\x80\xa2", "\xe2\x80\x93", "\xe2\x80\x94", "\xe2\x80\x98", "\xe2\x80\x99", "\xe2\x80\x9c", "\xe2\x80\x9d"];
        $replace = ['-', '-', '-', "'", "'", '"', '"'];
        $text = str_replace($search, $replace, $text);
        $converted = @iconv('UTF-8', 'windows-1252//TRANSLIT', $text) ?: mb_convert_encoding($text, 'Windows-1252', 'UTF-8');

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string) $converted);
    }

    // ──────────────────────────────────────────────────────────────────
    // Construcción del PDF binario (PDF 1.4 structure)
    // ──────────────────────────────────────────────────────────────────

    private function buildPdf(): string
    {
        $this->pages[] = $this->stream;
        $totalPages = count($this->pages);

        $objects = [];
        $offsets = [];
        $obj = 1;

        // 1 - Catalog, 2 - Pages, 3 - Font1 (Helvetica), 4 - Font2 (Helvetica-Bold)
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = ''; // fill after pages
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $pageObjIds = [];
        $contentObjIds = [];
        $nextObj = 5;

        for ($i = 0; $i < $totalPages; $i++) {
            $pageObjIds[$i] = $nextObj++;
            $contentObjIds[$i] = $nextObj++;
        }

        $pageKids = implode(' ', array_map(fn ($id) => "{$id} 0 R", $pageObjIds));
        $objects[2] = "<< /Type /Pages /Kids [{$pageKids}] /Count {$totalPages} >>";

        for ($i = 0; $i < $totalPages; $i++) {
            $contentStream = $this->pages[$i];
            $contentLen = strlen($contentStream);
            $objects[$contentObjIds[$i]] = "<< /Length {$contentLen} >>\nstream\n{$contentStream}\nendstream";
            $objects[$pageObjIds[$i]] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$this->pageWidth} {$this->pageHeight}] "
                .'/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> '
                ."/Contents {$contentObjIds[$i]} 0 R >>";
        }

        $pdf = "%PDF-1.4\n%\xe2\xe3\xcf\xd3\n";
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $objCount = max(array_keys($objects)) + 1;
        $pdf .= "xref\n0 {$objCount}\n0000000000 65535 f \n";
        for ($id = 1; $id < $objCount; $id++) {
            $off = isset($offsets[$id]) ? str_pad((string) $offsets[$id], 10, '0', STR_PAD_LEFT) : '0000000000';
            $flag = isset($offsets[$id]) ? 'n' : 'f';
            $pdf .= "{$off} 00000 {$flag} \n";
        }
        $pdf .= "trailer\n<< /Size {$objCount} /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }
}
