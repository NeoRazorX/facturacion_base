<?php
/*
 * This file is part of facturacion_base
 * Copyright (C) 2013-2017  Carlos Garcia Gomez  neorazorx@gmail.com
 * Copyright (C) 2017       Francesc Pineda Segarra  shawe.ewahs@gmail.com
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
namespace FacturaScripts\model;

/**
 * Línea de un albarán de cliente.
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class linea_albaran_cliente extends \fs_model
{

    /**
     * Clave primaria.
     * @var integer
     */
    public $idlinea;

    /**
     * ID de la línea relacionada del pedido relacionado,
     * si lo hay.
     * @var integer
     */
    public $idlineapedido;

    /**
     * ID del albaran de esta línea.
     * @var integer
     */
    public $idalbaran;

    /**
     * ID del pedido relacionado con el albarán relacionado.
     * @var integer
     */
    public $idpedido;

    /**
     * Cantidad del artículo.
     * @var float
     */
    public $cantidad;

    /**
     * Código del impuesto relacionado.
     * @var string
     */
    public $codimpuesto;

    /**
     * Descripción del artículo.
     * @var string
     */
    public $descripcion;

    /**
     * % de descuento.
     * @var float
     */
    public $dtopor;

    /**
     * % de descuento 2
     * @var float
     */
    public $dtopor2;
    
    /**
     * % de descuento 3
     * @var float
     */
    public $dtopor3;
    
    /**
     * % de descuento 4
     * @var float
     */
    public $dtopor4;

    /**
     * % de retención IRPF.
     * @var float
     */
    public $irpf;

    /**
     * % de IVA de la línea, el que corresponde al impuesto.
     * @var float
     */
    public $iva;

    /**
     * Importe neto sin descuento, es decir, pvpunitario * cantidad.
     * @var float
     */
    public $pvpsindto;

    /**
     * Importe neto de la línea, sin impuestos.
     * @var float
     */
    public $pvptotal;

    /**
     * Precio del artículo, una unidad.
     * @var float
     */
    public $pvpunitario;

    /**
     * % de recargo de equivalencia RE.
     * @var float
     */
    public $recargo;

    /**
     * Referencia del artículo.
     * @var string
     */
    public $referencia;

    /**
     * Código de la combinación seleccionada, en el caso de los artículos con atributos.
     * @var string
     */
    public $codcombinacion;

    /**
     * Posición de la linea en el documento. Cuanto más alto más abajo.
     * @var integer
     */
    public $orden;

    /**
     * False -> no se muestra la columna cantidad al imprimir.
     * @var boolean
     */
    public $mostrar_cantidad;

    /**
     * False -> no se muestran las columnas precio, descuento, impuestos y total al imprimir.
     * @var boolean
     */
    public $mostrar_precio;

    /**
     * TODO
     * @var
     */
    private $codigo;

    /**
     * TODO
     * @var
     */
    private $fecha;

    /**
     * Listado de albaranes.
     * @var array
     */
    private static $albaranes;

    public function __construct($data = FALSE)
    {
        parent::__construct('lineasalbaranescli');

        if (!isset(self::$albaranes)) {
            self::$albaranes = array();
        }

        if ($data) {
            $this->idlinea = $this->intval($data['idlinea']);
            $this->idlineapedido = $this->intval($data['idlineapedido']);
            $this->idalbaran = $this->intval($data['idalbaran']);
            $this->idpedido = $this->intval($data['idpedido']);
            $this->cantidad = floatval($data['cantidad']);
            $this->codcombinacion = $data['codcombinacion'];
            $this->codimpuesto = $data['codimpuesto'];
            $this->descripcion = $data['descripcion'];
            $this->dtopor = floatval($data['dtopor']);
            $this->dtopor2 = floatval($data['dtopor2']);
            $this->dtopor3 = floatval($data['dtopor3']);
            $this->dtopor4 = floatval($data['dtopor4']);
            $this->irpf = floatval($data['irpf']);
            $this->iva = floatval($data['iva']);
            $this->pvpsindto = floatval($data['pvpsindto']);
            $this->pvptotal = floatval($data['pvptotal']);
            $this->pvpunitario = floatval($data['pvpunitario']);
            $this->recargo = floatval($data['recargo']);
            $this->referencia = $data['referencia'];
            $this->orden = intval($data['orden']);
            $this->mostrar_cantidad = $this->str2bool($data['mostrar_cantidad']);
            $this->mostrar_precio = $this->str2bool($data['mostrar_precio']);
        } else {
            $this->idlinea = NULL;
            $this->idlineapedido = NULL;
            $this->idalbaran = NULL;
            $this->idpedido = NULL;
            $this->cantidad = 0.0;
            $this->codimpuesto = NULL;
            $this->codcombinacion = NULL;
            $this->descripcion = '';
            $this->dtopor = 0.0;
            $this->dtopor2 = 0.0;
            $this->dtopor3 = 0.0;
            $this->dtopor4 = 0.0;
            $this->irpf = 0.0;
            $this->iva = 0.0;
            $this->mostrar_cantidad = TRUE;
            $this->mostrar_precio = TRUE;
            $this->orden = 0;
            $this->pvpsindto = 0.0;
            $this->pvptotal = 0.0;
            $this->pvpunitario = 0.0;
            $this->recargo = 0.0;
            $this->referencia = NULL;
        }
    }

    protected function install()
    {
        return '';
    }

    /**
     * Completa con los datos del albarán.
     */
    private function fill()
    {
        $encontrado = FALSE;
        foreach (self::$albaranes as $a) {
            if ($a->idalbaran == $this->idalbaran) {
                $this->codigo = $a->codigo;
                $this->fecha = $a->fecha;
                $encontrado = TRUE;
                break;
            }
        }
        if (!$encontrado) {
            $alb = new \albaran_cliente();
            $alb = $alb->get($this->idalbaran);
            if ($alb) {
                $this->codigo = $alb->codigo;
                $this->fecha = $alb->fecha;
                self::$albaranes[] = $alb;
            }
        }
    }

    public function pvp_iva()
    {
        return $this->pvpunitario * (100 + $this->iva) / 100;
    }

    public function total_iva()
    {
        return $this->pvptotal * (100 + $this->iva - $this->irpf + $this->recargo) / 100;
    }

    /// Devuelve el precio total por unidad (con descuento incluido e iva aplicado)
    public function total_iva2()
    {
        if ($this->cantidad == 0) {
            return 0;
        }

        return $this->pvptotal * (100 + $this->iva) / 100 / $this->cantidad;
    }

    public function descripcion()
    {
        return nl2br($this->descripcion);
    }

    public function show_codigo()
    {
        if (!isset($this->codigo)) {
            $this->fill();
        }

        return $this->codigo;
    }

    public function show_fecha()
    {
        if (!isset($this->fecha)) {
            $this->fill();
        }

        return $this->fecha;
    }

    public function show_nombrecliente()
    {
        $nombre = 'desconocido';

        foreach (self::$albaranes as $a) {
            if ($a->idalbaran == $this->idalbaran) {
                $nombre = $a->nombrecliente;
                break;
            }
        }

        return $nombre;
    }

    public function url()
    {
        return 'index.php?page=ventas_albaran&id=' . $this->idalbaran;
    }

    public function articulo_url()
    {
        if (is_null($this->referencia) OR $this->referencia == '') {
            return "index.php?page=ventas_articulos";
        }

        return "index.php?page=ventas_articulo&ref=" . urlencode($this->referencia);
    }

    /**
     * Devuelve los datos de una linea.
     * @param type $idlinea
     * @return boolean|\linea_albaran_cliente
     */
    public function get($idlinea)
    {
        $data = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE idlinea = " . $this->var2str($idlinea) . ";");
        if ($data) {
            return new \linea_albaran_cliente($data[0]);
        }

        return FALSE;
    }

    public function exists()
    {
        if (is_null($this->idlinea)) {
            return FALSE;
        }

        return $this->db->select("SELECT * FROM " . $this->table_name . " WHERE idlinea = " . $this->var2str($this->idlinea) . ";");
    }

    public function test()
    {
        $this->descripcion = $this->no_html($this->descripcion);
        $totalsindto = $this->pvpunitario * $this->cantidad;
        $dto_due = (1-((1-$this->dtopor/100)*(1-$this->dtopor2/100)*(1-$this->dtopor3/100)*(1-$this->dtopor4/100)))*100;
        $total = $totalsindto * (1 - $dto_due / 100);

        if (!$this->floatcmp($this->pvptotal, $total, FS_NF0, TRUE)) {
            $this->new_error_msg("Error en el valor de pvptotal de la línea " . $this->referencia . " del " . FS_ALBARAN . ". Valor correcto: " . $total . " y se recibe " . $this->pvptotal);
            return FALSE;
        } else if (!$this->floatcmp($this->pvpsindto, $totalsindto, FS_NF0, TRUE)) {
            $this->new_error_msg("Error en el valor de pvpsindto de la línea " . $this->referencia . " del " . FS_ALBARAN . ". Valor correcto: " . $totalsindto . " y se recibe " . $this->pvpsindto);
            return FALSE;
        }

        return TRUE;
    }

    public function save()
    {
        if ($this->test()) {
            $this->clean_cache();

            if ($this->exists()) {
                $sql = "UPDATE " . $this->table_name . " SET "
                    . "cantidad = " . $this->var2str($this->cantidad)
                    . ", codimpuesto = " . $this->var2str($this->codimpuesto)
                    . ", codcombinacion = " . $this->var2str($this->codcombinacion)
                    . ", descripcion = " . $this->var2str($this->descripcion)
                    . ", dtopor = " . $this->var2str($this->dtopor)
                    . ", dtopor2 = " . $this->var2str($this->dtopor2)
                    . ", dtopor3 = " . $this->var2str($this->dtopor3)
                    . ", dtopor4 = " . $this->var2str($this->dtopor4)
                    . ", idalbaran = " . $this->var2str($this->idalbaran)
                    . ", idlineapedido = " . $this->var2str($this->idlineapedido)
                    . ", idpedido = " . $this->var2str($this->idpedido)
                    . ", irpf = " . $this->var2str($this->irpf)
                    . ", iva = " . $this->var2str($this->iva)
                    . ", pvpsindto = " . $this->var2str($this->pvpsindto)
                    . ", pvptotal = " . $this->var2str($this->pvptotal)
                    . ", pvpunitario = " . $this->var2str($this->pvpunitario)
                    . ", recargo = " . $this->var2str($this->recargo)
                    . ", referencia = " . $this->var2str($this->referencia)
                    . ", orden = " . $this->var2str($this->orden)
                    . ", mostrar_cantidad = " . $this->var2str($this->mostrar_cantidad)
                    . ", mostrar_precio = " . $this->var2str($this->mostrar_precio)
                    . "  WHERE idlinea = " . $this->var2str($this->idlinea) . ";";

                return $this->db->exec($sql);
            }

            $sql = "INSERT INTO " . $this->table_name . " (idlineapedido,idalbaran,idpedido,referencia,codcombinacion,
               descripcion,cantidad,dtopor,dtopor2,dtopor3,dtopor4,codimpuesto,iva,pvptotal,pvpsindto,pvpunitario,irpf,recargo,orden,
               mostrar_cantidad,mostrar_precio) VALUES
                      (" . $this->var2str($this->idlineapedido)
                . "," . $this->var2str($this->idalbaran)
                . "," . $this->var2str($this->idpedido)
                . "," . $this->var2str($this->referencia)
                . "," . $this->var2str($this->codcombinacion)
                . "," . $this->var2str($this->descripcion)
                . "," . $this->var2str($this->cantidad)
                . "," . $this->var2str($this->dtopor)
                . "," . $this->var2str($this->dtopor2)
                . "," . $this->var2str($this->dtopor3)
                . "," . $this->var2str($this->dtopor4)
                . "," . $this->var2str($this->codimpuesto)
                . "," . $this->var2str($this->iva)
                . "," . $this->var2str($this->pvptotal)
                . "," . $this->var2str($this->pvpsindto)
                . "," . $this->var2str($this->pvpunitario)
                . "," . $this->var2str($this->irpf)
                . "," . $this->var2str($this->recargo)
                . "," . $this->var2str($this->orden)
                . "," . $this->var2str($this->mostrar_cantidad)
                . "," . $this->var2str($this->mostrar_precio) . ");";

            if ($this->db->exec($sql)) {
                $this->idlinea = $this->db->lastval();
                return TRUE;
            }
        }

        return FALSE;
    }

    public function delete()
    {
        $this->clean_cache();
        return $this->db->exec("DELETE FROM " . $this->table_name . " WHERE idlinea = " . $this->var2str($this->idlinea) . ";");
    }

    public function clean_cache()
    {
        $this->cache->delete('albcli_top_articulos');
    }

    /**
     * Devuelve las líneas del albarán.
     * @param type $id
     * @return \linea_albaran_cliente
     */
    public function all_from_albaran($id)
    {
        $linealist = array();
        $sql = "SELECT * FROM " . $this->table_name . " WHERE idalbaran = " . $this->var2str($id)
            . " ORDER BY orden DESC, idlinea ASC;";

        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $l) {
                $linealist[] = new \linea_albaran_cliente($l);
            }
        }

        return $linealist;
    }

    private function all_from($sql, $offset = 0, $limit = FS_ITEM_LIMIT)
    {
        $linealist = array();
        $data = $this->db->select_limit($sql, $limit, $offset);
        if ($data) {
            foreach ($data as $a) {
                $linealist[] = new \linea_albaran_cliente($a);
            }
        }

        return $linealist;
    }

    public function all_from_articulo($ref, $offset = 0, $limit = FS_ITEM_LIMIT)
    {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE referencia = " . $this->var2str($ref)
            . " ORDER BY idalbaran DESC";

        return $this->all_from($sql, $offset, $limit);
    }

    /**
     * Busca todas las coincidencias de $query en las líneas.
     * @param string $query
     * @param integer $offset
     * @return \linea_pedido_cliente
     */
    public function search($query = '', $offset = 0)
    {
        $query = mb_strtolower($this->no_html($query), 'UTF8');

        $sql = "SELECT * FROM " . $this->table_name . " WHERE ";
        if (is_numeric($query)) {
            $sql .= "referencia LIKE '%" . $query . "%' OR descripcion LIKE '%" . $query . "%'";
        } else {
            $buscar = str_replace(' ', '%', $query);
            $sql .= "lower(referencia) LIKE '%" . $buscar . "%' OR lower(descripcion) LIKE '%" . $buscar . "%'";
        }
        $sql .= " ORDER BY idalbaran DESC, idlinea ASC";

        return $this->all_from($sql, $offset);
    }

    public function search_from_cliente($codcliente, $query = '', $offset = 0)
    {
        $query = mb_strtolower($this->no_html($query), 'UTF8');

        $sql = "SELECT * FROM " . $this->table_name . " WHERE idalbaran IN
         (SELECT idalbaran FROM albaranescli WHERE codcliente = " . $this->var2str($codcliente) . ") AND ";
        if (is_numeric($query)) {
            $sql .= "(referencia LIKE '%" . $query . "%' OR descripcion LIKE '%" . $query . "%')";
        } else {
            $buscar = str_replace(' ', '%', $query);
            $sql .= "(lower(referencia) LIKE '%" . $buscar . "%' OR lower(descripcion) LIKE '%" . $buscar . "%')";
        }
        $sql .= " ORDER BY idalbaran DESC, idlinea ASC";

        return $this->all_from($sql, $offset);
    }

    public function search_from_cliente2($codcliente, $ref = '', $obs = '', $offset = 0)
    {
        $ref = mb_strtolower($this->no_html($ref), 'UTF8');
        $obs = mb_strtolower($this->no_html($obs), 'UTF8');

        $sql = "SELECT * FROM " . $this->table_name . " WHERE idalbaran IN
         (SELECT idalbaran FROM albaranescli WHERE codcliente = " . $this->var2str($codcliente) . "
         AND lower(observaciones) LIKE '" . $obs . "%') AND ";
        if (is_numeric($ref)) {
            $sql .= "(referencia LIKE '%" . $ref . "%' OR descripcion LIKE '%" . $ref . "%')";
        } else {
            $ref = str_replace(' ', '%', $ref);
            $sql .= "(lower(referencia) LIKE '%" . $ref . "%' OR lower(descripcion) LIKE '%" . $ref . "%')";
        }
        $sql .= " ORDER BY idalbaran DESC, idlinea ASC";

        return $this->all_from($sql, $offset);
    }

    public function last_from_cliente($codcliente, $offset = 0)
    {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE idalbaran IN"
            . " (SELECT idalbaran FROM albaranescli WHERE codcliente = " . $this->var2str($codcliente) . ")"
            . " ORDER BY idalbaran DESC, idlinea ASC";

        return $this->all_from($sql, $offset);
    }

    public function count_by_articulo()
    {
        $data = $this->db->select("SELECT COUNT(DISTINCT referencia) as total FROM " . $this->table_name . ";");
        if ($data) {
            return intval($data[0]['total']);
        }

        return 0;
    }
}
