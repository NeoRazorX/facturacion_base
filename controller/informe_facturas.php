<?php
/**
 * This file is part of facturacion_base
 * Copyright (C) 2013-2020  Carlos Garcia Gomez     <neorazorx@gmail.com>
 * Copyright (C) 2017       Francesc Pineda Segarra <francesc.pìneda@x-netdigital.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
require_once 'plugins/facturacion_base/controller/informe_albaranes.php';

/**
 * Heredamos del controlador de informe_albaranes, para reaprovechar el código.
 */
class informe_facturas extends informe_albaranes
{

    public $pais;
    public $estado;

    public function __construct()
    {
        parent::__construct(__CLASS__, 'Facturas', 'informes');
    }

    protected function private_core()
    {
        /// declaramos los objetos sólo para asegurarnos de que existen las tablas
        new factura_cliente();
        new factura_proveedor();

        $this->nombre_docs = 'Facturas';
        $this->pais = new pais();
        $this->table_compras = 'facturasprov';
        $this->table_ventas = 'facturascli';

        parent::private_core();
    }

    protected function generar_extra()
    {
        if ($_POST['generar'] == 'informe_compras') {
            if ($_POST['unidades'] == 'TRUE') {
                $this->informe_compras_unidades();
            } else {
                $this->informe_compras();
            }
        } else if ($_POST['generar'] == 'informe_ventas') {
            if ($_POST['unidades'] == 'TRUE') {
                $this->informe_ventas_unidades();
            } else {
                $this->informe_ventas();
            }
        }
    }

    public function provincias()
    {
        return $this->fbase_sql_distinct('dirclientes', 'provincia');
    }

    public function stats_series($tabla = 'facturasprov')
    {
        return parent::stats_series($tabla);
    }

    public function stats_agentes($tabla = 'facturasprov')
    {
        return parent::stats_agentes($tabla);
    }

    public function stats_almacenes($tabla = 'facturasprov')
    {
        return parent::stats_almacenes($tabla);
    }

    public function stats_formas_pago($tabla = 'facturasprov')
    {
        return parent::stats_formas_pago($tabla);
    }

    public function stats_estados($tabla = 'facturasprov')
    {
        $stats = array();

        $where = $this->where_compras;
        if ($tabla == $this->table_ventas) {
            $where = $this->where_ventas;
        }

        /// aprobados
        $sql = "select sum(neto) as total from " . $tabla;
        $sql .= $where;
        $sql .= " and idfactura is not null order by total desc;";

        $data = $this->db->select($sql);
        if ($data && floatval($data[0]['total'])) {
            $stats[] = array(
                'txt' => 'facturado',
                'total' => round(floatval($data[0]['total']), FS_NF0)
            );
        }

        /// pendientes
        $sql = "select sum(neto) as total from " . $tabla;
        $sql .= $where;
        $sql .= " and idfactura is null order by total desc;";

        $data = $this->db->select($sql);
        if ($data && floatval($data[0]['total'])) {
            $stats[] = array(
                'txt' => 'no facturado',
                'total' => round(floatval($data[0]['total']), FS_NF0)
            );
        }

        return $stats;
    }

    protected function get_documentos($tabla)
    {
        $doclist = array();

        $where = $this->where_compras;
        if ($tabla == $this->table_ventas) {
            $where = $this->where_ventas;
        }

        $sql = "select * from " . $tabla . $where . " order by fecha asc, codigo asc;";
        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $d) {
                if ($tabla == $this->table_ventas) {
                    $sql = "SELECT * FROM lineasivafactcli WHERE idfactura = " . $this->empresa->var2str($d['idfactura']) . " ORDER BY iva DESC";
                    $dataiva = $this->db->select($sql);
                    if ($dataiva) {
                        $this->process_lineas_iva($doclist, $dataiva, $d, 'factura_cliente');
                    } else {
                        $factura = new factura_cliente($d);
                        $factura->tasaiva = 0;
                        $factura->tasare = 0;
                        $doclist[] = $factura;
                    }
                } else {
                    $sql = "SELECT * FROM lineasivafactprov WHERE idfactura = " . $this->empresa->var2str($d['idfactura']) . " ORDER BY iva DESC";
                    $dataiva = $this->db->select($sql);
                    if ($dataiva) {
                        $this->process_lineas_iva($doclist, $dataiva, $d, 'factura_proveedor');
                    } else {
                        $factura = new factura_proveedor($d);
                        $factura->tasaiva = 0;
                        $factura->tasare = 0;
                        $doclist[] = $factura;
                    }
                }
            }
        }

        return $doclist;
    }

    /**
     * A partir de los datos de lineasivafactcli (o prov) completamos el listado
     * de facturas desglosando por tipo de iva.
     * @param array $docList
     * @param array $dataIVA
     * @param array $dataFactura
     * @param string $className
     */
    private function process_lineas_iva(&$docList, &$dataIVA, &$dataFactura, $className)
    {
        foreach ($dataIVA as $key => $diva) {
            $dataFactura['neto'] = $diva['neto'];
            $dataFactura['totaliva'] = $diva['totaliva'];
            $dataFactura['totalrecargo'] = $diva['totalrecargo'];
            if ($key != 0) {
                $dataFactura['irpf'] = 0;
                $dataFactura['totalirpf'] = 0;
            }
            $dataFactura['total'] = $diva['neto'] + $dataFactura['totaliva'] + $dataFactura['totalrecargo'] - $dataFactura['totalirpf'];

            $factura = new $className($dataFactura);
            $factura->tasaiva = $diva['iva'];
            $factura->tasare = $diva['recargo'];
            $docList[] = $factura;
        }
    }

    /**
     * Añade el desglose de impuestos al documento PDF.
     * @param fs_pdf $pdf_doc
     * @param string $tipo
     */
    protected function desglose_impuestos_pdf(&$pdf_doc, $tipo)
    {
        $impuestos = array();

        if ($tipo == 'compra' && $this->db->table_exists('lineasivafactprov')) {
            $sql = "select * from lineasivafactprov WHERE idfactura IN"
                . " (select idfactura from facturasprov " . $this->where_compras . ")"
                . " order by iva asc;";
        } else if ($tipo == 'venta' && $this->db->table_exists('lineasivafactcli')) {
            $sql = "select * from lineasivafactcli WHERE idfactura IN"
                . " (select idfactura from facturascli " . $this->where_ventas . ")"
                . " order by iva asc;";
        } else {
            return FALSE;
        }

        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $d) {
                if ($tipo == 'compra') {
                    $liva = new linea_iva_factura_proveedor($d);
                } else {
                    $liva = new linea_iva_factura_cliente($d);
                }

                /**
                 * Debemos fiarnos de que los cálculos de las líneas de iva sean correctos.
                 * Si hubiese un error, es en la generación de las lineas de iva donde hay
                 * que solucionarlo.
                 */
                if (isset($impuestos['iva'][$liva->iva])) {
                    $impuestos['iva'][$liva->iva]['base'] += $liva->neto;
                    $impuestos['iva'][$liva->iva]['totaliva'] += $liva->totaliva;
                    $impuestos['iva'][$liva->iva]['totalre'] += $liva->totalrecargo;
                } else {
                    $impuestos['iva'][$liva->iva]['base'] = $liva->neto;
                    $impuestos['iva'][$liva->iva]['totaliva'] = $liva->totaliva;
                    $impuestos['iva'][$liva->iva]['totalre'] = $liva->totalrecargo;
                }
            }
        }

        if (!empty($impuestos)) {
            $header = array();
            $row = array();

            foreach ($impuestos['iva'] as $key => $value) {
                $header[] = 'Base ' . $key . '%';
                $row[] = $this->show_numero($value['base']);

                $header[] = FS_IVA . ' ' . $key . '%';
                $row[] = $this->show_numero($value['totaliva']);

                if ($value['totalre']) {
                    $header[] = 'RE ' . $key . '%';
                    $row[] = $this->show_numero($value['totalre']);
                }
            }

            $pdf_doc->pdf->ezText("\n");
            $pdf_doc->new_table();
            $pdf_doc->add_table_header($header);
            $pdf_doc->add_table_row($row);
            $pdf_doc->save_table(
                array(
                    'fontSize' => 8,
                    'shaded' => 0,
                    'width' => 780
                )
            );
        } else {
            $pdf_doc->pdf->ezText("\nSin impuestos.");
        }
    }

    private function informe_compras()
    {
        $sql = "SELECT codproveedor,fecha,SUM(neto) as total FROM facturasprov"
            . " WHERE fecha >= " . $this->empresa->var2str($this->desde)
            . " AND fecha <= " . $this->empresa->var2str($this->hasta);

        if ($this->codserie) {
            $sql .= " AND codserie = " . $this->empresa->var2str($this->codserie);
        }

        if ($this->codagente) {
            $sql .= " AND codagente = " . $this->empresa->var2str($this->codagente);
        }

        if ($this->proveedor) {
            $sql .= " AND codproveedor = " . $this->empresa->var2str($this->proveedor->codproveedor);
        }

        if ($_POST['minimo']) {
            $sql .= " AND neto > " . $this->empresa->var2str($_POST['minimo']);
        }

        $sql .= " GROUP BY codproveedor,fecha ORDER BY codproveedor ASC, fecha DESC;";

        $data = $this->db->select($sql);
        if ($data) {
            $this->template = FALSE;

            header("content-type:application/csv;charset=UTF-8");
            header("Content-Disposition: attachment; filename=\"informe_compras.csv\"");
            echo "codproveedor;nombre;año;ene;feb;mar;abr;may;jun;jul;ago;sep;oct;nov;dic;total;%VAR\n";

            $proveedor = new proveedor();
            $stats = array();
            foreach ($data as $d) {
                $anyo = date('Y', strtotime($d['fecha']));
                $mes = date('n', strtotime($d['fecha']));
                if (!isset($stats[$d['codproveedor']][$anyo])) {
                    $stats[$d['codproveedor']][$anyo] = array(
                        1 => 0,
                        2 => 0,
                        3 => 0,
                        4 => 0,
                        5 => 0,
                        6 => 0,
                        7 => 0,
                        8 => 0,
                        9 => 0,
                        10 => 0,
                        11 => 0,
                        12 => 0,
                        13 => 0,
                        14 => 0
                    );
                }

                $stats[$d['codproveedor']][$anyo][$mes] += floatval($d['total']);
                $stats[$d['codproveedor']][$anyo][13] += floatval($d['total']);
            }

            $totales = array();
            foreach ($stats as $i => $value) {
                /// calculamos la variación
                $anterior = 0;
                foreach (array_reverse($value, TRUE) as $j => $value2) {
                    if ($anterior > 0) {
                        $value[$j][14] = ($value2[13] * 100 / $anterior) - 100;
                    }

                    $anterior = $value2[13];

                    if (isset($totales[$j])) {
                        foreach ($value2 as $k => $value3) {
                            $totales[$j][$k] += $value3;
                        }
                    } else {
                        $totales[$j] = $value2;
                    }
                }

                $pro = $proveedor->get($i);
                foreach ($value as $j => $value2) {
                    if ($pro) {
                        echo '"' . $i . '";' . fs_fix_html($pro->nombre) . ';' . $j;
                    } else {
                        echo '"' . $i . '";-;' . $j;
                    }

                    foreach ($value2 as $value3) {
                        echo ';' . number_format($value3, FS_NF0, ',', '');
                    }

                    echo "\n";
                }
                echo ";;;;;;;;;;;;;;;\n";
            }

            foreach (array_reverse($totales, TRUE) as $i => $value) {
                echo ";TOTALES;" . $i;
                $l_total = 0;
                foreach ($value as $j => $value3) {
                    if ($j < 13) {
                        echo ';' . number_format($value3, FS_NF0, ',', '');
                        $l_total += $value3;
                    }
                }
                echo ";" . number_format($l_total, FS_NF0, ',', '') . ";\n";
            }
        } else {
            $this->new_error_msg('Sin resultados.');
        }
    }

    private function informe_ventas()
    {
        $sql = "SELECT codalmacen,codcliente,fecha,SUM(neto) as total FROM facturascli"
            . " WHERE fecha >= " . $this->empresa->var2str($this->desde)
            . " AND fecha <= " . $this->empresa->var2str($this->hasta);

        if ($_POST['codpais']) {
            $sql .= " AND codpais = " . $this->empresa->var2str($_POST['codpais']);
        }

        if ($_POST['provincia']) {
            $sql .= " AND lower(provincia) = lower(" . $this->empresa->var2str($_POST['provincia']) . ")";
        }

        if ($this->cliente) {
            $sql .= " AND codcliente = " . $this->empresa->var2str($this->cliente->codcliente);
        }

        if ($this->codserie) {
            $sql .= " AND codserie = " . $this->empresa->var2str($this->codserie);
        }

        if ($this->codalmacen) {
            $sql .= " AND codalmacen = " . $this->empresa->var2str($this->codalmacen);
        }

        if ($this->codagente) {
            $sql .= " AND codagente = " . $this->empresa->var2str($this->codagente);
        }

        if ($_POST['minimo']) {
            $sql .= " AND neto > " . $this->empresa->var2str($_POST['minimo']);
        }

        $sql .= " GROUP BY codalmacen,codcliente,fecha ORDER BY codcliente ASC, fecha DESC;";

        $data = $this->db->select($sql);
        if ($data) {
            $this->template = FALSE;
            header("content-type:application/csv;charset=UTF-8");
            header("Content-Disposition: attachment; filename=\"informe_ventas.csv\"");
            echo "almacen;codcliente;nombre;año;ene;feb;mar;abr;may;jun;jul;ago;sep;oct;nov;dic;total;%VAR\n";
            $cliente = new cliente();
            $stats = array();
            foreach ($data as $d) {
                $anyo = date('Y', strtotime($d['fecha']));
                $mes = date('n', strtotime($d['fecha']));
                if (!isset($stats[$d['codcliente']][$anyo])) {
                    $stats[$d['codcliente']][$anyo] = array(
                        1 => 0,
                        2 => 0,
                        3 => 0,
                        4 => 0,
                        5 => 0,
                        6 => 0,
                        7 => 0,
                        8 => 0,
                        9 => 0,
                        10 => 0,
                        11 => 0,
                        12 => 0,
                        13 => 0,
                        14 => 0,
                        15 => $d['codalmacen']
                    );
                }

                $stats[$d['codcliente']][$anyo][$mes] += floatval($d['total']);
                $stats[$d['codcliente']][$anyo][13] += floatval($d['total']);
            }

            $totales = array();
            foreach ($stats as $i => $value) {
                /// calculamos la variación y los totales
                $anterior = 0;
                foreach (array_reverse($value, TRUE) as $j => $value2) {
                    if ($anterior > 0) {
                        $value[$j][14] = ($value2[13] * 100 / $anterior) - 100;
                    }

                    $anterior = $value2[13];

                    if (isset($totales[$j])) {
                        foreach ($value2 as $k => $value3) {
                            $totales[$j][$k] += $value3;
                        }
                    } else {
                        $totales[$j] = $value2;
                    }
                }

                $cli = $cliente->get($i);
                foreach ($value as $j => $value2) {
                    if ($cli) {
                        echo '"' . $value[$j][15] . '";' . '"' . $i . '";' . fs_fix_html($cli->nombre) . ';' . $j;
                    } else {
                        echo '"' . $value[$j][15] . '";' . '"' . $i . '";-;' . $j;
                    }

                    foreach ($value2 as $x => $value3) {
                        if ($x < 15) {
                            echo ';' . $this->show_numero($value3, FS_NF0);
                        }
                    }
                    echo "\n";
                }
                echo ";;;;;;;;;;;;;;;\n";
            }
            foreach (array_reverse($totales, TRUE) as $i => $value) {
                echo ";TOTALES;" . $i;
                $l_total = 0;
                foreach ($value as $j => $value3) {
                    if ($j < 13) {
                        echo ';' . $this->show_numero($value3, FS_NF0);
                    }
                }
                echo ";" . $this->show_numero($l_total, FS_NF0) . ";\n";
            }
        } else {
            $this->new_error_msg('Sin resultados.');
        }
    }

    private function informe_compras_unidades()
    {
        $sql = "SELECT f.codalmacen,f.codproveedor,f.fecha,l.referencia,l.descripcion,SUM(l.cantidad) as total"
            . " FROM facturasprov f, lineasfacturasprov l"
            . " WHERE f.idfactura = l.idfactura AND l.referencia IS NOT NULL"
            . " AND f.fecha >= " . $this->empresa->var2str($this->desde)
            . " AND f.fecha <= " . $this->empresa->var2str($this->hasta);

        if ($this->codserie) {
            $sql .= " AND f.codserie = " . $this->empresa->var2str($this->codserie);
        }

        if ($this->codalmacen) {
            $sql .= " AND f.codalmacen = " . $this->empresa->var2str($this->codalmacen);
        }

        if ($this->codagente) {
            $sql .= " AND f.codagente = " . $this->empresa->var2str($this->codagente);
        }

        if ($this->proveedor) {
            $sql .= " AND codproveedor = " . $this->empresa->var2str($this->proveedor->codproveedor);
        }

        if ($_POST['minimo']) {
            $sql .= " AND l.cantidad > " . $this->empresa->var2str($_POST['minimo']);
        }

        $sql .= " GROUP BY f.codalmacen,f.codproveedor,f.fecha,l.referencia,l.descripcion ORDER BY f.codproveedor ASC, l.referencia ASC, f.fecha DESC;";

        $data = $this->db->select($sql);
        if ($data) {
            $this->template = FALSE;
            header("content-type:application/csv;charset=UTF-8");
            header("Content-Disposition: attachment; filename=\"informe_compras_unidades.csv\"");
            echo "almacen;codproveedor;nombre;referencia;descripcion;año;ene;feb;mar;abr;may;jun;jul;ago;sep;oct;nov;dic;total;%VAR\n";

            $proveedor = new proveedor();
            $stats = array();

            foreach ($data as $d) {
                $anyo = date('Y', strtotime($d['fecha']));
                $mes = date('n', strtotime($d['fecha']));
                if (!isset($stats[$d['codproveedor']][$d['referencia']][$anyo])) {
                    $stats[$d['codproveedor']][$d['referencia']][$anyo] = array(
                        1 => 0,
                        2 => 0,
                        3 => 0,
                        4 => 0,
                        5 => 0,
                        6 => 0,
                        7 => 0,
                        8 => 0,
                        9 => 0,
                        10 => 0,
                        11 => 0,
                        12 => 0,
                        13 => 0,
                        14 => 0,
                        15 => $d['codalmacen'],
                        16 => $d['descripcion'],
                    );
                }

                $stats[$d['codproveedor']][$d['referencia']][$anyo][$mes] += floatval($d['total']);
                $stats[$d['codproveedor']][$d['referencia']][$anyo][13] += floatval($d['total']);
            }

            foreach ($stats as $i => $value) {
                $pro = $proveedor->get($i);
                foreach ($value as $j => $value2) {
                    /// calculamos la variación
                    $anterior = 0;
                    foreach (array_reverse($value2, TRUE) as $k => $value3) {
                        if ($anterior > 0) {
                            $value2[$k][14] = ($value3[13] * 100 / $anterior) - 100;
                        }
                        $anterior = $value3[13];
                    }

                    foreach ($value2 as $k => $value3) {
                        if ($pro) {
                            echo '"' . $value2[$k][15] . '";' . '"' . $i . '";' . fs_fix_html($pro->nombre) . ';"' . $j . '";' . '"' . $value2[$k][16] . '"' . ';' . $k;
                        } else {
                            echo '"' . $value2[$k][15] . '";' . '"' . $i . '";-;"' . $j . '";' . '"' . $value2[$k][16] . '"' . ';' . $k;
                        }

                        foreach ($value3 as $x => $value4) {
                            if ($x < 15) {
                                echo ';' . $this->show_numero($value4, FS_NF0);
                            }
                        }
                        echo "\n";
                    }
                    echo ";;;;;;;;;;;;;;;\n";
                }
                echo ";;;;;;;;;;;;;;;\n";
            }
        } else {
            $this->new_error_msg('Sin resultados.');
        }
    }

    private function informe_ventas_unidades()
    {
        $sql = "SELECT f.codalmacen,f.codcliente,f.fecha,l.referencia,l.descripcion,SUM(l.cantidad) as total"
            . " FROM facturascli f, lineasfacturascli l"
            . " WHERE f.idfactura = l.idfactura AND l.referencia IS NOT NULL"
            . " AND f.fecha >= " . $this->empresa->var2str($this->desde)
            . " AND f.fecha <= " . $this->empresa->var2str($this->hasta);

        if ($_POST['codpais']) {
            $sql .= " AND f.codpais = " . $this->empresa->var2str($_POST['codpais']);
        }

        if ($_POST['provincia']) {
            $sql .= " AND lower(f.provincia) = lower(" . $this->empresa->var2str($_POST['provincia']) . ")";
        }

        if ($this->cliente) {
            $sql .= " AND codcliente = " . $this->empresa->var2str($this->cliente->codcliente);
        }

        if ($this->codalmacen) {
            $sql .= " AND f.codalmacen = " . $this->empresa->var2str($this->codalmacen);
        }

        if ($this->codserie) {
            $sql .= " AND f.codserie = " . $this->empresa->var2str($this->codserie);
        }

        if ($this->codagente) {
            $sql .= " AND f.codagente = " . $this->empresa->var2str($this->codagente);
        }

        if ($_POST['minimo']) {
            $sql .= " AND l.cantidad > " . $this->empresa->var2str($_POST['minimo']);
        }

        $sql .= " GROUP BY f.codalmacen,f.codcliente,f.fecha,l.referencia,l.descripcion ORDER BY f.codcliente ASC, l.referencia ASC, f.fecha DESC;";

        $data = $this->db->select($sql);
        if ($data) {
            $this->template = FALSE;

            header("content-type:application/csv;charset=UTF-8");
            header("Content-Disposition: attachment; filename=\"informe_ventas_unidades.csv\"");
            echo "almacen;codcliente;nombre;referencia;descripcion;año;ene;feb;mar;abr;may;jun;jul;ago;sep;oct;nov;dic;total;%VAR\n";

            $cliente = new cliente();
            $stats = array();
            foreach ($data as $d) {
                $anyo = date('Y', strtotime($d['fecha']));
                $mes = date('n', strtotime($d['fecha']));
                if (!isset($stats[$d['codcliente']][$d['referencia']][$anyo])) {
                    $stats[$d['codcliente']][$d['referencia']][$anyo] = array(
                        1 => 0,
                        2 => 0,
                        3 => 0,
                        4 => 0,
                        5 => 0,
                        6 => 0,
                        7 => 0,
                        8 => 0,
                        9 => 0,
                        10 => 0,
                        11 => 0,
                        12 => 0,
                        13 => 0,
                        14 => 0,
                        15 => $d['codalmacen'],
                        16 => $d['descripcion'],
                    );
                }

                $stats[$d['codcliente']][$d['referencia']][$anyo][$mes] += floatval($d['total']);
                $stats[$d['codcliente']][$d['referencia']][$anyo][13] += floatval($d['total']);
            }

            foreach ($stats as $i => $value) {
                $cli = $cliente->get($i);
                foreach ($value as $j => $value2) {
                    /// calculamos la variación
                    $anterior = 0;
                    foreach (array_reverse($value2, TRUE) as $k => $value3) {
                        if ($anterior > 0) {
                            $value2[$k][14] = ($value3[13] * 100 / $anterior) - 100;
                        }

                        $anterior = $value3[13];
                    }

                    foreach ($value2 as $k => $value3) {
                        if ($cli) {
                            echo '"' . $value2[$k][15] . '";' . '"' . $i . '";' . fs_fix_html($cli->nombre) . ';"' . $j . '";' . '"' . $value2[$k][16] . '"' . ';' . $k;
                        } else {
                            echo '"' . $value2[$k][15] . '";' . '"' . $i . '";-;"' . $j . '";' . '"' . $value2[$k][16] . '"' . ';' . $k;
                        }

                        foreach ($value3 as $x => $value4) {
                            if ($x < 15) {
                                echo ';' . $this->show_numero($value4, FS_NF0);
                            }
                        }

                        echo "\n";
                    }
                    echo ";;;;;;;;;;;;;;;;\n";
                }
                echo ";;;;;;;;;;;;;;;;\n";
            }
        } else {
            $this->new_error_msg('Sin resultados.');
        }
    }

    protected function ini_filters()
    {
        parent::ini_filters();

        $this->estado = FALSE;
        if (isset($_REQUEST['estado'])) {
            $this->estado = $_REQUEST['estado'];
        }
    }

    protected function set_where()
    {
        parent::set_where();

        if ($this->estado) {
            $estado = $this->estado == 'pagada' ? TRUE : FALSE;
            $this->where_compras .= " AND pagada = " . $this->empresa->var2str($estado);
            $this->where_ventas .= " AND pagada = " . $this->empresa->var2str($estado);
        }
    }

    protected function generar_pdf($tipo = 'compra')
    {
        /// desactivamos el motor de plantillas
        $this->template = FALSE;

        $pdf_doc = new fs_pdf('a4', 'landscape', 'Courier');
        $pdf_doc->pdf->addInfo('Title', $this->nombre_docs . ' de ' . $tipo . ' del ' . $this->desde . ' al ' . $this->hasta);
        $pdf_doc->pdf->addInfo('Subject', $this->nombre_docs . ' de ' . $tipo . ' del ' . $this->desde . ' al ' . $this->hasta);
        $pdf_doc->pdf->addInfo('Author', fs_fix_html($this->empresa->nombre));

        $cliente = 'Proveedor';
        $num2 = 'Num. proveedor';
        $tabla = $this->table_compras;
        if ($tipo == 'venta') {
            $cliente = 'Cliente';
            $num2 = FS_NUMERO2;
            $tabla = $this->table_ventas;
        }

        $encabezado = fs_fix_html($this->empresa->nombre) . ' - ' . $this->nombre_docs
            . ' de ' . $tipo . ' del ' . $this->desde . ' al ' . $this->hasta;

        if ($this->codagente) {
            $encabezado .= ', empleado: ' . $this->codagente;
        }

        if ($this->codserie) {
            $encabezado .= ', serie: ' . $this->codserie;
        }

        if ($this->coddivisa) {
            $encabezado .= ', divisa: ' . $this->coddivisa;
        }

        if ($this->codpago) {
            $encabezado .= ', forma de pago: ' . $this->codpago;
        }

        if ($this->codalmacen) {
            $encabezado .= ', almacén ' . $this->codalmacen;
        }

        $documentos = $this->get_documentos($tabla);
        if (!empty($documentos)) {
            $total_lineas = count($documentos);
            $linea_actual = 0;
            $lppag = 72;
            $pagina = 1;
            $neto = $totaliva = $totalre = $totalirpf = $total = 0;

            while ($linea_actual < $total_lineas) {
                if ($linea_actual > 0) {
                    $pdf_doc->pdf->ezNewPage();
                    $pagina++;
                }

                /// encabezado
                $pdf_doc->pdf->ezText($encabezado . ".\n\n");

                /// tabla principal
                $pdf_doc->new_table();
                $pdf_doc->add_table_header(
                    array(
                        'serie' => '<b>' . strtoupper(FS_SERIE) . '</b>',
                        'doc' => '<b>Documento</b>',
                        'num2' => '<b>' . $num2 . '</b>',
                        'fecha' => '<b>Fecha</b>',
                        'cliente' => '<b>' . $cliente . '</b>',
                        'cifnif' => '<b>' . FS_CIFNIF . '</b>',
                        'neto' => '<b>Neto</b>',
                        'tasaiva' => '<b>%' . FS_IVA . '</b>',
                        'iva' => '<b>' . FS_IVA . '</b>',
                        'tasare' => '<b>%RE</b>',
                        're' => '<b>RE</b>',
                        'irpf' => '<b>' . FS_IRPF . '</b>',
                        'total' => '<b>Total</b>'
                    )
                );

                for ($i = 0; $i < $lppag && $linea_actual < $total_lineas; $i++) {
                    $linea = array(
                        'serie' => $documentos[$linea_actual]->codserie,
                        'doc' => $documentos[$linea_actual]->codigo,
                        'num2' => '',
                        'fecha' => $documentos[$linea_actual]->fecha,
                        'cliente' => '',
                        'cifnif' => $documentos[$linea_actual]->cifnif,
                        'neto' => $this->show_numero($documentos[$linea_actual]->neto),
                        'tasaiva' => $this->show_numero($documentos[$linea_actual]->tasaiva),
                        'iva' => $this->show_numero($documentos[$linea_actual]->totaliva),
                        'tasare' => $this->show_numero($documentos[$linea_actual]->tasare),
                        're' => $this->show_numero($documentos[$linea_actual]->totalrecargo),
                        'irpf' => $this->show_numero($documentos[$linea_actual]->totalirpf),
                        'total' => $this->show_numero($documentos[$linea_actual]->total),
                    );

                    if ($tipo == 'compra') {
                        $linea['num2'] = fs_fix_html($documentos[$linea_actual]->numproveedor);
                        $linea['cliente'] = fs_fix_html($documentos[$linea_actual]->nombre);
                    } else {
                        $linea['num2'] = fs_fix_html($documentos[$linea_actual]->numero2);
                        $linea['cliente'] = fs_fix_html($documentos[$linea_actual]->nombrecliente);
                    }

                    $pdf_doc->add_table_row($linea);

                    $neto += $documentos[$linea_actual]->neto;
                    $totaliva += $documentos[$linea_actual]->totaliva;
                    $totalre += $documentos[$linea_actual]->totalrecargo;
                    $totalirpf += $documentos[$linea_actual]->totalirpf;
                    $total += $documentos[$linea_actual]->total;
                    $i++;
                    $linea_actual++;
                }

                /// añadimos el subtotal
                $linea = array(
                    'serie' => '',
                    'doc' => '',
                    'num2' => '',
                    'fecha' => '',
                    'cliente' => '',
                    'cifnif' => '',
                    'neto' => '<b>' . $this->show_numero($neto) . '</b>',
                    'tasaiva' => '',
                    'iva' => '<b>' . $this->show_numero($totaliva) . '</b>',
                    'tasare' => '',
                    're' => '<b>' . $this->show_numero($totalre) . '</b>',
                    'irpf' => '<b>' . $this->show_numero($totalirpf) . '</b>',
                    'total' => '<b>' . $this->show_numero($total) . '</b>',
                );
                $pdf_doc->add_table_row($linea);

                $pdf_doc->save_table(
                    array(
                        'fontSize' => 8,
                        'cols' => array(
                            'neto' => array('justification' => 'right'),
                            'tasaiva' => array('justification' => 'right'),
                            'iva' => array('justification' => 'right'),
                            'tasare' => array('justification' => 'right'),
                            're' => array('justification' => 'right'),
                            'irpf' => array('justification' => 'right'),
                            'total' => array('justification' => 'right')
                        ),
                        'shaded' => 0,
                        'width' => 780
                    )
                );
            }

            $this->desglose_impuestos_pdf($pdf_doc, $tipo);
        } else {
            $pdf_doc->pdf->ezText($encabezado . '.');
            $pdf_doc->pdf->ezText("\nSin resultados.", 14);
        }

        $pdf_doc->show();
    }

    protected function generar_xls($tipo = 'compra')
    {
        /// desactivamos el motor de plantillas
        $this->template = FALSE;

        header("Content-Disposition: attachment; filename=\"informe_" . $this->nombre_docs . "_" . time() . ".xlsx\"");
        header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');

        $header = array(
            'serie' => 'string',
            'doc' => 'string',
            'num2' => 'string',
            'num.proveedor' => 'string',
            'fecha' => 'string',
            'cliente' => 'string',
            'proveedor' => 'string',
            FS_CIFNIF => 'string',
            'neto' => '#,##0.00;[RED]-#,##0.00',
            'tasaiva' => '#,##0.00;[RED]-#,##0.00',
            'iva' => '#,##0.00;[RED]-#,##0.00',
            'tasare' => '#,##0.00;[RED]-#,##0.00',
            're' => '#,##0.00;[RED]-#,##0.00',
            'irpf' => '#,##0.00;[RED]-#,##0.00',
            'total' => '#,##0.00;[RED]-#,##0.00',
        );

        if ($tipo == 'compra') {
            $tabla = $this->table_compras;
            unset($header['num2']);
            unset($header['cliente']);
        } else {
            $tabla = $this->table_ventas;
            unset($header['num.proveedor']);
            unset($header['proveedor']);
        }

        $writter = new XLSXWriter();
        $writter->setAuthor('FacturaScripts');
        $writter->writeSheetHeader($this->nombre_docs, $header);

        foreach ($this->get_documentos($tabla) as $doc) {
            $linea = array(
                'serie' => $doc->codserie,
                'doc' => $doc->codigo,
                'num2' => '',
                'num.proveedor' => '',
                'fecha' => $doc->fecha,
                'cliente' => '',
                'proveedor' => '',
                FS_CIFNIF => $doc->cifnif,
                'neto' => $doc->neto,
                'tasaiva' => $doc->tasaiva,
                'iva' => $doc->totaliva,
                'tasare' => $doc->tasare,
                're' => $doc->totalrecargo,
                'irpf' => $doc->totalirpf,
                'total' => $doc->total,
            );

            if ($tipo == 'compra') {
                $linea['num.proveedor'] = $doc->numproveedor;
                $linea['proveedor'] = $doc->nombre;
                unset($linea['num2']);
                unset($linea['cliente']);
            } else {
                $linea['num2'] = $doc->numero2;
                $linea['cliente'] = $doc->nombrecliente;
                unset($linea['num.proveedor']);
                unset($linea['proveedor']);
            }

            $writter->writeSheetRow($this->nombre_docs, $linea);
        }

        $writter->writeToStdOut();
    }

    protected function generar_csv($tipo = 'compra')
    {
        /// desactivamos el motor de plantillas
        $this->template = FALSE;

        header("content-type:application/csv;charset=UTF-8");
        header("Content-Disposition: attachment; filename=\"informe_" . $this->nombre_docs . "_" . time() . ".csv\"");

        if ($tipo == 'compra') {
            $tabla = $this->table_compras;
            echo "serie,documento,num.proveedor,fecha,proveedor," . FS_CIFNIF . ",neto,tasa" . FS_IVA
            . "," . FS_IVA . ",tasare,re," . FS_IRPF . ",total\n";
        } else {
            $tabla = $this->table_ventas;
            echo "serie,documento," . FS_NUMERO2 . ",fecha,cliente," . FS_CIFNIF . ",neto,tasa" . FS_IVA
            . "," . FS_IVA . ",tasare,re," . FS_IRPF . ",total\n";
        }

        foreach ($this->get_documentos($tabla) as $doc) {
            $linea = array(
                'serie' => $doc->codserie,
                'doc' => $doc->codigo,
                'num2' => '',
                'fecha' => $doc->fecha,
                'cliente' => '',
                'cifnif' => $doc->cifnif,
                'neto' => $doc->neto,
                'tasaiva' => $doc->tasaiva,
                'iva' => $doc->totaliva,
                'tasare' => $doc->tasare,
                're' => $doc->totalrecargo,
                'irpf' => $doc->totalirpf,
                'total' => $doc->total,
            );

            if ($tipo == 'compra') {
                $linea['num2'] = $doc->numproveedor;
                $linea['cliente'] = fs_fix_html($doc->nombre);
            } else {
                $linea['num2'] = $doc->numero2;
                $linea['cliente'] = fs_fix_html($doc->nombrecliente);
            }

            echo '"' . join('","', $linea) . "\"\n";
        }
    }
}
