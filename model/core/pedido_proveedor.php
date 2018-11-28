<?php
/*
 * This file is part of presupuestos_y_pedidos
 * Copyright (C) 2014-2017  Carlos Garcia Gomez       neorazorx@gmail.com
 * Copyright (C) 2014-2015  Francesc Pineda Segarra   shawe.ewahs@gmail.com
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

require_once __DIR__ . '/../../extras/documento_compra.php';

/**
 * Pedido de proveedor
 */
class pedido_proveedor extends \fs_model
{

    use \documento_compra;

    /**
     * Clave primaria.
     * @var integer 
     */
    public $idpedido;

    /**
     * ID del albarán relacionado.
     * @var integer 
     */
    public $idalbaran;

    /**
     * Indica si se puede editar o no.
     * @var boolean 
     */
    public $editable;

    /**
     * Si este presupuesto es la versión de otro, aquí se almacena el idpresupuesto del original.
     * @var integer 
     */
    public $idoriginal;

    public function __construct($data = FALSE)
    {
        parent::__construct('pedidosprov');
        if ($data) {
            $this->load_data_trait($data);
            $this->idpedido = $this->intval($data['idpedido']);
            $this->idalbaran = $this->intval($data['idalbaran']);

            $this->editable = $this->str2bool($data['editable']);
            if ($this->idalbaran) {
                $this->editable = FALSE;
            }

            $this->idoriginal = $this->intval($data['idoriginal']);
        } else {
            $this->clear_trait($this->default_items);
            $this->idpedido = NULL;
            $this->idalbaran = NULL;
            $this->editable = TRUE;
            $this->idoriginal = NULL;
        }
    }

    public function url()
    {
        if (is_null($this->idpedido)) {
            return 'index.php?page=compras_pedidos';
        }

        return 'index.php?page=compras_pedido&id=' . $this->idpedido;
    }

    public function albaran_url()
    {
        if (is_null($this->idalbaran)) {
            return 'index.php?page=compras_albaranes';
        }

        return 'index.php?page=compras_albaran&id=' . $this->idalbaran;
    }

    public function get_lineas()
    {
        $linea = new \linea_pedido_proveedor();
        return $linea->all_from_pedido($this->idpedido);
    }

    public function get_versiones()
    {
        $versiones = array();

        $sql = "SELECT * FROM " . $this->table_name . " WHERE idoriginal = " . $this->var2str($this->idpedido);
        if ($this->idoriginal) {
            $sql .= " OR idoriginal = " . $this->var2str($this->idoriginal);
            $sql .= " OR idpedido = " . $this->var2str($this->idoriginal);
        }
        $sql .= "ORDER BY fecha DESC, hora DESC;";

        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $d) {
                $versiones[] = new \pedido_proveedor($d);
            }
        }

        return $versiones;
    }

    public function get($id)
    {
        $data = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE idpedido = " . $this->var2str($id) . ";");
        if ($data) {
            return new \pedido_proveedor($data[0]);
        }

        return FALSE;
    }

    public function exists()
    {
        if (is_null($this->idpedido)) {
            return FALSE;
        }

        return $this->db->select("SELECT * FROM " . $this->table_name . " WHERE idpedido = " . $this->var2str($this->idpedido) . ";");
    }

    public function new_codigo()
    {
        $this->numero = fs_documento_new_numero($this->db, $this->table_name, $this->codejercicio, $this->codserie, 'npedidoprov');
        $this->codigo = fs_documento_new_codigo(FS_PEDIDO, $this->codejercicio, $this->codserie, $this->numero, 'C');
    }

    /**
     * Comprueba los daros del pedido, devuelve TRUE si está todo correcto
     * @return boolean
     */
    public function test()
    {
        return $this->test_trait();
    }

    public function full_test($duplicados = TRUE)
    {
        if ($this->idalbaran) {
            $alb0 = new \albaran_proveedor();
            $albaran = $alb0->get($this->idalbaran);
            if (!$albaran) {
                $this->idalbaran = NULL;
                $this->save();
            }
        }

        return $this->full_test_trait(FS_PEDIDO);
    }

    public function save()
    {
        if ($this->test()) {
            if ($this->exists()) {
                $sql = "UPDATE " . $this->table_name . " SET cifnif = " . $this->var2str($this->cifnif)
                    . ", codagente = " . $this->var2str($this->codagente)
                    . ", codalmacen = " . $this->var2str($this->codalmacen)
                    . ", codproveedor = " . $this->var2str($this->codproveedor)
                    . ", coddivisa = " . $this->var2str($this->coddivisa)
                    . ", codejercicio = " . $this->var2str($this->codejercicio)
                    . ", codigo = " . $this->var2str($this->codigo)
                    . ", codpago = " . $this->var2str($this->codpago)
                    . ", codserie = " . $this->var2str($this->codserie)
                    . ", editable = " . $this->var2str($this->editable)
                    . ", fecha = " . $this->var2str($this->fecha)
                    . ", hora = " . $this->var2str($this->hora)
                    . ", idalbaran = " . $this->var2str($this->idalbaran)
                    . ", irpf = " . $this->var2str($this->irpf)
                    . ", neto = " . $this->var2str($this->neto)
                    . ", nombre = " . $this->var2str($this->nombre)
                    . ", numero = " . $this->var2str($this->numero)
                    . ", numproveedor = " . $this->var2str($this->numproveedor)
                    . ", observaciones = " . $this->var2str($this->observaciones)
                    . ", tasaconv = " . $this->var2str($this->tasaconv)
                    . ", total = " . $this->var2str($this->total)
                    . ", totaleuros = " . $this->var2str($this->totaleuros)
                    . ", totalirpf = " . $this->var2str($this->totalirpf)
                    . ", totaliva = " . $this->var2str($this->totaliva)
                    . ", totalrecargo = " . $this->var2str($this->totalrecargo)
                    . ", numdocs = " . $this->var2str($this->numdocs)
                    . ", idoriginal = " . $this->var2str($this->idoriginal)
                    . "  WHERE idpedido = " . $this->var2str($this->idpedido) . ";";

                return $this->db->exec($sql);
            }

            $this->new_codigo();
            $sql = "INSERT INTO " . $this->table_name . " (cifnif,codagente,codalmacen,codproveedor,
               coddivisa,codejercicio,codigo,codpago,codserie,editable,fecha,hora,idalbaran,irpf,
               neto,nombre,numero,observaciones,tasaconv,total,totaleuros,totalirpf,
               totaliva,totalrecargo,numproveedor,numdocs,idoriginal) VALUES 
                     (" . $this->var2str($this->cifnif)
                . "," . $this->var2str($this->codagente)
                . "," . $this->var2str($this->codalmacen)
                . "," . $this->var2str($this->codproveedor)
                . "," . $this->var2str($this->coddivisa)
                . "," . $this->var2str($this->codejercicio)
                . "," . $this->var2str($this->codigo)
                . "," . $this->var2str($this->codpago)
                . "," . $this->var2str($this->codserie)
                . "," . $this->var2str($this->editable)
                . "," . $this->var2str($this->fecha)
                . "," . $this->var2str($this->hora)
                . "," . $this->var2str($this->idalbaran)
                . "," . $this->var2str($this->irpf)
                . "," . $this->var2str($this->neto)
                . "," . $this->var2str($this->nombre)
                . "," . $this->var2str($this->numero)
                . "," . $this->var2str($this->observaciones)
                . "," . $this->var2str($this->tasaconv)
                . "," . $this->var2str($this->total)
                . "," . $this->var2str($this->totaleuros)
                . "," . $this->var2str($this->totalirpf)
                . "," . $this->var2str($this->totaliva)
                . "," . $this->var2str($this->totalrecargo)
                . "," . $this->var2str($this->numproveedor)
                . "," . $this->var2str($this->numdocs) . ","
                . $this->var2str($this->idoriginal) . ");";

            if ($this->db->exec($sql)) {
                $this->idpedido = $this->db->lastval();
                return TRUE;
            }
        }

        return FALSE;
    }

    /**
     * Elimina el pedido de la base de datos.
     * Devuelve FALSE en caso de fallo.
     * @return boolean
     */
    public function delete()
    {
        $this->new_message(ucfirst(FS_PEDIDO) . ' de compra ' . $this->codigo . " eliminado correctamente.");
        return $this->db->exec("DELETE FROM " . $this->table_name . " WHERE idpedido = " . $this->var2str($this->idpedido) . ";");
    }

    /**
     * Devuelve un array con los últimos pedidos de compra.
     * @param integer $offset
     * @return \pedido_proveedor
     */
    public function all($offset = 0, $order = 'fecha DESC, codigo DESC', $limit = FS_ITEM_LIMIT)
    {
        $sql = "SELECT * FROM " . $this->table_name . " ORDER BY " . $order;

        $data = $this->db->select_limit($sql, $limit, $offset);
        return $this->all_from_data($data);
    }

    /**
     * Devuelve un array con los pedidos de compra pendientes
     * @param integer $offset
     * @param string $order
     * @return \pedido_proveedor
     */
    public function all_ptealbaran($offset = 0, $order = 'fecha ASC, codigo ASC', $limit = FS_ITEM_LIMIT)
    {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE idalbaran IS NULL ORDER BY " . $order;

        $data = $this->db->select_limit($sql, $limit, $offset);
        return $this->all_from_data($data);
    }

    /**
     * Devuelve un array con todos los pedidos del proveedor.
     * @param string $codproveedor
     * @param integer $offset
     * @return \pedido_proveedor
     */
    public function all_from_proveedor($codproveedor, $offset = 0)
    {
        $sql = "SELECT * FROM " . $this->table_name .
            " WHERE codproveedor = " . $this->var2str($codproveedor) .
            " ORDER BY fecha DESC, codigo DESC";

        $data = $this->db->select_limit($sql, FS_ITEM_LIMIT, $offset);
        return $this->all_from_data($data);
    }

    /**
     * Devuelve un array con todos los pedidos del agente/empleado
     * @param string $codagente
     * @param integer $offset
     * @return \pedido_proveedor
     */
    public function all_from_agente($codagente, $offset = 0)
    {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE codagente = " . $this->var2str($codagente)
            . " ORDER BY fecha DESC, codigo DESC";

        $data = $this->db->select_limit($sql, FS_ITEM_LIMIT, $offset);
        return $this->all_from_data($data);
    }

    /**
     * Devuelve todos los pedidos relacionados con el albarán.
     * @param integer $id
     * @return \pedido_proveedor
     */
    public function all_from_albaran($id)
    {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE idalbaran = " . $this->var2str($id)
            . " ORDER BY fecha DESC, codigo DESC;";

        $data = $this->db->select($sql);
        return $this->all_from_data($data);
    }

    /**
     * 
     * @param string $desde
     * @param string $hasta
     * @param string $codserie
     * @param string $codagente
     * @param string $codproveedor
     * @param string $estado
     * @param string $forma_pago
     * @param string $almacen
     * @param string $divisa
     * @return \pedido_proveedor
     */
    public function all_desde($desde, $hasta, $codserie = FALSE, $codagente = FALSE, $codproveedor = FALSE, $estado = FALSE, $forma_pago = FALSE, $almacen = FALSE, $divisa = FALSE)
    {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE fecha >= " . $this->var2str($desde) . " AND fecha <= " . $this->var2str($hasta);
        if ($codserie) {
            $sql .= " AND codserie = " . $this->var2str($codserie);
        }
        if ($codagente) {
            $sql .= " AND codagente = " . $this->var2str($codagente);
        }
        if ($codproveedor) {
            $sql .= " AND codproveedor = " . $this->var2str($codproveedor);
        }
        if ($estado != '') {
            switch ($estado) {
                case '0':
                    $sql .= " AND idalbaran IS NULL ";
                    break;
                case '1':
                    $sql .= " AND idalbaran IS NOT NULL ";
                    break;
            }
        }
        if ($forma_pago) {
            $sql .= " AND codpago = " . $this->var2str($forma_pago);
        }
        if ($divisa) {
            $sql .= "AND coddivisa = " . $this->var2str($divisa);
        }
        if ($almacen) {
            $sql .= "AND codalmacen = " . $this->var2str($almacen);
        }
        $sql .= " ORDER BY fecha ASC, codigo ASC;";

        $data = $this->db->select($sql);
        return $this->all_from_data($data);
    }

    /**
     * Devuelve un array con los pedidos que coinciden con $query
     * @param string $query
     * @param integer $offset
     * @return \pedido_proveedor
     */
    public function search($query, $offset = 0)
    {
        $query = mb_strtolower($this->no_html($query), 'UTF8');

        $consulta = "SELECT * FROM " . $this->table_name . " WHERE ";
        if (is_numeric($query)) {
            $consulta .= "codigo LIKE '%" . $query . "%' OR numproveedor LIKE '%" . $query . "%' OR observaciones LIKE '%" . $query . "%'
            OR total BETWEEN '" . ($query - .01) . "' AND '" . ($query + .01) . "'";
        } else if (preg_match('/^([0-9]{1,2})-([0-9]{1,2})-([0-9]{4})$/i', $query)) {
            /// es una fecha
            $consulta .= "fecha = " . $this->var2str($query) . " OR observaciones LIKE '%" . $query . "%'";
        } else {
            $consulta .= "lower(codigo) LIKE '%" . $query . "%' OR lower(numproveedor) LIKE '%" . $query . "%' "
                . "OR lower(observaciones) LIKE '%" . str_replace(' ', '%', $query) . "%'";
        }
        $consulta .= " ORDER BY fecha DESC, codigo DESC";

        $data = $this->db->select_limit($consulta, FS_ITEM_LIMIT, $offset);
        return $this->all_from_data($data);
    }

    private function all_from_data(&$data)
    {
        $pedilist = array();
        if ($data) {
            foreach ($data as $p) {
                $pedilist[] = new \pedido_proveedor($p);
            }
        }

        return $pedilist;
    }

    public function cron_job()
    {
        $sql = "UPDATE " . $this->table_name . " SET idalbaran = NULL, editable = TRUE"
            . " WHERE idalbaran IS NOT NULL AND NOT EXISTS(SELECT 1 FROM albaranesprov t1 WHERE t1.idalbaran = " . $this->table_name . ".idalbaran);";
        $this->db->exec($sql);
    }
}
