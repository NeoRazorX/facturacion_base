<?php
/*
 * This file is part of facturacion_base
 * Copyright (C) 2014-2017  Carlos Garcia Gomez  neorazorx@gmail.com
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

require_once 'plugins/facturacion_base/extras/fbase_controller.php';

class compras_facturas extends fbase_controller
{

    public $agente;
    public $almacenes;
    public $articulo;
    public $buscar_lineas;
    public $codagente;
    public $codalmacen;
    public $codserie;
    public $desde;
    public $estado;
    public $factura;
    public $hasta;
    public $lineas;
    public $mostrar;
    public $num_resultados;
    public $offset;
    public $order;
    public $proveedor;
    public $resultados;
    public $serie;
    public $total_resultados;
    public $total_resultados_txt;

    public function __construct()
    {
        parent::__construct(__CLASS__, 'Facturas', 'compras');
    }

    protected function private_core()
    {
        parent::private_core();

        $this->agente = new agente();
        $this->almacenes = new almacen();
        $this->factura = new factura_proveedor();
        $this->serie = new serie();

        $this->mostrar = 'todo';
        if (isset($_GET['mostrar'])) {
            $this->mostrar = $_GET['mostrar'];
            setcookie('compras_fac_mostrar', $this->mostrar, time() + FS_COOKIES_EXPIRE);
        } else if (isset($_COOKIE['compras_fac_mostrar'])) {
            $this->mostrar = $_COOKIE['compras_fac_mostrar'];
        }

        $this->offset = 0;
        if (isset($_GET['offset'])) {
            $this->offset = intval($_GET['offset']);
        }

        $this->order = 'fecha DESC';
        if (isset($_GET['order'])) {
            $orden_l = $this->orden();
            if (isset($orden_l[$_GET['order']])) {
                $this->order = $orden_l[$_GET['order']]['orden'];
            }

            setcookie('compras_fac_order', $this->order, time() + FS_COOKIES_EXPIRE);
        } else if (isset($_COOKIE['compras_fac_order'])) {
            $this->order = $_COOKIE['compras_fac_order'];
        }

        if (isset($_POST['buscar_lineas'])) {
            $this->buscar_lineas();
        } else if (isset($_REQUEST['buscar_proveedor'])) {
            $this->fbase_buscar_proveedor($_REQUEST['buscar_proveedor']);
        } else if (isset($_GET['ref'])) {
            $this->template = 'extension/compras_facturas_articulo';

            $articulo = new articulo();
            $this->articulo = $articulo->get($_GET['ref']);

            $linea = new linea_factura_proveedor();
            $this->resultados = $linea->all_from_articulo($_GET['ref'], $this->offset);
        } else {
            $this->share_extension();
            $this->proveedor = FALSE;
            $this->codagente = '';
            $this->codalmacen = '';
            $this->codserie = '';
            $this->desde = '';
            $this->estado = '';
            $this->hasta = '';
            $this->num_resultados = '';
            $this->total_resultados = array();
            $this->total_resultados_txt = '';

            if (isset($_GET['delete'])) {
                $this->delete_factura();
            } else {
                if (!isset($_GET['mostrar']) && ( $this->query != '' || isset($_REQUEST['codagente']) || isset($_REQUEST['codproveedor']) || isset($_REQUEST['codserie']))) {
                    /**
                     * si obtenermos un codagente, un codproveedor o un codserie pasamos direcatemente
                     * a la pestaña de búsqueda, a menos que tengamos un mostrar, que
                     * entonces nos indica donde tenemos que estar.
                     */
                    $this->mostrar = 'buscar';
                }

                if (isset($_REQUEST['codproveedor']) && $_REQUEST['codproveedor'] != '') {
                    $pro0 = new proveedor();
                    $this->proveedor = $pro0->get($_REQUEST['codproveedor']);
                }

                if (isset($_REQUEST['codagente'])) {
                    $this->codagente = $_REQUEST['codagente'];
                }

                if (isset($_REQUEST['codserie'])) {
                    $this->codserie = $_REQUEST['codserie'];
                }

                if (isset($_REQUEST['desde'])) {
                    $this->desde = $_REQUEST['desde'];
                    $this->hasta = $_REQUEST['hasta'];
                    $this->estado = $_REQUEST['estado'];
                }
            }

            /// añadimos segundo nivel de ordenación
            $order2 = '';
            if ($this->order == 'fecha DESC') {
                $order2 = ', hora DESC, numero DESC';
            } else if ($this->order == 'fecha ASC') {
                $order2 = ', hora ASC, numero ASC';
            }

            if ($this->mostrar == 'sinpagar') {
                $this->resultados = $this->factura->all_sin_pagar($this->offset, FS_ITEM_LIMIT, $this->order . $order2);

                if ($this->offset == 0) {
                    /// calculamos el total, pero desglosando por divisa
                    $this->total_resultados = array();
                    $this->total_resultados_txt = 'Suma total de esta página:';
                    foreach ($this->resultados as $fac) {
                        if (!isset($this->total_resultados[$fac->coddivisa])) {
                            $this->total_resultados[$fac->coddivisa] = array(
                                'coddivisa' => $fac->coddivisa,
                                'total' => 0
                            );
                        }

                        $this->total_resultados[$fac->coddivisa]['total'] += $fac->total;
                    }
                }
            } else if ($this->mostrar == 'buscar') {
                $this->buscar($order2);
            } else
                $this->resultados = $this->factura->all($this->offset, FS_ITEM_LIMIT, $this->order . $order2);
        }
    }

    public function url($busqueda = FALSE)
    {
        if ($busqueda) {
            $codproveedor = '';
            if ($this->proveedor) {
                $codproveedor = $this->proveedor->codproveedor;
            }

            $url = parent::url() . "&mostrar=" . $this->mostrar
                . "&query=" . $this->query
                . "&codserie=" . $this->codserie
                . "&codagente=" . $this->codagente
                . "&codalmacen=" . $this->codalmacen
                . "&codproveedor=" . $codproveedor
                . "&desde=" . $this->desde
                . "&estado=" . $this->estado
                . "&hasta=" . $this->hasta;

            return $url;
        }

        return parent::url();
    }

    public function paginas()
    {
        if ($this->mostrar == 'sinpagar') {
            $total = $this->total_sinpagar();
        } else if ($this->mostrar == 'buscar') {
            $total = $this->num_resultados;
        } else {
            $total = $this->total_registros();
        }

        return $this->fbase_paginas($this->url(TRUE), $total, $this->offset);
    }

    public function buscar_lineas()
    {
        /// cambiamos la plantilla HTML
        $this->template = 'ajax/compras_lineas_facturas';

        $this->buscar_lineas = $_POST['buscar_lineas'];
        $linea = new linea_factura_proveedor();

        $this->lineas = $linea->search($this->buscar_lineas);
    }

    private function share_extension()
    {
        /// añadimos las extensiones para proveedores, agentes y artículos
        $extensiones = array(
            array(
                'name' => 'facturas_proveedor',
                'page_from' => __CLASS__,
                'page_to' => 'compras_proveedor',
                'type' => 'button',
                'text' => '<span class="glyphicon glyphicon-list" aria-hidden="true"></span> &nbsp; Facturas',
                'params' => ''
            ),
            array(
                'name' => 'facturas_agente',
                'page_from' => __CLASS__,
                'page_to' => 'admin_agente',
                'type' => 'button',
                'text' => '<span class="glyphicon glyphicon-list" aria-hidden="true"></span> &nbsp; Facturas de proveedor',
                'params' => ''
            ),
            array(
                'name' => 'facturas_articulo',
                'page_from' => __CLASS__,
                'page_to' => 'ventas_articulo',
                'type' => 'tab_button',
                'text' => '<span class="glyphicon glyphicon-list" aria-hidden="true"></span> &nbsp; Facturas de proveedor',
                'params' => ''
            )
        );
        foreach ($extensiones as $ext) {
            $fsext0 = new fs_extension($ext);
            if (!$fsext0->save()) {
                $this->new_error_msg('Imposible guardar los datos de la extensión ' . $ext['name'] . '.');
            }
        }
    }

    public function total_sinpagar()
    {
        return $this->fbase_sql_total('facturasprov', 'idfactura', 'WHERE pagada = false');
    }

    private function total_registros()
    {
        return $this->fbase_sql_total('facturasprov', 'idfactura');
    }

    private function buscar($order2)
    {
        $this->resultados = array();
        $this->num_resultados = 0;
        $sql = " FROM facturasprov ";
        $where = 'WHERE ';

        if ($this->query) {
            $query = $this->agente->no_html(mb_strtolower($this->query, 'UTF8'));
            $sql .= $where;
            if (is_numeric($query)) {
                $sql .= "(codigo LIKE '%" . $query . "%' OR numproveedor LIKE '%" . $query . "%' "
                    . "OR observaciones LIKE '%" . $query . "%' OR cifnif LIKE '" . $query . "%')";
            } else {
                $sql .= "(lower(codigo) LIKE '%" . $query . "%' OR lower(numproveedor) LIKE '%" . $query . "%' "
                    . "OR lower(cifnif) LIKE '" . $query . "%' "
                    . "OR lower(observaciones) LIKE '%" . str_replace(' ', '%', $query) . "%')";
            }
            $where = ' AND ';
        }

        if ($this->codagente) {
            $sql .= $where . "codagente = " . $this->agente->var2str($this->codagente);
            $where = ' AND ';
        }

        if ($this->codalmacen) {
            $sql .= $where . "codalmacen = " . $this->agente->var2str($this->codalmacen);
            $where = ' AND ';
        }

        if ($this->proveedor) {
            $sql .= $where . "codproveedor = " . $this->agente->var2str($this->proveedor->codproveedor);
            $where = ' AND ';
        }

        if ($this->codserie) {
            $sql .= $where . "codserie = " . $this->agente->var2str($this->codserie);
            $where = ' AND ';
        }

        if ($this->desde) {
            $sql .= $where . "fecha >= " . $this->agente->var2str($this->desde);
            $where = ' AND ';
        }

        if ($this->hasta) {
            $sql .= $where . "fecha <= " . $this->agente->var2str($this->hasta);
            $where = ' AND ';
        }

        if ($this->estado == 'pagadas') {
            $sql .= $where . "pagada";
            $where = ' AND ';
        } else if ($this->estado == 'impagadas') {
            $sql .= $where . "pagada = false";
            $where = ' AND ';
        } else if ($this->estado == 'anuladas') {
            $sql .= $where . "anulada = true";
            $where = ' AND ';
        } else if ($this->estado == 'sinasiento') {
            $sql .= $where . "idasiento IS NULL";
            $where = ' AND ';
        }

        $data = $this->db->select("SELECT COUNT(idfactura) as total" . $sql);
        if ($data) {
            $this->num_resultados = intval($data[0]['total']);

            $data2 = $this->db->select_limit("SELECT *" . $sql . " ORDER BY " . $this->order . $order2, FS_ITEM_LIMIT, $this->offset);
            if ($data2) {
                foreach ($data2 as $d) {
                    $this->resultados[] = new factura_proveedor($d);
                }
            }

            $data2 = $this->db->select("SELECT coddivisa,SUM(total) as total" . $sql . " GROUP BY coddivisa");
            if ($data2) {
                $this->total_resultados_txt = 'Suma total de los resultados:';

                foreach ($data2 as $d) {
                    $this->total_resultados[] = array(
                        'coddivisa' => $d['coddivisa'],
                        'total' => floatval($d['total'])
                    );
                }
            }
        }
    }

    private function delete_factura()
    {
        $fact = $this->factura->get($_GET['delete']);
        if ($fact) {
            /// obtenemos las líneas de la factura antes de eliminar
            $lineas = $fact->get_lineas();

            if ($fact->delete()) {
                if (!$fact->anulada) {
                    /// Restauramos el stock
                    $art0 = new articulo();
                    foreach ($lineas as $linea) {
                        /// si la línea pertenece a un albarán no descontamos stock
                        if (is_null($linea->idalbaran)) {
                            $articulo = $art0->get($linea->referencia);
                            if ($articulo) {
                                $articulo->sum_stock($fact->codalmacen, 0 - $linea->cantidad, TRUE, $linea->codcombinacion);
                            }
                        }
                    }
                }

                /// ¿Esta factura es rectificativa de otra?
                if ($fact->idfacturarect) {
                    $original = $this->factura->get($fact->idfacturarect);
                    if ($original) {
                        $original->anulada = FALSE;
                        $original->save();
                    }
                }

                $this->clean_last_changes();
            } else {
                $this->new_error_msg("¡Imposible eliminar la factura!");
            }
        } else {
            $this->new_error_msg("Factura no encontrada.");
        }
    }

    public function orden()
    {
        return array(
            'fecha_desc' => array(
                'icono' => '<span class="glyphicon glyphicon-sort-by-attributes-alt" aria-hidden="true"></span>',
                'texto' => 'Fecha',
                'orden' => 'fecha DESC'
            ),
            'fecha_asc' => array(
                'icono' => '<span class="glyphicon glyphicon-sort-by-attributes" aria-hidden="true"></span>',
                'texto' => 'Fecha',
                'orden' => 'fecha ASC'
            ),
            'codigo_desc' => array(
                'icono' => '<span class="glyphicon glyphicon-sort-by-attributes-alt" aria-hidden="true"></span>',
                'texto' => 'Código',
                'orden' => 'codigo DESC'
            ),
            'codigo_asc' => array(
                'icono' => '<span class="glyphicon glyphicon-sort-by-attributes" aria-hidden="true"></span>',
                'texto' => 'Código',
                'orden' => 'codigo ASC'
            ),
            'total_desc' => array(
                'icono' => '<span class="glyphicon glyphicon-sort-by-attributes-alt" aria-hidden="true"></span>',
                'texto' => 'Total',
                'orden' => 'total DESC'
            )
        );
    }
}
