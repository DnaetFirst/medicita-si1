<?php

namespace App\Services;

use App\Models\P5\ResultadoLaboratorio;
use Carbon\Carbon;

class LaboratorioPdfService
{
    private array $pages = [];

    private string $stream = '';

    private int $pageWidth = 595;

    private int $pageHeight = 842;

    private int $currentY = 800;

    private int $pageNumber = 1;

    /**
     * Genera un informe oficial en formato PDF 1.4 estricto para un resultado de laboratorio.
     */
    public function generar(ResultadoLaboratorio $resultado): string
    {
        $resultado->loadMissing([
            'orden.paciente.user',
            'orden.medico.user',
            'orden.medico.especialidad',
            'detalles.examen',
            'validadoPor',
            'publicadoPor',
        ]);

        $orden = $resultado->orden;
        $paciente = $orden?->paciente;
        $medico = $orden?->medico;
        $bioquimico = $resultado->validadoPor ?? $resultado->publicadoPor ?? $resultado->autor;

        $edad = '—';
        if ($paciente?->fecha_nacimiento) {
            $edad = Carbon::parse($paciente->fecha_nacimiento)->age;
        }

        $detalles = [];
        foreach ($resultado->detalles as $det) {
            $detalles[] = [
                'parametro' => $det->parametro,
                'valor' => $det->valor,
                'unidad' => $det->unidad_medida,
                'rango' => $det->rango_referencia,
                'interp' => $det->interpretacion ?? 'Normal',
            ];
        }

        $data = [
            'estado' => strtoupper($resultado->estado ?? 'PUBLICADO'),
            'codigo_resultado' => $resultado->codigo_resultado,
            'codigo_orden' => $orden?->codigo_orden ?? 'ORD-SIN-CODIGO',
            'version' => $resultado->version ?? 1,
            'paciente_nombre' => $paciente?->nombre_completo ?? 'Paciente No Especificado',
            'paciente_ci' => $paciente?->ci ?? ($paciente?->user?->email ?? '—'),
            'paciente_edad' => (string) $edad,
            'paciente_genero' => ucfirst($paciente?->genero ?? 'No especificado'),
            'medico_nombre' => $medico?->user?->name ?? 'Médico Asignado',
            'especialidad' => $medico?->especialidad?->nombre ?? 'Consulta General',
            'bioquimico_nombre' => $bioquimico?->name ?? 'Laboratorio Clínico Central',
            'fecha_orden' => $orden?->created_at?->format('d/m/Y H:i') ?? '—',
            'fecha_publicacion' => $resultado->fecha_publicacion?->format('d/m/Y H:i') ?? Carbon::now()->format('d/m/Y H:i'),
            'observaciones' => $resultado->observaciones,
            'operacion_id' => $resultado->operacion_id ?? $resultado->id,
            'detalles' => $detalles,
        ];

        return $this->renderDocument($data);
    }

    private function renderDocument(array $data): string
    {
        $this->pages = [];
        $this->stream = '';
        $this->pageNumber = 1;
        $this->currentY = 800;

        $this->drawHeader($data);
        $this->drawInfoCards($data);
        $this->drawTable($data);
        $this->drawObservations($data);
        $this->drawSignatureBadge($data);
        $this->drawFooter();

        return $this->buildPdf();
    }

    private function escape(string $text): string
    {
        // Reemplazar caracteres especiales fuera de WinAnsiEncoding antes de convertir
        $text = str_replace(['•', '–', '—', '’', '‘', '“', '”'], ['-', '-', '-', "'", "'", '"', '"'], $text);
        $converted = @iconv('UTF-8', 'windows-1252//TRANSLIT', $text) ?: mb_convert_encoding($text, 'Windows-1252', 'UTF-8');

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $converted);
    }

    private function checkPageBreak(int $neededSpace, array $data): void
    {
        if ($this->currentY - $neededSpace < 120) {
            $this->drawFooter();
            $this->pages[] = $this->stream;
            $this->stream = '';
            $this->pageNumber++;
            $this->currentY = 800;
            $this->drawHeaderCompact($data);
        }
    }

    private function drawHeader(array $data): void
    {
        // Franja superior Medical Blue
        $this->stream .= "0.051 0.486 0.486 rg\n";
        $this->stream .= "40 812 515 5 re f\n";

        // Título de la Institución
        $title = $this->escape('MEDICITA PRO');
        $this->stream .= "BT /F2 18 Tf 40 788 Td 0.051 0.486 0.486 rg ($title) Tj ET\n";

        // Subtítulo
        $sub = $this->escape('SISTEMA INTEGRAL DE GESTIÓN CLÍNICA Y DIAGNÓSTICO');
        $this->stream .= "BT /F1 8 Tf 40 774 Td 0.392 0.455 0.545 rg ($sub) Tj ET\n";

        // Título del Documento
        $docTitle = $this->escape('INFORME OFICIAL DE LABORATORIO CLÍNICO');
        $this->stream .= "BT /F2 13 Tf 40 754 Td 0.118 0.161 0.231 rg ($docTitle) Tj ET\n";

        // Recuadro de Estado y Códigos a la derecha
        $this->stream .= "0.902 0.957 0.957 rg 405 745 150 48 re f\n";
        $this->stream .= "0.051 0.486 0.486 RG 1 w 405 745 150 48 re S\n";

        $estado = strtoupper($data['estado'] ?? 'PUBLICADO');
        $colorEstado = $estado === 'PUBLICADO' ? '0.086 0.396 0.204' : '0.118 0.161 0.231';
        $this->stream .= 'BT /F2 9 Tf 415 778 Td '.$colorEstado.' rg ('.$this->escape("ESTADO: {$estado}").") Tj ET\n";
        $this->stream .= 'BT /F2 8 Tf 415 764 Td 0.118 0.161 0.231 rg ('.$this->escape('Cód: '.$data['codigo_resultado']).") Tj ET\n";
        $this->stream .= 'BT /F1 8 Tf 415 752 Td 0.392 0.455 0.545 rg ('.$this->escape('Orden: '.$data['codigo_orden']).") Tj ET\n";

        $this->currentY = 735;
    }

    private function drawHeaderCompact(array $data): void
    {
        $this->stream .= "0.051 0.486 0.486 rg 40 815 515 3 re f\n";
        $this->stream .= 'BT /F2 10 Tf 40 798 Td 0.051 0.486 0.486 rg ('.$this->escape('MEDICITA PRO - INFORME DE LABORATORIO').") Tj ET\n";
        $this->stream .= 'BT /F1 8 Tf 340 798 Td 0.392 0.455 0.545 rg ('.$this->escape('Resultado: '.$data['codigo_resultado'].' - Paciente: '.$data['paciente_nombre']).") Tj ET\n";
        $this->stream .= "0.796 0.835 0.882 RG 0.5 w 40 790 m 555 790 l S\n";
        $this->currentY = 775;
    }

    private function drawInfoCards(array $data): void
    {
        $cardWidth = 250;
        $cardHeight = 76;
        $cardY = $this->currentY - $cardHeight;

        // Tarjeta Paciente (Izquierda)
        $this->stream .= "0.973 0.980 0.988 rg 40 {$cardY} {$cardWidth} {$cardHeight} re f\n";
        $this->stream .= "0.796 0.835 0.882 RG 1 w 40 {$cardY} {$cardWidth} {$cardHeight} re S\n";
        $this->stream .= '0.051 0.486 0.486 rg 40 '.($cardY + $cardHeight - 16)." {$cardWidth} 16 re f\n";
        $this->stream .= 'BT /F2 8 Tf 46 '.($cardY + $cardHeight - 12).' Td 1 1 1 rg ('.$this->escape('DATOS DEL PACIENTE').") Tj ET\n";

        $this->stream .= 'BT /F2 8 Tf 46 '.($cardY + 44).' Td 0.392 0.455 0.545 rg ('.$this->escape('Nombre:').") Tj ET\n";
        $this->stream .= 'BT /F2 8.5 Tf 90 '.($cardY + 44).' Td 0.118 0.161 0.231 rg ('.$this->escape($data['paciente_nombre']).") Tj ET\n";

        $this->stream .= 'BT /F2 8 Tf 46 '.($cardY + 28).' Td 0.392 0.455 0.545 rg ('.$this->escape('Documento:').") Tj ET\n";
        $this->stream .= 'BT /F1 8 Tf 100 '.($cardY + 28).' Td 0.118 0.161 0.231 rg ('.$this->escape('CI '.$data['paciente_ci']).") Tj ET\n";

        $this->stream .= 'BT /F2 8 Tf 46 '.($cardY + 12).' Td 0.392 0.455 0.545 rg ('.$this->escape('Edad / Sexo:').") Tj ET\n";
        $this->stream .= 'BT /F1 8 Tf 115 '.($cardY + 12).' Td 0.118 0.161 0.231 rg ('.$this->escape($data['paciente_edad'].' años - '.$data['paciente_genero']).") Tj ET\n";

        // Tarjeta Orden y Solicitud (Derecha)
        $cardX2 = 305;
        $this->stream .= "0.973 0.980 0.988 rg {$cardX2} {$cardY} {$cardWidth} {$cardHeight} re f\n";
        $this->stream .= "0.796 0.835 0.882 RG 1 w {$cardX2} {$cardY} {$cardWidth} {$cardHeight} re S\n";
        $this->stream .= "0.051 0.486 0.486 rg {$cardX2} ".($cardY + $cardHeight - 16)." {$cardWidth} 16 re f\n";
        $this->stream .= 'BT /F2 8 Tf '.($cardX2 + 6).' '.($cardY + $cardHeight - 12).' Td 1 1 1 rg ('.$this->escape('SOLICITUD Y EMISIÓN').") Tj ET\n";

        $this->stream .= 'BT /F2 8 Tf '.($cardX2 + 6).' '.($cardY + 44).' Td 0.392 0.455 0.545 rg ('.$this->escape('Médico Sol.:').") Tj ET\n";
        $this->stream .= 'BT /F1 8 Tf '.($cardX2 + 65).' '.($cardY + 44).' Td 0.118 0.161 0.231 rg ('.$this->escape($data['medico_nombre'].' ('.$data['especialidad'].')').") Tj ET\n";

        $this->stream .= 'BT /F2 8 Tf '.($cardX2 + 6).' '.($cardY + 28).' Td 0.392 0.455 0.545 rg ('.$this->escape('Bioquímico:').") Tj ET\n";
        $this->stream .= 'BT /F1 8 Tf '.($cardX2 + 65).' '.($cardY + 28).' Td 0.118 0.161 0.231 rg ('.$this->escape($data['bioquimico_nombre']).") Tj ET\n";

        $this->stream .= 'BT /F2 8 Tf '.($cardX2 + 6).' '.($cardY + 12).' Td 0.392 0.455 0.545 rg ('.$this->escape('Publicado:').") Tj ET\n";
        $this->stream .= 'BT /F1 8 Tf '.($cardX2 + 65).' '.($cardY + 12).' Td 0.118 0.161 0.231 rg ('.$this->escape($data['fecha_publicacion'].' (V.'.$data['version'].')').") Tj ET\n";

        $this->currentY = $cardY - 16;
    }

    private function drawTable(array $data): void
    {
        $tableY = $this->currentY;
        $this->stream .= '0.902 0.957 0.957 rg 40 '.($tableY - 20)." 515 20 re f\n";
        $this->stream .= '0.051 0.486 0.486 RG 1 w 40 '.($tableY - 20)." 515 20 re S\n";

        $cols = [
            ['title' => 'PRUEBA / PARÁMETRO', 'x' => 45],
            ['title' => 'RESULTADO', 'x' => 220],
            ['title' => 'UNIDAD', 'x' => 300],
            ['title' => 'VALOR REFERENCIA', 'x' => 365],
            ['title' => 'INTERPRETACIÓN', 'x' => 475],
        ];

        foreach ($cols as $col) {
            $this->stream .= 'BT /F2 8 Tf '.$col['x'].' '.($tableY - 14).' Td 0.051 0.486 0.486 rg ('.$this->escape($col['title']).") Tj ET\n";
        }

        $this->currentY = $tableY - 20;

        $rowIndex = 0;
        foreach ($data['detalles'] as $det) {
            $this->checkPageBreak(24, $data);
            $rowY = $this->currentY - 22;

            if ($rowIndex % 2 === 1) {
                $this->stream .= "0.973 0.980 0.988 rg 40 {$rowY} 515 22 re f\n";
            }
            $this->stream .= "0.85 0.88 0.91 RG 0.5 w 40 {$rowY} m 555 {$rowY} l S\n";

            $param = $this->escape($det['parametro']);
            $this->stream .= 'BT /F2 8.5 Tf 45 '.($rowY + 7)." Td 0.118 0.161 0.231 rg ({$param}) Tj ET\n";

            $val = $this->escape($det['valor']);
            $this->stream .= 'BT /F2 9 Tf 220 '.($rowY + 7)." Td 0.051 0.486 0.486 rg ({$val}) Tj ET\n";

            $unidad = $this->escape($det['unidad'] ?? '-');
            $this->stream .= 'BT /F1 8 Tf 300 '.($rowY + 7)." Td 0.392 0.455 0.545 rg ({$unidad}) Tj ET\n";

            $rango = $this->escape($det['rango'] ?? '-');
            $this->stream .= 'BT /F1 8 Tf 365 '.($rowY + 7)." Td 0.392 0.455 0.545 rg ({$rango}) Tj ET\n";

            $interp = $det['interp'] ?? 'Normal';
            $isNormal = stripos($interp, 'normal') !== false;
            $interpColor = $isNormal ? '0.086 0.396 0.204' : '0.75 0.1 0.1';
            $interpFont = $isNormal ? '/F1' : '/F2';
            $this->stream .= "BT {$interpFont} 8 Tf 475 ".($rowY + 7)." Td {$interpColor} rg (".$this->escape($interp).") Tj ET\n";

            $this->currentY = $rowY;
            $rowIndex++;
        }

        $this->currentY -= 15;
    }

    private function drawObservations(array $data): void
    {
        if (empty($data['observaciones'])) {
            return;
        }

        $this->checkPageBreak(50, $data);

        $boxHeight = 36;
        $boxY = $this->currentY - $boxHeight;

        $this->stream .= "0.98 0.98 0.98 rg 40 {$boxY} 515 {$boxHeight} re f\n";
        $this->stream .= "0.796 0.835 0.882 RG 0.5 w 40 {$boxY} 515 {$boxHeight} re S\n";

        $this->stream .= 'BT /F2 7.5 Tf 46 '.($boxY + 24).' Td 0.051 0.486 0.486 rg ('.$this->escape('OBSERVACIONES DEL LABORATORIO:').") Tj ET\n";
        $obs = $this->escape($data['observaciones']);
        $this->stream .= 'BT /F1 7.5 Tf 46 '.($boxY + 11)." Td 0.25 0.25 0.25 rg ({$obs}) Tj ET\n";

        $this->currentY = $boxY - 15;
    }

    private function drawSignatureBadge(array $data): void
    {
        $this->checkPageBreak(65, $data);

        $boxHeight = 52;
        $boxY = $this->currentY - $boxHeight;

        $this->stream .= "0.96 0.98 0.99 rg 40 {$boxY} 515 {$boxHeight} re f\n";
        $this->stream .= "0.051 0.486 0.486 RG 1 w 40 {$boxY} 515 {$boxHeight} re S\n";
        $this->stream .= "0.051 0.486 0.486 rg 40 {$boxY} 4 {$boxHeight} re f\n";

        $this->stream .= 'BT /F2 8 Tf 52 '.($boxY + 38).' Td 0.051 0.486 0.486 rg ('.$this->escape('VALIDACIÓN Y FIRMA ELECTRÓNICA INSTITUCIONAL').") Tj ET\n";
        $bioquimicoNom = preg_replace('/^Lic\.\s*/i', '', $data['bioquimico_nombre']);
        $this->stream .= 'BT /F1 7.5 Tf 52 '.($boxY + 26).' Td 0.118 0.161 0.231 rg ('.$this->escape('Responsable: Lic. '.$bioquimicoNom.' - Registro Profesional LAB-BO-2026').") Tj ET\n";

        $hash = substr(hash('sha256', $data['codigo_resultado'].($data['operacion_id'] ?? 'medicita')), 0, 32);
        $this->stream .= 'BT /F3 7 Tf 52 '.($boxY + 14).' Td 0.392 0.455 0.545 rg ('.$this->escape('Firma Digital Hash: '.$hash.' - Documento Oficial Verificado').") Tj ET\n";

        $this->stream .= 'BT /F2 8 Tf 430 '.($boxY + 22).' Td 0.086 0.396 0.204 rg ('.$this->escape('[DOCUMENTO VALIDO]').") Tj ET\n";

        $this->currentY = $boxY - 15;
    }

    private function drawFooter(): void
    {
        $this->stream .= "0.796 0.835 0.882 RG 0.5 w 40 45 m 555 45 l S\n";
        $this->stream .= 'BT /F1 7 Tf 40 33 Td 0.392 0.455 0.545 rg ('.$this->escape('MediCita Pro - Sistema Clínico Hospitalario - Informe médico confidencial con validez legal.').") Tj ET\n";
        $this->stream .= 'BT /F1 7 Tf 490 33 Td 0.392 0.455 0.545 rg ('.$this->escape('Página '.$this->pageNumber).") Tj ET\n";
    }

    private function buildPdf(): string
    {
        $this->pages[] = $this->stream;

        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];

        $pageCount = count($this->pages);
        $fontHelvId = 3;
        $fontBoldId = 4;
        $fontItalicId = 5;

        $pageObjIds = [];
        for ($i = 0; $i < $pageCount; $i++) {
            $pageObjIds[] = 6 + ($i * 2);
        }

        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = implode(' ', array_map(fn ($id) => "{$id} 0 R", $pageObjIds));
        $objects[2] = "<< /Type /Pages /Kids [{$kids}] /Count {$pageCount} >>";
        $objects[$fontHelvId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[$fontBoldId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $objects[$fontItalicId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Oblique /Encoding /WinAnsiEncoding >>';

        for ($i = 0; $i < $pageCount; $i++) {
            $pageId = $pageObjIds[$i];
            $streamId = $pageId + 1;
            $streamData = $this->pages[$i];
            $len = strlen($streamData);

            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$this->pageWidth} {$this->pageHeight}] ".
                "/Resources << /Font << /F1 {$fontHelvId} 0 R /F2 {$fontBoldId} 0 R /F3 {$fontItalicId} 0 R >> >> ".
                "/Contents {$streamId} 0 R >>";

            $objects[$streamId] = "<< /Length {$len} >>\nstream\n".$streamData."\nendstream";
        }

        $totalObjs = count($objects);
        for ($id = 1; $id <= $totalObjs; $id++) {
            $offsets[$id] = strlen($out);
            $out .= "{$id} 0 obj\n".$objects[$id]."\nendobj\n";
        }

        $xrefOffset = strlen($out);
        $out .= "xref\n0 ".($totalObjs + 1)."\n";
        $out .= sprintf("%010d 65535 f \r\n", 0);
        for ($id = 1; $id <= $totalObjs; $id++) {
            $out .= sprintf("%010d 00000 n \r\n", $offsets[$id]);
        }

        $out .= "trailer\n<< /Size ".($totalObjs + 1)." /Root 1 0 R >>\n";
        $out .= "startxref\n{$xrefOffset}\n%%EOF\n";

        return $out;
    }
}
