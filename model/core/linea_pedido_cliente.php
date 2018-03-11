<?php
/*
 * This file is part of presupuestos_y_pedidos
 * Copyright (C) 2014-2018  Carlos Garcia Gomez  neorazorx@gmail.com
 * Copyright (C) 2014-2017  Francesc Pineda Segarra  shawe.ewahs@gmail.com
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
 * Línea de pedido de cliente.
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class linea_pedido_cliente extends \fs_model
{

    use \linea_documento_venta;

    /**
     * ID de la linea relacionada en el presupuesto relacionado,
     * si lo hay.
     * @var integer
     */
    public $idlineapresupuesto;

    /**
     * ID del pedido de esta línea.
     * @var integer
     */
    public $idpedido;

    /**
     * ID del presupuesto relacionado con este pedido, si lo hay.
     * @var integer
     */
    public $idpresupuesto;

    /**
     * Listado de pedidos.
     * @var array
     */
    private static $pedidos;

    public function __construct($data = FALSE)
    {
        parent::__construct('lineaspedidoscli');

        if (!isset(self::$pedidos)) {
            self::$pedidos = array();
        }

        if ($data) {
            $this->load_data_trait($data);
            $this->idlineapresupuesto = $this->intval($data['idlineapresupuesto']);
            $this->idpedido = $this->intval($data['idpedido']);
            $this->idpresupuesto = $this->intval($data['idpresupuesto']);
        } else {
            $this->clear_trait();
            $this->idlineapresupuesto = NULL;
            $this->idpedido = NULL;
            $this->idpresupuesto = NULL;
        }
    }

    public function show_codigo()
    {
        $codigo = 'desconocido';

        $encontrado = FALSE;
        foreach (self::$pedidos as $p) {
            if ($p->idpedido == $this->idpedido) {
                $codigo = $p->codigo;
                $encontrado = TRUE;
                break;
            }
        }

        if (!$encontrado) {
            $pre = new \pedido_cliente();
            self::$pedidos[] = $pre->get($this->idpedido);
            $codigo = self::$pedidos[count(self::$pedidos) - 1]->codigo;
        }

        return $codigo;
    }

    public function show_fecha()
    {
        $fecha = 'desconocida';

        $encontrado = FALSE;
        foreach (self::$pedidos as $p) {
            if ($p->idpedido == $this->idpedido) {
                $fecha = $p->fecha;
                $encontrado = TRUE;
                break;
            }
        }

        if (!$encontrado) {
            $pre = new \pedido_cliente();
            self::$pedidos[] = $pre->get($this->idpedido);
            $fecha = self::$pedidos[count(self::$pedidos) - 1]->fecha;
        }

        return $fecha;
    }

    public function show_nombrecliente()
    {
        $nombre = 'desconocido';

        $encontrado = FALSE;
        foreach (self::$pedidos as $p) {
            if ($p->idpedido == $this->idpedido) {
                $nombre = $p->nombrecliente;
                $encontrado = TRUE;
                break;
            }
        }

        if (!$encontrado) {
            $pre = new \pedido_cliente();
            self::$pedidos[] = $pre->get($this->idpedido);
            $nombre = self::$pedidos[count(self::$pedidos) - 1]->nombrecliente;
        }

        return $nombre;
    }

    public function url()
    {
        return 'index.php?page=ventas_pedido&id=' . $this->idpedido;
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
                    . ", idpedido = " . $this->var2str($this->idpedido)
                    . ", idlineapresupuesto = " . $this->var2str($this->idlineapresupuesto)
                    . ", idpresupuesto = " . $this->var2str($this->idpresupuesto)
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

            $sql = "INSERT INTO " . $this->table_name . " (cantidad,codimpuesto,descripcion,dtopor,dtopor2,dtopor3,dtopor4,
               idpedido,irpf,iva,pvpsindto,pvptotal,pvpunitario,recargo,referencia,codcombinacion,idlineapresupuesto,
               idpresupuesto,orden,mostrar_cantidad,mostrar_precio) VALUES 
                      (" . $this->var2str($this->cantidad)
                . "," . $this->var2str($this->codimpuesto)
                . "," . $this->var2str($this->descripcion)
                . "," . $this->var2str($this->dtopor)
                . "," . $this->var2str($this->dtopor2)
                . "," . $this->var2str($this->dtopor3)
                . "," . $this->var2str($this->dtopor4)
                . "," . $this->var2str($this->idpedido)
                . "," . $this->var2str($this->irpf)
                . "," . $this->var2str($this->iva)
                . "," . $this->var2str($this->pvpsindto)
                . "," . $this->var2str($this->pvptotal)
                . "," . $this->var2str($this->pvpunitario)
                . "," . $this->var2str($this->recargo)
                . "," . $this->var2str($this->referencia)
                . "," . $this->var2str($this->codcombinacion)
                . "," . $this->var2str($this->idlineapresupuesto)
                . "," . $this->var2str($this->idpresupuesto)
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
        return $this->db->exec("DELETE FROM " . $this->table_name . " WHERE idlinea = " . $this->var2str($this->idlinea) . ";");
    }

    /**
     * Devuelve todas las líneas del pedido $idp
     * @param integer $idp
     * @return \linea_pedido_cliente
     */
    public function all_from_pedido($idp)
    {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE idpedido = " . $this->var2str($idp)
            . " ORDER BY orden DESC, idlinea ASC;";

        $data = $this->db->select($sql);
        return $this->all_from_data($data);
    }

    /**
     * Devuelve todas las líneas que hagan referencia al artículo $ref
     * @param string $ref
     * @param integer $offset
     * @param integer $limit
     * @return \linea_pedido_cliente
     */
    public function all_from_articulo($ref, $offset = 0, $limit = FS_ITEM_LIMIT)
    {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE referencia = " . $this->var2str($ref)
            . " ORDER BY idpedido DESC";

        $data = $this->db->select_limit($sql, $limit, $offset);
        return $this->all_from_data($data);
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
        $sql .= " ORDER BY idpedido DESC, idlinea ASC";

        $data = $this->db->select_limit($sql, FS_ITEM_LIMIT, $offset);
        return $this->all_from_data($data);
    }

    /**
     * Busca todas las coincidencias de $query en las líneas del cliente $codcliente
     * @param string $codcliente
     * @param string $ref
     * @param string $obs
     * @param integer $offset
     * @return \linea_pedido_cliente
     */
    public function search_from_cliente2($codcliente, $ref = '', $obs = '', $offset = 0)
    {
        $ref = mb_strtolower($this->no_html($ref), 'UTF8');
        $obs = mb_strtolower($this->no_html($obs), 'UTF8');

        $sql = "SELECT * FROM " . $this->table_name . " WHERE idpedido IN
         (SELECT idpedido FROM pedidoscli WHERE codcliente = " . $this->var2str($codcliente) . "
         AND lower(observaciones) LIKE '" . $obs . "%') AND ";
        if (is_numeric($ref)) {
            $sql .= "(referencia LIKE '%" . $ref . "%' OR descripcion LIKE '%" . $ref . "%')";
        } else {
            $buscar = str_replace(' ', '%', $ref);
            $sql .= "(lower(referencia) LIKE '%" . $buscar . "%' OR lower(descripcion) LIKE '%" . $buscar . "%')";
        }
        $sql .= " ORDER BY idpedido DESC, idlinea ASC";

        $data = $this->db->select_limit($sql, FS_ITEM_LIMIT, $offset);
        return $this->all_from_data($data);
    }

    private function all_from_data(&$data)
    {
        $linealist = array();
        if ($data) {
            foreach ($data as $l) {
                $linealist[] = new \linea_pedido_cliente($l);
            }
        }

        return $linealist;
    }
}
