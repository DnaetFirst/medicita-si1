<?php

namespace App\Services;

use RuntimeException;

class AnalizadorSqlReporte
{
    public const VISTAS = [
        'vista_reporte_medicos' => ['medico_id', 'medico_nombre', 'medico_email', 'especialidad_id', 'especialidad_nombre', 'especialidad_codigo', 'nro_registro_medico', 'telefono_contacto', 'medico_estado', 'biografia'],
        'vista_reporte_citas' => ['cita_id', 'codigo_ficha', 'fecha_cita', 'hora_cita', 'estado', 'motivo_cancelacion', 'cancelado_por_user_id', 'es_cupo_recuperado', 'hora_confirmacion', 'hora_llegada', 'hora_inicio_atencion', 'hora_fin_atencion', 'medico_id', 'medico_nombre', 'especialidad_id', 'especialidad_nombre', 'paciente_id', 'paciente_nombre', 'paciente_ci', 'created_at'],
        'vista_reporte_ocupacion' => ['fecha', 'medico_id', 'medico_nombre', 'especialidad_id', 'especialidad_nombre', 'total_slots', 'slots_reservados', 'slots_disponibles', 'slots_bloqueados'],
        'vista_reporte_cancelaciones' => ['fecha_cita', 'estado', 'motivo_cancelacion', 'es_cupo_recuperado', 'especialidad_id', 'especialidad_nombre', 'medico_id', 'medico_nombre', 'total_citas'],
        'vista_reporte_movimientos' => ['movimiento_id', 'cita_id', 'operacion', 'estado_anterior', 'estado_nuevo', 'fecha_evento', 'fecha_anterior', 'fecha_nueva', 'medico_id', 'medico_nombre', 'especialidad_id', 'especialidad_nombre'],
        'vista_reporte_pacientes' => ['paciente_id', 'paciente_nombre', 'activo', 'fecha_registro'],
        'vista_reporte_espera' => ['lista_id', 'paciente_id', 'especialidad_id', 'especialidad_nombre', 'medico_id', 'fecha_inscripcion', 'fecha_desde', 'fecha_hasta', 'estado'],
        'vista_reporte_ofertas' => ['oferta_id', 'lista_id', 'paciente_id', 'estado', 'fecha_oferta', 'fecha_vencimiento', 'respondida_at', 'fecha_turno', 'medico_id', 'especialidad_id', 'especialidad_nombre'],
        'vista_reporte_laboratorio' => ['orden_id', 'paciente_id', 'medico_id', 'especialidad_id', 'especialidad_nombre', 'estado', 'fecha_emision'],
    ];

    /** @var list<string> */
    private array $tokens = [];

    /** @var list<string> */
    private array $referencias = [];

    /** @var list<string> */
    private array $aliases = [];

    private int $posicion = 0;

    private int $profundidad = 0;

    /** @return array{sql: string, columnas: list<string>} */
    public function analizar(string $sql, int $maximo = 100): array
    {
        $sql = preg_replace('/;\s*$/', '', trim($sql));
        $estructura = preg_replace('/\'(?:\'\'|[^\'])*\'|"(?:""|[^"])*"/', "'literal'", $sql);
        if (strlen($sql) > 12000 || preg_match('/(--|\/\*|\*\/|;|#)/', $estructura)) {
            throw new RuntimeException('Solo se admite una consulta SELECT sin comentarios.');
        }
        preg_match_all('/\G\s*(\'(?:\'\'|[^\'])*\'|"(?:""|[^"])*"|[a-zA-Z_][a-zA-Z0-9_]*|\d+(?:\.\d+)?|>=|<=|<>|!=|\|\||[(),.*+\/%=<>-])/', $sql, $partes);
        if (trim(implode('', $partes[0])) !== $sql) {
            throw new RuntimeException('La consulta contiene caracteres o sintaxis no permitidos.');
        }
        $this->tokens = $partes[1];
        $this->posicion = $this->profundidad = 0;
        $this->referencias = $this->aliases = [];
        if (count($this->tokens) > 1200) {
            throw new RuntimeException('La consulta es demasiado compleja. Reduce los filtros.');
        }
        $this->exigir('SELECT');
        $this->aceptar('DISTINCT');
        $columnas = [];
        do {
            $inicio = $this->posicion;
            $this->expresion();
            $columna = implode(' ', array_slice($this->tokens, $inicio, $this->posicion - $inicio));
            if ($this->aceptar('AS')) {
                $columna = $this->identificador();
                $this->aliases[] = strtolower($columna);
            }
            $columnas[] = $columna;
        } while ($this->aceptar(','));
        $this->exigir('FROM');
        $vista = strtolower($this->identificador());
        if (! isset(self::VISTAS[$vista])) {
            throw new RuntimeException('Por confidencialidad clínica solo se permiten las vistas analíticas autorizadas.');
        }
        $aliasVista = $vista;
        if ($this->aceptar('AS')) {
            $aliasVista = strtolower($this->identificador());
        } elseif ($this->actual() && ! in_array($this->actual(), ['WHERE', 'GROUP', 'HAVING', 'ORDER', 'LIMIT'])) {
            $aliasVista = strtolower($this->identificador());
        }
        if ($this->aceptar('WHERE')) {
            $this->expresion();
        }
        if ($this->aceptar('GROUP')) {
            $this->exigir('BY');
            do {
                $this->expresion();
            } while ($this->aceptar(','));
        }
        if ($this->aceptar('HAVING')) {
            $this->expresion();
        }
        if ($this->aceptar('ORDER')) {
            $this->exigir('BY');
            do {
                $this->expresion();
                if (! $this->aceptar('ASC')) {
                    $this->aceptar('DESC');
                }
            } while ($this->aceptar(','));
        }
        $fin = $this->posicion;
        $limite = $maximo;
        if ($this->aceptar('LIMIT')) {
            $numero = $this->tokens[$this->posicion++] ?? '';
            if (! ctype_digit($numero)) {
                throw new RuntimeException('LIMIT debe ser un entero positivo.');
            }
            $limite = min($maximo, max(1, (int) $numero));
        }
        if ($this->posicion !== count($this->tokens)) {
            throw new RuntimeException('Usa una única vista, sin subconsultas, JOIN, UNION ni comandos adicionales.');
        }
        foreach ($this->referencias as $referencia) {
            $segmentos = explode('.', strtolower($referencia));
            $columna = array_pop($segmentos);
            if (($segmentos && $segmentos !== [$aliasVista] && $segmentos !== [$vista]) || ! in_array($columna, [...self::VISTAS[$vista], ...$this->aliases, '*'])) {
                throw new RuntimeException('Columna no disponible en '.$vista.': '.$referencia);
            }
        }

        return ['sql' => implode(' ', array_slice($this->tokens, 0, $fin)).' LIMIT '.$limite, 'columnas' => $columnas === ['*'] ? self::VISTAS[$vista] : $columnas];
    }

    private function expresion(int $minimo = 0): void
    {
        if (++$this->profundidad > 30) {
            throw new RuntimeException('La consulta supera la profundidad permitida.');
        }
        $token = $this->actual();
        if ($this->aceptar('(')) {
            $this->expresion();
            $this->exigir(')');
        } elseif ($this->aceptar('NOT') || $this->aceptar('-') || $this->aceptar('+')) {
            $this->expresion(6);
        } elseif ($this->aceptar('CASE')) {
            do {
                $this->exigir('WHEN');
                $this->expresion();
                $this->exigir('THEN');
                $this->expresion();
            } while ($this->actual() === 'WHEN');
            if ($this->aceptar('ELSE')) {
                $this->expresion();
            }
            $this->exigir('END');
        } elseif (in_array($token, ['NULL', 'TRUE', 'FALSE']) || preg_match('/^(?:\d|\')/', $this->tokens[$this->posicion] ?? '')) {
            $this->posicion++;
        } elseif ($this->aceptar('*')) {
            $this->referencias[] = '*';
        } else {
            $nombre = $this->identificador();
            if ($this->aceptar('(')) {
                if (strtoupper($nombre) === 'CAST') {
                    $this->expresion();
                    $this->exigir('AS');
                    if (! in_array(strtoupper($this->identificador()), ['TEXT', 'INTEGER', 'REAL', 'NUMERIC'])) {
                        throw new RuntimeException('Tipo de conversión no autorizado.');
                    }
                } else {
                    if (! in_array(strtoupper($nombre), ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX', 'ROUND', 'COALESCE', 'NULLIF', 'LOWER', 'UPPER', 'DATE', 'ABS', 'SUBSTR'])) {
                        throw new RuntimeException('Función no autorizada: '.$nombre);
                    }
                    $this->aceptar('DISTINCT');
                    do {
                        $this->expresion();
                    } while ($this->aceptar(','));
                }
                $this->exigir(')');
            } else {
                if ($this->aceptar('.')) {
                    $nombre .= '.'.($this->aceptar('*') ? '*' : $this->identificador());
                }
                $this->referencias[] = $nombre;
            }
        }
        $precedencias = ['OR' => 1, 'AND' => 2, '=' => 3, '!=' => 3, '<>' => 3, '<' => 3, '>' => 3, '<=' => 3, '>=' => 3, 'LIKE' => 3, 'IS' => 3, 'IN' => 3, 'BETWEEN' => 3, '+' => 4, '-' => 4, '||' => 4, '*' => 5, '/' => 5, '%' => 5];
        while (($precedencia = $precedencias[$this->actual() ?? ''] ?? -1) >= $minimo) {
            $operador = $this->actual();
            $this->posicion++;
            if ($operador === 'IS') {
                $this->aceptar('NOT');
                $this->exigir('NULL');
            } elseif ($operador === 'IN') {
                $this->exigir('(');
                do {
                    $this->expresion(4);
                } while ($this->aceptar(','));
                $this->exigir(')');
            } elseif ($operador === 'BETWEEN') {
                $this->expresion(4);
                $this->exigir('AND');
                $this->expresion(4);
            } else {
                $this->expresion($precedencia + 1);
            }
        }
        $this->profundidad--;
    }

    private function identificador(): string
    {
        $nombre = $this->tokens[$this->posicion++] ?? '';
        if (! preg_match('/^(?:[a-zA-Z_][a-zA-Z0-9_]*|"[a-zA-Z_][a-zA-Z0-9_]*")$/', $nombre)) {
            throw new RuntimeException('Se esperaba un nombre de columna o vista.');
        }

        return trim($nombre, '"');
    }

    private function actual(): ?string
    {
        return isset($this->tokens[$this->posicion]) ? strtoupper($this->tokens[$this->posicion]) : null;
    }

    private function aceptar(string $token): bool
    {
        if ($this->actual() !== $token) {
            return false;
        }
        $this->posicion++;

        return true;
    }

    private function exigir(string $token): void
    {
        if (! $this->aceptar($token)) {
            throw new RuntimeException('Sintaxis no admitida; se esperaba '.$token.'.');
        }
    }
}
