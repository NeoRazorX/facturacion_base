<?php
/*
 * This file is part of presupuestos_y_pedidos
 * Copyright (C) 2014-2017  Carlos Garcia Gomez  neorazorx@gmail.com
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

require_once 'plugins/facturacion_base/extras/documento_venta.php';

/**
 * Pedido de cliente.
 */
class pedido_cliente extends \fs_model
{

    use \documento_venta;

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
     * Estado del pedido:
     * 0 -> pendiente. (editable)
     * 1 -> aprobado. (hay un idalbaran y no es editable)
     * 2 -> rechazado. (no hay idalbaran y no es editable)
     * @var integer
     */
    public $status;
    public $editable;

    /**
     * Fecha de salida prevista del material.
     * @var string
     */
    public $fechasalida;

    /**
     * Si este presupuesto es la versión de otro, aquí se almacena el idpresupuesto del original.
     * @var integer
     */
    public $idoriginal;

    public function __construct($data = FALSE)
    {
        parent::__construct('pedidoscli');
        if ($data) {
            $this->load_data_trait($data);
            $this->idpedido = $this->intval($data['idpedido']);
            $this->idalbaran = $this->intval($data['idalbaran']);

            /// calculamos el estado para mantener compatibilidad con eneboo
            $this->status = intval($data['status']);
            $this->editable = $this->str2bool($data['editable']);
            if ($this->idalbaran) {
                $this->status = 1;
                $this->editable = FALSE;
            } else if ($this->status == 2) {
                /// cancelado
                $this->editable = FALSE;
            } else if ($this->editable) {
                $this->status = 0;
            } else {
                $this->status = 2;
            }

            $this->fechasalida = NULL;
            if (!is_null($data['fechasalida'])) {
                $this->fechasalida = Date('d-m-Y', strtotime($data['fechasalida']));
            }

            $this->idoriginal = $this->intval($data['idoriginal']);
        } else {
            $this->clear_trait($this->default_items);
            $this->idpedido = NULL;
            $this->idalbaran = NULL;
            $this->status = 0;
            $this->editable = TRUE;
            $this->fechasalida = NULL;
            $this->idoriginal = NULL;
        }
    }

    public function url()
    {
        if (is_null($this->idpedido)) {
            return 'index.php?page=ventas_pedidos';
        }

        return 'index.php?page=ventas_pedido&id=' . $this->idpedido;
    }

    public function albaran_url()
    {
        if (is_null($this->idalbaran)) {
            return 'index.php?page=ventas_albaran';
        }

        return 'index.php?page=ventas_albaran&id=' . $this->idalbaran;
    }

    /**
     * Devuelve las líneas del pedido.
     * @return \linea_pedido_cliente
     */
    public function get_lineas()
    {
        $linea = new \linea_pedido_cliente();
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
                $versiones[] = new \pedido_cliente($d);
            }
        }

        return $versiones;
    }

    public function get($id)
    {
        $pedido = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE idpedido = " . $this->var2str($id) . ";");
        if ($pedido) {
            return new \pedido_cliente($pedido[0]);
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

    /**
     * Genera un nuevo código y número para el pedido
     */
    public function new_codigo()
    {
        $this->numero = fs_documento_new_numero($this->db, $this->table_name, $this->codejercicio, $this->codserie, 'npedidocli');
        $this->codigo = fs_documento_new_codigo(FS_PEDIDO, $this->codejercicio, $this->codserie, $this->numero);
    }

    /**
     * Comprueba los datos del pedido, devuelve TRUE si está todo correcto
     * @return boolean
     */
    public function test()
    {
        /// comprobamos que editable se corresponda con el status
        if ($this->idalbaran) {
            $this->status = 1;
            $this->editable = FALSE;
        } else if ($this->status == 0) {
            $this->editable = TRUE;
        } else if ($this->status == 2) {
            $this->editable = FALSE;
        }

        return $this->test_trait();
    }

    /**
     * Comprobaciones extra del pedido, devuelve TRUE si está todo correcto
     * @param boolean $duplicados
     * @return boolean
     */
    public function full_test($duplicados = TRUE)
    {
        /// ¿Existe el albarán vinculado?
        if ($this->idalbaran) {
            $alb0 = new \albaran_cliente();
            $albaran = $alb0->get($this->idalbaran);
            if (!$albaran) {
                $this->idalbaran = NULL;
                $this->status = 0;
                $this->editable = TRUE;
                $this->save();
            }
        }

        return $this->full_test_trait(FS_PEDIDO);
    }

    /**
     * Guarda los datos en la base de datos
     * @return boolean
     */
    public function save()
    {
        if ($this->test()) {
            if ($this->exists()) {
                $sql = "UPDATE " . $this->table_name . " SET apartado = " . $this->var2str($this->apartado)
                    . ", cifnif = " . $this->var2str($this->cifnif)
                    . ", ciudad = " . $this->var2str($this->ciudad)
                    . ", codagente = " . $this->var2str($this->codagente)
                    . ", codalmacen = " . $this->var2str($this->codalmacen)
                    . ", codcliente = " . $this->var2str($this->codcliente)
                    . ", coddir = " . $this->var2str($this->coddir)
                    . ", coddivisa = " . $this->var2str($this->coddivisa)
                    . ", codejercicio = " . $this->var2str($this->codejercicio)
                    . ", codigo = " . $this->var2str($this->codigo)
                    . ", codpago = " . $this->var2str($this->codpago)
                    . ", codpais = " . $this->var2str($this->codpais)
                    . ", codpostal = " . $this->var2str($this->codpostal)
                    . ", codserie = " . $this->var2str($this->codserie)
                    . ", direccion = " . $this->var2str($this->direccion)
                    . ", editable = " . $this->var2str($this->editable)
                    . ", fecha = " . $this->var2str($this->fecha)
                    . ", hora = " . $this->var2str($this->hora)
                    . ", idalbaran = " . $this->var2str($this->idalbaran)
                    . ", irpf = " . $this->var2str($this->irpf)
                    . ", netosindto = " . $this->var2str($this->netosindto)
                    . ", neto = " . $this->var2str($this->neto)
                    . ", dtopor1 = " . $this->var2str($this->dtopor1)
                    . ", dtopor2 = " . $this->var2str($this->dtopor2)
                    . ", dtopor3 = " . $this->var2str($this->dtopor3)
                    . ", dtopor4 = " . $this->var2str($this->dtopor4)
                    . ", dtopor5 = " . $this->var2str($this->dtopor5)
                    . ", nombrecliente = " . $this->var2str($this->nombrecliente)
                    . ", numero = " . $this->var2str($this->numero)
                    . ", numero2 = " . $this->var2str($this->numero2)
                    . ", observaciones = " . $this->var2str($this->observaciones)
                    . ", status = " . $this->var2str($this->status)
                    . ", porcomision = " . $this->var2str($this->porcomision)
                    . ", provincia = " . $this->var2str($this->provincia)
                    . ", tasaconv = " . $this->var2str($this->tasaconv)
                    . ", total = " . $this->var2str($this->total)
                    . ", totaleuros = " . $this->var2str($this->totaleuros)
                    . ", totalirpf = " . $this->var2str($this->totalirpf)
                    . ", totaliva = " . $this->var2str($this->totaliva)
                    . ", totalrecargo = " . $this->var2str($this->totalrecargo)
                    . ", femail = " . $this->var2str($this->femail)
                    . ", fechasalida = " . $this->var2str($this->fechasalida)
                    . ", codtrans = " . $this->var2str($this->envio_codtrans)
                    . ", codigoenv = " . $this->var2str($this->envio_codigo)
                    . ", nombreenv = " . $this->var2str($this->envio_nombre)
                    . ", apellidosenv = " . $this->var2str($this->envio_apellidos)
                    . ", apartadoenv = " . $this->var2str($this->envio_apartado)
                    . ", direccionenv = " . $this->var2str($this->envio_direccion)
                    . ", codpostalenv = " . $this->var2str($this->envio_codpostal)
                    . ", ciudadenv = " . $this->var2str($this->envio_ciudad)
                    . ", provinciaenv = " . $this->var2str($this->envio_provincia)
                    . ", codpaisenv = " . $this->var2str($this->envio_codpais)
                    . ", numdocs = " . $this->var2str($this->numdocs)
                    . ", idoriginal = " . $this->var2str($this->idoriginal)
                    . "  WHERE idpedido = " . $this->var2str($this->idpedido) . ";";

                return $this->db->exec($sql);
            }

            $this->new_codigo();
            $sql = "INSERT INTO " . $this->table_name . " (apartado,cifnif,ciudad,codagente,codalmacen,
               codcliente,coddir,coddivisa,codejercicio,codigo,codpais,codpago,codpostal,codserie,
               direccion,editable,fecha,hora,idalbaran,irpf,netosindto,neto,dtopor1,dtopor2,dtopor3,dtopor4,dtopor5,
               nombrecliente,numero,observaciones,status,porcomision,provincia,tasaconv,total,totaleuros,
               totalirpf,totaliva,totalrecargo,numero2,femail,fechasalida,codtrans,codigoenv,nombreenv,
               apellidosenv,apartadoenv,direccionenv,codpostalenv,ciudadenv,provinciaenv,codpaisenv,
               numdocs,idoriginal) VALUES ("
                . $this->var2str($this->apartado) . ","
                . $this->var2str($this->cifnif) . ","
                . $this->var2str($this->ciudad) . ","
                . $this->var2str($this->codagente) . ","
                . $this->var2str($this->codalmacen) . ","
                . $this->var2str($this->codcliente) . ","
                . $this->var2str($this->coddir) . ","
                . $this->var2str($this->coddivisa) . ","
                . $this->var2str($this->codejercicio) . ","
                . $this->var2str($this->codigo) . ","
                . $this->var2str($this->codpais) . ","
                . $this->var2str($this->codpago) . ","
                . $this->var2str($this->codpostal) . ","
                . $this->var2str($this->codserie) . ","
                . $this->var2str($this->direccion) . ","
                . $this->var2str($this->editable) . ","
                . $this->var2str($this->fecha) . ","
                . $this->var2str($this->hora) . ","
                . $this->var2str($this->idalbaran) . ","
                . $this->var2str($this->irpf) . ","
                . $this->var2str($this->netosindto) . ","
                . $this->var2str($this->neto) . ","
                . $this->var2str($this->dtopor1) . ","
                . $this->var2str($this->dtopor2) . ","
                . $this->var2str($this->dtopor3) . ","
                . $this->var2str($this->dtopor4) . ","
                . $this->var2str($this->dtopor5) . ","
                . $this->var2str($this->nombrecliente) . ","
                . $this->var2str($this->numero) . ","
                . $this->var2str($this->observaciones) . ","
                . $this->var2str($this->status) . ","
                . $this->var2str($this->porcomision) . ","
                . $this->var2str($this->provincia) . ","
                . $this->var2str($this->tasaconv) . ","
                . $this->var2str($this->total) . ","
                . $this->var2str($this->totaleuros) . ","
                . $this->var2str($this->totalirpf) . ","
                . $this->var2str($this->totaliva) . ","
                . $this->var2str($this->totalrecargo) . ","
                . $this->var2str($this->numero2) . ","
                . $this->var2str($this->femail) . ","
                . $this->var2str($this->fechasalida) . ","
                . $this->var2str($this->envio_codtrans) . ","
                . $this->var2str($this->envio_codigo) . ","
                . $this->var2str($this->envio_nombre) . ","
                . $this->var2str($this->envio_apellidos) . ","
                . $this->var2str($this->envio_apartado) . ","
                . $this->var2str($this->envio_direccion) . ","
                . $this->var2str($this->envio_codpostal) . ","
                . $this->var2str($this->envio_ciudad) . ","
                . $this->var2str($this->envio_provincia) . ","
                . $this->var2str($this->envio_codpais) . ","
                . $this->var2str($this->numdocs) . ","
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
        if ($this->db->exec("DELETE FROM " . $this->table_name . " WHERE idpedido = " . $this->var2str($this->idpedido) . ";")) {
            /// modificamos el presupuesto relacionado
            $this->db->exec("UPDATE presupuestoscli SET idpedido = NULL, editable = TRUE,"
                . " status = 0 WHERE idpedido = " . $this->var2str($this->idpedido) . ";");

            $this->new_message(ucfirst(FS_PEDIDO) . ' de venta ' . $this->codigo . " eliminado correctamente.");
            return TRUE;
        }

        return FALSE;
    }

    /**
     * Devuelve un array con los últimos pedidos de venta.
     * @param integer $offset
     * @param string $order
     * @return \pedido_cliente
     */
    public function all($offset = 0, $order = 'fecha DESC', $limit = FS_ITEM_LIMIT)
    {
        $sql = "SELECT * FROM " . $this->table_name . " ORDER BY " . $order;

        $data = $this->db->select_limit($sql, $limit, $offset);
        return $this->all_from_data($data);
    }

    /**
     * Devuelve un array con los pedidos de venta pendientes.
     * @param integer $offset
     * @param string $order
     * @return \pedido_cliente
     */
    public function all_ptealbaran($offset = 0, $order = 'fecha ASC', $limit = FS_ITEM_LIMIT)
    {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE idalbaran IS NULL"
            . " AND status = 0 ORDER BY " . $order;

        $data = $this->db->select_limit($sql, $limit, $offset);
        return $this->all_from_data($data);
    }

    /**
     * Devuelve un array con los pedidos de venta rechazados
     * @param integer $offset
     * @param string $order
     * @return \pedido_cliente
     */
    public function all_rechazados($offset = 0, $order = 'fecha DESC', $limit = FS_ITEM_LIMIT)
    {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE status = 2 ORDER BY " . $order;

        $data = $this->db->select_limit($sql, $limit, $offset);
        return $this->all_from_data($data);
    }

    /**
     * Devuelve un array con los pedidos del cliente $codcliente.
     * @param string $codcliente
     * @param integer $offset
     * @return \pedido_cliente
     */
    public function all_from_cliente($codcliente, $offset = 0)
    {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE codcliente = " . $this->var2str($codcliente)
            . " ORDER BY fecha DESC, codigo DESC";

        $data = $this->db->select_limit($sql, FS_ITEM_LIMIT, $offset);
        return $this->all_from_data($data);
    }

    /**
     * Devuelve un array con los pedidos del agente/empleado
     * @param string $codagente
     * @param integer $offset
     * @return \pedido_cliente
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
     * @return \pedido_cliente
     */
    public function all_from_albaran($id)
    {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE idalbaran = " . $this->var2str($id)
            . " ORDER BY fecha DESC, codigo DESC;";

        $data = $this->db->select($sql);
        return $this->all_from_data($data);
    }

    /**
     * Devuelve un array con los pedidos según los filtros:
     * @param string $desde
     * @param string $hasta
     * @param string $codserie
     * @param string $codagente
     * @param string $codcliente
     * @param string $estado
     * @param string $forma_pago
     * @param string $almacen
     * @param string $divisa
     * @return \presupuesto_cliente
     */
    public function all_desde($desde, $hasta, $codserie = FALSE, $codagente = FALSE, $codcliente = FALSE, $estado = FALSE, $forma_pago = FALSE, $almacen = FALSE, $divisa = FALSE)
    {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE fecha >= " . $this->var2str($desde) . " AND fecha <= " . $this->var2str($hasta);
        if ($codserie) {
            $sql .= " AND codserie = " . $this->var2str($codserie);
        }
        if ($codagente) {
            $sql .= " AND codagente = " . $this->var2str($codagente);
        }
        if ($codcliente) {
            $sql .= " AND codcliente = " . $this->var2str($codcliente);
        }
        if ($estado != '') {
            switch ($estado) {
                case '0':
                    $sql .= " AND idalbaran IS NULL AND status = '0'";
                    break;
                case '1':
                    $sql .= " AND status = '1'";
                    break;
                case '2':
                    $sql .= " AND status = '2'";
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
     * @return \pedido_cliente
     */
    public function search($query, $offset = 0)
    {
        $query = mb_strtolower($this->no_html($query), 'UTF8');

        $consulta = "SELECT * FROM " . $this->table_name . " WHERE ";
        if (is_numeric($query)) {
            $consulta .= "codigo LIKE '%" . $query . "%' OR numero2 LIKE '%" . $query . "%' OR observaciones LIKE '%" . $query . "%'
            OR total BETWEEN '" . ($query - .01) . "' AND '" . ($query + .01) . "'";
        } else if (preg_match('/^([0-9]{1,2})-([0-9]{1,2})-([0-9]{4})$/i', $query)) {
            /// es una fecha
            $consulta .= "fecha = " . $this->var2str($query) . " OR observaciones LIKE '%" . $query . "%'";
        } else {
            $consulta .= "lower(codigo) LIKE '%" . $query . "%' OR lower(numero2) LIKE '%" . $query . "%' "
                . "OR lower(observaciones) LIKE '%" . str_replace(' ', '%', $query) . "%'";
        }
        $consulta .= " ORDER BY fecha DESC, codigo DESC";

        $data = $this->db->select_limit($consulta, FS_ITEM_LIMIT, $offset);
        return $this->all_from_data($data);
    }

    /**
     * Devuelve un array con los pedidos que coincicen con $query del cliente $codcliente
     * @param string $codcliente
     * @param string $desde
     * @param string $hasta
     * @param string $serie
     * @param string $obs
     * @return \pedido_cliente
     */
    public function search_from_cliente($codcliente, $desde, $hasta, $serie, $obs = '')
    {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE codcliente = " . $this->var2str($codcliente) .
            " AND idalbaran AND fecha BETWEEN " . $this->var2str($desde) . " AND " . $this->var2str($hasta) .
            " AND codserie = " . $this->var2str($serie);

        if ($obs != '') {
            $sql .= " AND lower(observaciones) = " . $this->var2str(strtolower($obs));
        }

        $sql .= " ORDER BY fecha DESC, codigo DESC;";

        $data = $this->db->select($sql);
        return $this->all_from_data($data);
    }

    private function all_from_data(&$data)
    {
        $pedilist = array();
        if ($data) {
            foreach ($data as $p) {
                $pedilist[] = new \pedido_cliente($p);
            }
        }

        return $pedilist;
    }

    public function cron_job()
    {
        /// marcamos como aprobados los presupuestos con idpedido
        $this->db->exec("UPDATE " . $this->table_name . " SET status = '1', editable = FALSE"
            . " WHERE status != '1' AND idalbaran IS NOT NULL;");

        /// devolvemos al estado pendiente a los pedidos con estado 1 a los que se haya borrado el albarán
        $this->db->exec("UPDATE " . $this->table_name . " SET status = '0', idalbaran = NULL, editable = TRUE "
            . "WHERE status = '1' AND idalbaran NOT IN (SELECT idalbaran FROM albaranescli);");

        /// marcamos como rechazados todos los presupuestos no editables y sin pedido asociado
        $this->db->exec("UPDATE " . $this->table_name . " SET status = '2' WHERE idalbaran IS NULL AND"
            . " editable = false;");

        /// asignamos netosindto a neto a todos los que estén a 0
        $this->db->exec("UPDATE " . $this->table_name . " SET netosindto = neto WHERE netosindto = 0;");
    }
}
