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

require_once __DIR__ . '/../../extras/documento_venta.php';

/**
 * Albarán de cliente o albarán de venta. Representa la entrega a un cliente
 * de un material que se le ha vendido. Implica la salida de ese material
 * del almacén de la empresa.
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class albaran_cliente extends \fs_model
{

    use \documento_venta;

    /**
     * Clave primaria. Integer.
     * @var integer 
     */
    public $idalbaran;

    /**
     * ID de la factura relacionada, si la hay.
     * @var integer 
     */
    public $idfactura;

    /**
     * TRUE => está pendiente de factura.
     * @var bool
     */
    public $ptefactura;

    public function __construct($data = FALSE)
    {
        parent::__construct('albaranescli');
        if ($data) {
            $this->load_data_trait($data);
            $this->idalbaran = $this->intval($data['idalbaran']);

            $this->idfactura = $this->intval($data['idfactura']);
            if ($this->idfactura == 0) {
                $this->idfactura = NULL;
            }

            $this->ptefactura = $this->str2bool($data['ptefactura']);
            if ($this->idfactura) {
                /// si ya hay una factura enlazada, no puede estar pendiente de factura
                $this->ptefactura = FALSE;
            }
        } else {
            $this->clear_trait($this->default_items);
            $this->idalbaran = NULL;
            $this->idfactura = NULL;
            $this->ptefactura = TRUE;
        }
    }

    protected function install()
    {
        /// nos aseguramos de que se comprueba la tabla de facturas antes
        new \factura_cliente();

        return '';
    }

    public function url()
    {
        if (is_null($this->idalbaran)) {
            return 'index.php?page=ventas_albaranes';
        }

        return 'index.php?page=ventas_albaran&id=' . $this->idalbaran;
    }

    public function factura_url()
    {
        if (is_null($this->idfactura)) {
            return '#';
        }

        return 'index.php?page=ventas_factura&id=' . $this->idfactura;
    }

    /**
     * Devuelve las líneas del albarán.
     * @return \linea_albaran_cliente
     */
    public function get_lineas()
    {
        $linea = new \linea_albaran_cliente();
        return $linea->all_from_albaran($this->idalbaran);
    }

    /**
     * Devuelve el albarán solicitado o false si no se encuentra.
     * @param string $id
     * @return \albaran_cliente|boolean
     */
    public function get($id)
    {
        $data = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE idalbaran = " . $this->var2str($id) . ";");
        if ($data) {
            return new \albaran_cliente($data[0]);
        }

        return FALSE;
    }

    public function get_by_codigo($cod)
    {
        $data = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE upper(codigo) = " . strtoupper($this->var2str($cod)) . ";");
        if ($data) {
            return new \albaran_cliente($data[0]);
        }

        return FALSE;
    }

    public function exists()
    {
        if (is_null($this->idalbaran)) {
            return FALSE;
        }

        return $this->db->select("SELECT * FROM " . $this->table_name . " WHERE idalbaran = " . $this->var2str($this->idalbaran) . ";");
    }

    /**
     * Genera un nuevo código y número para este albarán
     */
    public function new_codigo()
    {
        $this->numero = fs_documento_new_numero($this->db, $this->table_name, $this->codejercicio, $this->codserie, 'nalbarancli');
        $this->codigo = fs_documento_new_codigo(FS_ALBARAN, $this->codejercicio, $this->codserie, $this->numero);
    }

    /**
     * Comprueba los datos del albarán, devuelve TRUE si son correctos
     * @return boolean
     */
    public function test()
    {
        if ($this->idfactura) {
            $this->ptefactura = FALSE;
        }

        return $this->test_trait();
    }

    /**
     * Comprobaciones extra del albarán, devuelve TRUE si está todo correcto
     * @param boolean $duplicados
     * @return boolean
     */
    public function full_test($duplicados = TRUE)
    {
        $status = $this->full_test_trait(FS_ALBARAN);

        if ($this->total != 0) {
            /// comprobamos las facturas asociadas
            $linea_factura = new \linea_factura_cliente();
            $facturas = $linea_factura->facturas_from_albaran($this->idalbaran);
            if (!empty($facturas)) {
                if (count($facturas) > 1) {
                    $msg = "Este " . FS_ALBARAN . " esta asociado a las siguientes facturas (y no debería):";
                    foreach ($facturas as $f) {
                        $msg .= " <a href='" . $f->url() . "'>" . $f->codigo . "</a>";
                    }
                    $this->new_error_msg($msg);
                    $status = FALSE;
                } else if ($facturas[0]->idfactura != $this->idfactura) {
                    $this->new_error_msg("Este " . FS_ALBARAN . " esta asociado a una <a href='" . $this->factura_url() .
                        "'>factura</a> incorrecta. La correcta es <a href='" . $facturas[0]->url() . "'>esta</a>.");
                    $status = FALSE;
                }
            } else if (isset($this->idfactura)) {
                $this->new_error_msg("Este " . FS_ALBARAN . " esta asociado a una <a href='" . $this->factura_url()
                    . "'>factura</a> que ya no existe.");
                $this->idfactura = NULL;
                $this->save();

                $status = FALSE;
            }
        }

        if ($status && $duplicados) {
            /// comprobamos si es un duplicado
            $data = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE fecha = " . $this->var2str($this->fecha)
                . " AND codcliente = " . $this->var2str($this->codcliente)
                . " AND total = " . $this->var2str($this->total)
                . " AND codagente = " . $this->var2str($this->codagente)
                . " AND numero2 = " . $this->var2str($this->numero2)
                . " AND observaciones = " . $this->var2str($this->observaciones)
                . " AND idalbaran != " . $this->var2str($this->idalbaran) . ";");
            if ($data) {
                foreach ($data as $alb) {
                    /// comprobamos las líneas
                    $aux = $this->db->select("SELECT referencia FROM lineasalbaranescli WHERE
                  idalbaran = " . $this->var2str($this->idalbaran) . "
                  AND referencia NOT IN (SELECT referencia FROM lineasalbaranescli
                  WHERE idalbaran = " . $this->var2str($alb['idalbaran']) . ");");
                    if (!$aux) {
                        $this->new_error_msg("Este " . FS_ALBARAN . " es un posible duplicado de
                     <a href='index.php?page=ventas_albaran&id=" . $alb['idalbaran'] . "'>este otro</a>.
                     Si no lo es, para evitar este mensaje, simplemente modifica las observaciones.");
                        $status = FALSE;
                    }
                }
            }
        }

        return $status;
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
                    . ", fecha = " . $this->var2str($this->fecha)
                    . ", hora = " . $this->var2str($this->hora)
                    . ", idfactura = " . $this->var2str($this->idfactura)
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
                    . ", porcomision = " . $this->var2str($this->porcomision)
                    . ", provincia = " . $this->var2str($this->provincia)
                    . ", tasaconv = " . $this->var2str($this->tasaconv)
                    . ", ptefactura = " . $this->var2str($this->ptefactura)
                    . ", total = " . $this->var2str($this->total)
                    . ", totaleuros = " . $this->var2str($this->totaleuros)
                    . ", totalirpf = " . $this->var2str($this->totalirpf)
                    . ", totaliva = " . $this->var2str($this->totaliva)
                    . ", totalrecargo = " . $this->var2str($this->totalrecargo)
                    . ", femail = " . $this->var2str($this->femail)
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
                    . "  WHERE idalbaran = " . $this->var2str($this->idalbaran) . ";";

                return $this->db->exec($sql);
            }

            $this->new_codigo();
            $sql = "INSERT INTO " . $this->table_name . " (idfactura,codigo,codagente,
               codserie,codejercicio,codcliente,codpago,coddivisa,codalmacen,codpais,coddir,
               codpostal,numero,numero2,nombrecliente,cifnif,direccion,ciudad,provincia,apartado,
               fecha,hora,netosindto,neto,dtopor1,dtopor2,dtopor3,dtopor4,dtopor5,total,totaliva,totaleuros,irpf,totalirpf,porcomision,tasaconv,
               totalrecargo,observaciones,ptefactura,femail,codtrans,codigoenv,nombreenv,apellidosenv,
               apartadoenv,direccionenv,codpostalenv,ciudadenv,provinciaenv,codpaisenv,numdocs) VALUES "
                . "(" . $this->var2str($this->idfactura)
                . "," . $this->var2str($this->codigo)
                . "," . $this->var2str($this->codagente)
                . "," . $this->var2str($this->codserie)
                . "," . $this->var2str($this->codejercicio)
                . "," . $this->var2str($this->codcliente)
                . "," . $this->var2str($this->codpago)
                . "," . $this->var2str($this->coddivisa)
                . "," . $this->var2str($this->codalmacen)
                . "," . $this->var2str($this->codpais)
                . "," . $this->var2str($this->coddir)
                . "," . $this->var2str($this->codpostal)
                . "," . $this->var2str($this->numero)
                . "," . $this->var2str($this->numero2)
                . "," . $this->var2str($this->nombrecliente)
                . "," . $this->var2str($this->cifnif)
                . "," . $this->var2str($this->direccion)
                . "," . $this->var2str($this->ciudad)
                . "," . $this->var2str($this->provincia)
                . "," . $this->var2str($this->apartado)
                . "," . $this->var2str($this->fecha)
                . "," . $this->var2str($this->hora)
                . "," . $this->var2str($this->netosindto)
                . "," . $this->var2str($this->neto)
                . "," . $this->var2str($this->dtopor1)
                . "," . $this->var2str($this->dtopor2)
                . "," . $this->var2str($this->dtopor3)
                . "," . $this->var2str($this->dtopor4)
                . "," . $this->var2str($this->dtopor5)
                . "," . $this->var2str($this->total)
                . "," . $this->var2str($this->totaliva)
                . "," . $this->var2str($this->totaleuros)
                . "," . $this->var2str($this->irpf)
                . "," . $this->var2str($this->totalirpf)
                . "," . $this->var2str($this->porcomision)
                . "," . $this->var2str($this->tasaconv)
                . "," . $this->var2str($this->totalrecargo)
                . "," . $this->var2str($this->observaciones)
                . "," . $this->var2str($this->ptefactura)
                . "," . $this->var2str($this->femail)
                . "," . $this->var2str($this->envio_codtrans)
                . "," . $this->var2str($this->envio_codigo)
                . "," . $this->var2str($this->envio_nombre)
                . "," . $this->var2str($this->envio_apellidos)
                . "," . $this->var2str($this->envio_apartado)
                . "," . $this->var2str($this->envio_direccion)
                . "," . $this->var2str($this->envio_codpostal)
                . "," . $this->var2str($this->envio_ciudad)
                . "," . $this->var2str($this->envio_provincia)
                . "," . $this->var2str($this->envio_codpais)
                . "," . $this->var2str($this->numdocs) . ");";

            if ($this->db->exec($sql)) {
                $this->idalbaran = $this->db->lastval();
                return TRUE;
            }
        }

        return FALSE;
    }

    /**
     * Elimina el albarán de la base de datos.
     * Devuelve FALSE en caso de fallo.
     * @return boolean
     */
    public function delete()
    {
        if ($this->db->exec("DELETE FROM " . $this->table_name . " WHERE idalbaran = " . $this->var2str($this->idalbaran) . ";")) {
            if ($this->idfactura) {
                /**
                 * Delegamos la eliminación de la factura en la clase correspondiente,
                 * que tendrá que hacer más cosas.
                 */
                $factura = new \factura_cliente();
                $factura0 = $factura->get($this->idfactura);
                if ($factura0) {
                    $factura0->delete();
                }
            }

            $this->new_message(ucfirst(FS_ALBARAN) . " de venta " . $this->codigo . " eliminado correctamente.");
            return TRUE;
        }

        return FALSE;
    }

    /**
     * Devuelve un array con los últimos albaranes de venta
     * @param integer $offset
     * @param string $order
     * @return \albaran_cliente
     */
    public function all($offset = 0, $order = 'fecha DESC', $limit = FS_ITEM_LIMIT)
    {
        $sql = "SELECT * FROM " . $this->table_name . " ORDER BY " . $order;

        $data = $this->db->select_limit($sql, $limit, $offset);
        return $this->all_from_data($data);
    }

    /**
     * Devuelve un array con los albaranes pendientes.
     * @param integer $offset
     * @param string $order
     * @return \albaran_cliente
     */
    public function all_ptefactura($offset = 0, $order = 'fecha ASC', $limit = FS_ITEM_LIMIT)
    {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE ptefactura = true ORDER BY " . $order;
        $data = $this->db->select_limit($sql, $limit, $offset);
        return $this->all_from_data($data);
    }

    /**
     * Devuelve un array con los albaranes del cliente $codcliente.
     * @param string $codcliente
     * @param integer $offset
     * @return \albaran_cliente
     */
    public function all_from_cliente($codcliente, $offset = 0)
    {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE codcliente = " . $this->var2str($codcliente)
            . " ORDER BY fecha DESC, codigo DESC";

        $data = $this->db->select_limit($sql, FS_ITEM_LIMIT, $offset);
        return $this->all_from_data($data);
    }

    /**
     * Devuelve un array con los albaranes del agente/empleado
     * @param string $codagente
     * @param integer $offset
     * @return \albaran_cliente
     */
    public function all_from_agente($codagente, $offset = 0)
    {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE codagente = " . $this->var2str($codagente)
            . " ORDER BY fecha DESC, codigo DESC";

        $data = $this->db->select_limit($sql, FS_ITEM_LIMIT, $offset);
        return $this->all_from_data($data);
    }

    /**
     * Devuelve todos los albaranes relacionados con la factura.
     * @param string $id
     * @return \albaran_cliente
     */
    public function all_from_factura($id)
    {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE idfactura = " . $this->var2str($id)
            . " ORDER BY fecha DESC, codigo DESC;";

        $data = $this->db->select($sql);
        return $this->all_from_data($data);
    }

    /**
     * Devuelve un array con los albaranes comprendidos entre $desde y $hasta
     * @param string $desde
     * @param string $hasta
     * @return \albaran_cliente
     */
    public function all_desde($desde, $hasta)
    {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE fecha >= " . $this->var2str($desde)
            . " AND fecha <= " . $this->var2str($hasta) . " ORDER BY codigo ASC;";

        $data = $this->db->select($sql);
        return $this->all_from_data($data);
    }

    /**
     * Devuelve un array con los albaranes que coinciden con $query
     * @param string $query
     * @param integer $offset
     * @return \albaran_cliente
     */
    public function search($query, $offset = 0)
    {
        $query = mb_strtolower($this->no_html($query), 'UTF8');

        $consulta = "SELECT * FROM " . $this->table_name . " WHERE ";
        if (is_numeric($query)) {
            $consulta .= "codigo LIKE '%" . $query . "%' OR numero2 LIKE '%" . $query . "%' OR observaciones LIKE '%" . $query . "%'";
        } else {
            $consulta .= "lower(codigo) LIKE '%" . $query . "%' OR lower(numero2) LIKE '%" . $query . "%' "
                . "OR lower(observaciones) LIKE '%" . str_replace(' ', '%', $query) . "%'";
        }
        $consulta .= " ORDER BY fecha DESC, codigo DESC";

        $data = $this->db->select_limit($consulta, FS_ITEM_LIMIT, $offset);
        return $this->all_from_data($data);
    }

    /**
     * Devuelve un array con los albaranes del cliente $codcliente que coincidan
     * con los filtros.
     * @param string $codcliente
     * @param string $desde
     * @param string $hasta
     * @param string $codserie
     * @param string $obs
     * @param string $coddivisa
     * @return \albaran_cliente
     */
    public function search_from_cliente($codcliente, $desde, $hasta, $codserie = '', $obs = '', $coddivisa = '')
    {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE codcliente = " . $this->var2str($codcliente)
            . " AND ptefactura AND fecha BETWEEN " . $this->var2str($desde) . " AND " . $this->var2str($hasta);

        if ($codserie) {
            $sql .= " AND codserie = " . $this->var2str($codserie);
        }

        if ($obs) {
            $sql .= " AND lower(observaciones) = " . $this->var2str(mb_strtolower($obs, 'UTF8'));
        }

        if ($coddivisa) {
            $sql .= " AND coddivisa = " . $this->var2str($coddivisa);
        }

        $sql .= " ORDER BY fecha ASC, codigo ASC;";

        $data = $this->db->select($sql);
        return $this->all_from_data($data);
    }

    private function all_from_data(&$data)
    {
        $albalist = array();
        if ($data) {
            foreach ($data as $a) {
                $albalist[] = new \albaran_cliente($a);
            }
        }

        return $albalist;
    }

    public function cron_job()
    {
        /**
         * Ponemos a NULL todos los idfactura que no están en facturascli.
         * ¿Por qué? Porque muchos usuarios se dedican a tocar la base de datos.
         */
        $this->db->exec("UPDATE " . $this->table_name . " SET idfactura = NULL WHERE idfactura = 0;");
        $this->db->exec("UPDATE " . $this->table_name . " SET idfactura = NULL WHERE idfactura IS NOT NULL"
            . " AND idfactura NOT IN (SELECT idfactura FROM facturascli WHERE facturascli.idfactura = " . $this->table_name . ".idfactura);");

        /// asignamos netosindto a neto a todos los que estén a 0
        $this->db->exec("UPDATE " . $this->table_name . " SET netosindto = neto WHERE netosindto = 0;");
    }
}
