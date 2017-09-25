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

require_once __DIR__ . '/../../extras/linea_documento_venta.php';

/**
 * Línea de un albarán de cliente.
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class linea_albaran_cliente extends \fs_model
{

    use \linea_documento_venta;

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
            $this->load_data_trait($data);
            $this->idlineapedido = $this->intval($data['idlineapedido']);
            $this->idalbaran = $this->intval($data['idalbaran']);
            $this->idpedido = $this->intval($data['idpedido']);
        } else {
            $this->clear_trait();
            $this->idlineapedido = NULL;
            $this->idalbaran = NULL;
            $this->idpedido = NULL;
        }
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

    /// Devuelve el precio total por unidad (con descuento incluido e iva aplicado)
    public function total_iva2()
    {
        if ($this->cantidad == 0) {
            return 0;
        }

        return $this->pvptotal * (100 + $this->iva) / 100 / $this->cantidad;
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
