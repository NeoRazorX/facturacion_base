<?php
/*
 * This file is part of facturacion_base
 * Copyright (C) 2013-2017  Carlos Garcia Gomez  neorazorx@gmail.com
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

require_once __DIR__ . '/../../extras/linea_documento_compra.php';

/**
 * Línea de un albarán de proveedor.
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class linea_albaran_proveedor extends \fs_model
{

    use \linea_documento_compra;

    /**
     * ID de la línea del pedido relacionada, si la hay.
     * @var integer 
     */
    public $idlineapedido;

    /**
     * ID del albarán de esta línea.
     * @var integer 
     */
    public $idalbaran;

    /**
     * ID del pedido relacionado con el albarán, si lo hay.
     * @var integer 
     */
    public $idpedido;
    private $codigo;
    private $fecha;
    private static $albaranes;

    public function __construct($data = FALSE)
    {
        parent::__construct('lineasalbaranesprov');

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
            $alb = new \albaran_proveedor();
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

    public function show_nombre()
    {
        $nombre = 'desconocido';

        foreach (self::$albaranes as $a) {
            if ($a->idalbaran == $this->idalbaran) {
                $nombre = $a->nombre;
                break;
            }
        }

        return $nombre;
    }

    public function url()
    {
        return 'index.php?page=compras_albaran&id=' . $this->idalbaran;
    }

    /**
     * Devuelve los datos de una linea
     * @param integer $idlinea
     * @return boolean|\linea_albaran_proveedor
     */
    public function get($idlinea)
    {
        $data = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE idlinea = " . $this->var2str($idlinea) . ";");
        if ($data) {
            return new \linea_albaran_proveedor($data[0]);
        }

        return FALSE;
    }

    public function exists()
    {
        if (is_null($this->idlinea)) {
            return false;
        }

        return $this->db->select("SELECT * FROM " . $this->table_name . " WHERE idlinea = " . $this->var2str($this->idlinea) . ";");
    }

    public function save()
    {
        if ($this->test()) {
            $this->clean_cache();

            if ($this->exists()) {
                $sql = "UPDATE " . $this->table_name . " SET idalbaran = " . $this->var2str($this->idalbaran)
                    . ", idpedido = " . $this->var2str($this->idpedido)
                    . ", idlineapedido = " . $this->var2str($this->idlineapedido)
                    . ", referencia = " . $this->var2str($this->referencia)
                    . ", codcombinacion = " . $this->var2str($this->codcombinacion)
                    . ", descripcion = " . $this->var2str($this->descripcion)
                    . ", cantidad = " . $this->var2str($this->cantidad)
                    . ", dtopor = " . $this->var2str($this->dtopor)
                    . ", codimpuesto = " . $this->var2str($this->codimpuesto)
                    . ", iva = " . $this->var2str($this->iva)
                    . ", pvptotal = " . $this->var2str($this->pvptotal)
                    . ", pvpsindto = " . $this->var2str($this->pvpsindto)
                    . ", pvpunitario = " . $this->var2str($this->pvpunitario)
                    . ", irpf = " . $this->var2str($this->irpf)
                    . ", recargo = " . $this->var2str($this->recargo)
                    . "  WHERE idlinea = " . $this->var2str($this->idlinea) . ";";

                return $this->db->exec($sql);
            }

            $sql = "INSERT INTO " . $this->table_name . " (idlineapedido,idalbaran,idpedido,referencia,codcombinacion,
               descripcion,cantidad,dtopor,codimpuesto,iva,pvptotal,pvpsindto,pvpunitario,irpf,recargo) VALUES
                     (" . $this->var2str($this->idlineapedido) .
                "," . $this->var2str($this->idalbaran) .
                "," . $this->var2str($this->idpedido) .
                "," . $this->var2str($this->referencia) .
                "," . $this->var2str($this->codcombinacion) .
                "," . $this->var2str($this->descripcion) .
                "," . $this->var2str($this->cantidad) .
                "," . $this->var2str($this->dtopor) .
                "," . $this->var2str($this->codimpuesto) .
                "," . $this->var2str($this->iva) .
                "," . $this->var2str($this->pvptotal) .
                "," . $this->var2str($this->pvpsindto) .
                "," . $this->var2str($this->pvpunitario) .
                "," . $this->var2str($this->irpf) .
                "," . $this->var2str($this->recargo) . ");";

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
        $this->cache->delete('albpro_top_articulos');
    }

    public function all_from_albaran($id)
    {
        $linealist = array();
        $sql = "SELECT * FROM " . $this->table_name . " WHERE idalbaran = " . $this->var2str($id)
            . " ORDER BY idlinea ASC;";

        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $l) {
                $linealist[] = new \linea_albaran_proveedor($l);
            }
        }

        return $linealist;
    }

    private function all_from($sql, $offset = 0, $limit = FS_ITEM_LIMIT)
    {
        $linealist = array();
        $data = $this->db->select_limit($sql, $limit, $offset);
        if ($data) {
            foreach ($data as $l) {
                $linealist[] = new \linea_albaran_proveedor($l);
            }
        }

        return $linealist;
    }

    public function all_from_articulo($ref, $offset = 0, $limit = FS_ITEM_LIMIT)
    {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE referencia = " . $this->var2str($ref) . " ORDER BY idalbaran DESC";
        return $this->all_from($sql, $offset, $limit);
    }

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

    public function search_from_proveedor($codproveedor, $query = '', $offset = 0)
    {
        $query = mb_strtolower($this->no_html($query), 'UTF8');

        $sql = "SELECT * FROM " . $this->table_name . " WHERE idalbaran IN
         (SELECT idalbaran FROM albaranesprov WHERE codproveedor = " . $this->var2str($codproveedor) . ") AND ";
        if (is_numeric($query)) {
            $sql .= "(referencia LIKE '%" . $query . "%' OR descripcion LIKE '%" . $query . "%')";
        } else {
            $buscar = str_replace(' ', '%', $query);
            $sql .= "(lower(referencia) LIKE '%" . $buscar . "%' OR lower(descripcion) LIKE '%" . $buscar . "%')";
        }
        $sql .= " ORDER BY idalbaran DESC, idlinea ASC";

        return $this->all_from($sql, $offset);
    }

    public function count_by_articulo()
    {
        $lineas = $this->db->select("SELECT COUNT(DISTINCT referencia) as total FROM " . $this->table_name . ";");
        if ($lineas) {
            return intval($lineas[0]['total']);
        }

        return 0;
    }
}
