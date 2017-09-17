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

require_once __DIR__ . '/../../extras/documento_compra.php';
require_once __DIR__ . '/../../extras/factura.php';

/**
 * Factura de un proveedor.
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class factura_proveedor extends \fs_model
{

    use \documento_compra;
    use \factura;

    public function __construct($data = FALSE)
    {
        parent::__construct('facturasprov');
        if ($data) {
            $this->load_data_trait($data);
            $this->anulada = $this->str2bool($data['anulada']);
            $this->codigorect = $data['codigorect'];
            $this->idasiento = $this->intval($data['idasiento']);
            $this->idasientop = $this->intval($data['idasientop']);
            $this->idfactura = $this->intval($data['idfactura']);
            $this->idfacturarect = $this->intval($data['idfacturarect']);
            $this->pagada = $this->str2bool($data['pagada']);
        } else {
            $this->clear_trait($this->default_items);
            $this->anulada = FALSE;
            $this->codigorect = NULL;
            $this->idasiento = NULL;
            $this->idasientop = NULL;
            $this->idfactura = NULL;
            $this->idfacturarect = NULL;
            $this->pagada = FALSE;
        }
    }

    protected function install()
    {
        new \serie();
        new \asiento();

        return '';
    }

    /**
     * Establece la fecha y la hora, pero respetando el ejercicio y las
     * regularizaciones de IVA.
     * Devuelve TRUE si se asigna una fecha u hora distinta a los solicitados.
     * @param string $fecha
     * @param string $hora
     * @return boolean
     */
    public function set_fecha_hora($fecha, $hora)
    {
        $cambio = FALSE;

        if (is_null($this->numero)) { /// nueva factura
            $this->fecha = $fecha;
            $this->hora = $hora;
        } else if ($fecha != $this->fecha) { /// factura existente y cambiamos fecha
            $cambio = TRUE;

            $eje0 = new \ejercicio();
            $ejercicio = $eje0->get($this->codejercicio);
            if ($ejercicio) {
                /// ¿El ejercicio actual está abierto?
                if ($ejercicio->abierto()) {
                    $eje2 = $eje0->get_by_fecha($fecha);
                    if ($eje2) {
                        if ($eje2->abierto()) {
                            /// ¿La factura está dentro de alguna regularización?
                            $regiva0 = new \regularizacion_iva();
                            if ($regiva0->get_fecha_inside($this->fecha)) {
                                $this->new_error_msg('La factura se encuentra dentro de una regularización de '
                                    . FS_IVA . '. No se puede modificar la fecha.');
                            } else if ($regiva0->get_fecha_inside($fecha)) {
                                $this->new_error_msg('No se puede asignar la fecha ' . $fecha . ' porque ya hay'
                                    . ' una regularización de ' . FS_IVA . ' para ese periodo.');
                            } else {
                                $cambio = FALSE;
                                $this->fecha = $fecha;
                                $this->hora = $hora;

                                /// ¿El ejercicio es distinto?
                                if ($this->codejercicio != $eje2->codejercicio) {
                                    $this->codejercicio = $eje2->codejercicio;
                                    $this->new_codigo();
                                }
                            }
                        } else {
                            $this->new_error_msg('El ejercicio ' . $eje2->nombre . ' está cerrado. No se puede modificar la fecha.');
                        }
                    }
                } else {
                    $this->new_error_msg('El ejercicio ' . $ejercicio->nombre . ' está cerrado. No se puede modificar la fecha.');
                }
            } else {
                $this->new_error_msg('Ejercicio no encontrado.');
            }
        } else if ($hora != $this->hora) { /// factura existente y cambiamos hora
            $this->hora = $hora;
        }

        return $cambio;
    }

    public function url()
    {
        if (is_null($this->idfactura)) {
            return 'index.php?page=compras_facturas';
        }

        return 'index.php?page=compras_factura&id=' . $this->idfactura;
    }

    /**
     * Devuelve las líneas de la factura.
     * @return line_factura_proveedor
     */
    public function get_lineas()
    {
        $linea = new \linea_factura_proveedor();
        return $linea->all_from_factura($this->idfactura);
    }

    /**
     * Devuelve las líneas de IVA de la factura.
     * Si no hay, las crea.
     * @return \linea_iva_factura_proveedor
     */
    public function get_lineas_iva()
    {
        return $this->get_lineas_iva_trait('\linea_iva_factura_proveedor');
    }

    /**
     * Devuelve un array con todas las facturas rectificativas de esta factura.
     * @return \factura_proveedor
     */
    public function get_rectificativas()
    {
        $devoluciones = array();

        $data = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE idfacturarect = " . $this->var2str($this->idfactura) . ";");
        if ($data) {
            foreach ($data as $d) {
                $devoluciones[] = new \factura_proveedor($d);
            }
        }

        return $devoluciones;
    }

    /**
     * Devuelve la factura de compra con el id proporcionado.
     * @param integer $id
     * @return boolean|\factura_proveedor
     */
    public function get($id)
    {
        $data = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE idfactura = " . $this->var2str($id) . ";");
        if ($data) {
            return new \factura_proveedor($data[0]);
        }

        return FALSE;
    }

    public function get_by_codigo($cod)
    {
        $data = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE codigo = " . $this->var2str($cod) . ";");
        if ($data) {
            return new \factura_proveedor($data[0]);
        }

        return FALSE;
    }

    public function exists()
    {
        if (is_null($this->idfactura)) {
            return FALSE;
        }

        return $this->db->select("SELECT * FROM " . $this->table_name . " WHERE idfactura = " . $this->var2str($this->idfactura) . ";");
    }

    /**
     * Genera el número y código de la factura.
     */
    public function new_codigo()
    {
        /// buscamos un hueco o el siguiente número disponible
        $encontrado = FALSE;
        $num = 1;
        $sql = "SELECT " . $this->db->sql_to_int('numero') . " as numero,fecha,hora FROM " . $this->table_name;
        if (FS_NEW_CODIGO != 'NUM' && FS_NEW_CODIGO != '0-NUM') {
            $sql .= " WHERE codejercicio = " . $this->var2str($this->codejercicio)
                . " AND codserie = " . $this->var2str($this->codserie);
        }
        $sql .= " ORDER BY numero ASC;";

        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $d) {
                if (intval($d['numero']) < $num) {
                    /**
                     * El número de la factura es menor que el inicial.
                     * El usuario ha cambiado el número inicial después de hacer
                     * facturas.
                     */
                } else if (intval($d['numero']) == $num) {
                    /// el número es correcto, avanzamos
                    $num++;
                } else {
                    /// Hemos encontrado un hueco
                    $encontrado = TRUE;
                    break;
                }
            }
        }

        $this->numero = $num;

        if (!$encontrado) {
            /// nos guardamos la secuencia para abanq/eneboo
            $sec0 = new \secuencia();
            $sec = $sec0->get_by_params2($this->codejercicio, $this->codserie, 'nfacturaprov');
            if ($sec && $sec->valorout <= $this->numero) {
                $sec->valorout = 1 + $this->numero;
                $sec->save();
            }
        }

        $this->codigo = fs_documento_new_codigo(FS_FACTURA, $this->codejercicio, $this->codserie, $this->numero, 'C');
    }

    /**
     * Comprueba los datos de la factura, devuelve TRUE si está todo correcto
     * @return boolean
     */
    public function test()
    {
        return $this->test_trait();
    }

    public function full_test($duplicados = TRUE)
    {
        $status = $this->full_test_trait(FS_FACTURA);

        /// comprobamos las líneas de IVA
        $this->get_lineas_iva();
        $linea_iva = new \linea_iva_factura_proveedor();
        if (!$linea_iva->factura_test($this->idfactura, $this->neto, $this->totaliva, $this->totalrecargo)) {
            $status = FALSE;
        }

        /// comprobamos la fecha de la factura
        $ejercicio = new \ejercicio();
        $eje0 = $ejercicio->get($this->codejercicio);
        if ($eje0 && (strtotime($this->fecha) < strtotime($eje0->fechainicio) || strtotime($this->fecha) > strtotime($eje0->fechafin))) {
            $status = FALSE;
            $this->new_error_msg("La fecha de esta factura está fuera del rango del"
                . " <a target='_blank' href='" . $eje0->url() . "'>ejercicio</a>.");
        }

        /// comprobamos el asiento
        if (isset($this->idasiento)) {
            $asiento = $this->get_asiento();
            if ($asiento) {
                if ($asiento->tipodocumento != 'Factura de proveedor' || $asiento->documento != $this->codigo) {
                    $this->new_error_msg("Esta factura apunta a un <a href='" . $this->asiento_url() . "'>asiento incorrecto</a>.");
                    $status = FALSE;
                } else if ($this->coddivisa == $this->default_items->coddivisa() && ( abs($asiento->importe) - abs($this->total + $this->totalirpf) >= .02)) {
                    $this->new_error_msg("El importe del asiento es distinto al de la factura.");
                    $status = FALSE;
                } else {
                    $asientop = $this->get_asiento_pago();
                    if ($asientop) {
                        if ($this->totalirpf != 0) {
                            /// excluimos la comprobación si la factura tiene IRPF
                        } else if (!$this->floatcmp($asiento->importe, $asientop->importe)) {
                            $this->new_error_msg('No coinciden los importes de los asientos.');
                            $status = FALSE;
                        }
                    }
                }
            } else {
                $this->new_error_msg("Asiento no encontrado.");
                $status = FALSE;
            }
        }

        if ($status && $duplicados) {
            /// comprobamos si es un duplicado
            $facturas = $this->db->select("SELECT * FROM " . $this->table_name . " WHERE fecha = " . $this->var2str($this->fecha)
                . " AND codproveedor = " . $this->var2str($this->codproveedor)
                . " AND total = " . $this->var2str($this->total)
                . " AND codagente = " . $this->var2str($this->codagente)
                . " AND numproveedor = " . $this->var2str($this->numproveedor)
                . " AND observaciones = " . $this->var2str($this->observaciones)
                . " AND idfactura != " . $this->var2str($this->idfactura) . ";");
            if ($facturas) {
                foreach ($facturas as $fac) {
                    /// comprobamos las líneas
                    $aux = $this->db->select("SELECT referencia FROM lineasfacturasprov WHERE
                  idfactura = " . $this->var2str($this->idfactura) . "
                  AND referencia NOT IN (SELECT referencia FROM lineasfacturasprov
                  WHERE idfactura = " . $this->var2str($fac['idfactura']) . ");");
                    if (!$aux) {
                        $this->new_error_msg("Esta factura es un posible duplicado de
                     <a href='index.php?page=compras_factura&id=" . $fac['idfactura'] . "'>esta otra</a>.
                     Si no lo es, para evitar este mensaje, simplemente modifica las observaciones.");
                        $status = FALSE;
                    }
                }
            }
        }

        return $status;
    }

    public function save()
    {
        if ($this->test()) {
            if ($this->exists()) {
                $sql = "UPDATE " . $this->table_name . " SET codigo = " . $this->var2str($this->codigo)
                    . ", total = " . $this->var2str($this->total)
                    . ", neto = " . $this->var2str($this->neto)
                    . ", cifnif = " . $this->var2str($this->cifnif)
                    . ", pagada = " . $this->var2str($this->pagada)
                    . ", anulada = " . $this->var2str($this->anulada)
                    . ", observaciones = " . $this->var2str($this->observaciones)
                    . ", codagente = " . $this->var2str($this->codagente)
                    . ", codalmacen = " . $this->var2str($this->codalmacen)
                    . ", irpf = " . $this->var2str($this->irpf)
                    . ", totaleuros = " . $this->var2str($this->totaleuros)
                    . ", nombre = " . $this->var2str($this->nombre)
                    . ", codpago = " . $this->var2str($this->codpago)
                    . ", codproveedor = " . $this->var2str($this->codproveedor)
                    . ", idfacturarect = " . $this->var2str($this->idfacturarect)
                    . ", numproveedor = " . $this->var2str($this->numproveedor)
                    . ", codigorect = " . $this->var2str($this->codigorect)
                    . ", codserie = " . $this->var2str($this->codserie)
                    . ", idasiento = " . $this->var2str($this->idasiento)
                    . ", idasientop = " . $this->var2str($this->idasientop)
                    . ", totalirpf = " . $this->var2str($this->totalirpf)
                    . ", totaliva = " . $this->var2str($this->totaliva)
                    . ", coddivisa = " . $this->var2str($this->coddivisa)
                    . ", numero = " . $this->var2str($this->numero)
                    . ", codejercicio = " . $this->var2str($this->codejercicio)
                    . ", tasaconv = " . $this->var2str($this->tasaconv)
                    . ", totalrecargo = " . $this->var2str($this->totalrecargo)
                    . ", fecha = " . $this->var2str($this->fecha)
                    . ", hora = " . $this->var2str($this->hora)
                    . ", numdocs = " . $this->var2str($this->numdocs)
                    . "  WHERE idfactura = " . $this->var2str($this->idfactura) . ";";

                return $this->db->exec($sql);
            }

            $this->new_codigo();
            $sql = "INSERT INTO " . $this->table_name . " (codigo,total,neto,cifnif,pagada,anulada,observaciones,
               codagente,codalmacen,irpf,totaleuros,nombre,codpago,codproveedor,idfacturarect,numproveedor,
               codigorect,codserie,idasiento,idasientop,totalirpf,totaliva,coddivisa,numero,codejercicio,tasaconv,
               totalrecargo,fecha,hora,numdocs) VALUES (" . $this->var2str($this->codigo)
                . "," . $this->var2str($this->total)
                . "," . $this->var2str($this->neto)
                . "," . $this->var2str($this->cifnif)
                . "," . $this->var2str($this->pagada)
                . "," . $this->var2str($this->anulada)
                . "," . $this->var2str($this->observaciones)
                . "," . $this->var2str($this->codagente)
                . "," . $this->var2str($this->codalmacen)
                . "," . $this->var2str($this->irpf)
                . "," . $this->var2str($this->totaleuros)
                . "," . $this->var2str($this->nombre)
                . "," . $this->var2str($this->codpago)
                . "," . $this->var2str($this->codproveedor)
                . "," . $this->var2str($this->idfacturarect)
                . "," . $this->var2str($this->numproveedor)
                . "," . $this->var2str($this->codigorect)
                . "," . $this->var2str($this->codserie)
                . "," . $this->var2str($this->idasiento)
                . "," . $this->var2str($this->idasientop)
                . "," . $this->var2str($this->totalirpf)
                . "," . $this->var2str($this->totaliva)
                . "," . $this->var2str($this->coddivisa)
                . "," . $this->var2str($this->numero)
                . "," . $this->var2str($this->codejercicio)
                . "," . $this->var2str($this->tasaconv)
                . "," . $this->var2str($this->totalrecargo)
                . "," . $this->var2str($this->fecha)
                . "," . $this->var2str($this->hora)
                . "," . $this->var2str($this->numdocs) . ");";

            if ($this->db->exec($sql)) {
                $this->idfactura = $this->db->lastval();
                return TRUE;
            }
        }

        return FALSE;
    }

    /**
     * Elimina la factura de la base de datos.
     * @return boolean
     */
    public function delete()
    {
        $bloquear = FALSE;

        $eje0 = new \ejercicio();
        $ejercicio = $eje0->get($this->codejercicio);
        if ($ejercicio) {
            if ($ejercicio->abierto()) {
                $reg0 = new \regularizacion_iva();
                if ($reg0->get_fecha_inside($this->fecha)) {
                    $this->new_error_msg('La factura se encuentra dentro de una regularización de '
                        . FS_IVA . '. No se puede eliminar.');
                    $bloquear = TRUE;
                } else if (count($this->get_rectificativas()) > 0) {
                    $this->new_error_msg('La factura ya tiene una rectificativa. No se puede eliminar.');
                    $bloquear = TRUE;
                }
            } else {
                $this->new_error_msg('El ejercicio ' . $ejercicio->nombre . ' está cerrado.');
                $bloquear = TRUE;
            }
        }

        /// desvincular albaranes asociados y eliminar factura
        $sql = "UPDATE albaranesprov SET idfactura = NULL, ptefactura = TRUE WHERE idfactura = " . $this->var2str($this->idfactura) . ";"
            . "DELETE FROM " . $this->table_name . " WHERE idfactura = " . $this->var2str($this->idfactura) . ";";

        if ($bloquear) {
            return FALSE;
        } else if ($this->db->exec($sql)) {
            if ($this->idasiento) {
                /**
                 * Delegamos la eliminación del asiento en la clase correspondiente.
                 */
                $asiento = new \asiento();
                $asi0 = $asiento->get($this->idasiento);
                if ($asi0) {
                    $asi0->delete();
                }

                $asi1 = $asiento->get($this->idasientop);
                if ($asi1) {
                    $asi1->delete();
                }
            }

            $this->new_message(ucfirst(FS_FACTURA) . " de compra " . $this->codigo . " eliminada correctamente.");
            return TRUE;
        }

        return FALSE;
    }

    private function all_from($sql, $offset = 0, $limit = FS_ITEM_LIMIT)
    {
        $faclist = array();
        $data = $this->db->select_limit($sql, $limit, $offset);
        if ($data) {
            foreach ($data as $a) {
                $faclist[] = new \factura_proveedor($a);
            }
        }

        return $faclist;
    }

    /**
     * Devuelve un array con las últimas facturas
     * @param integer $offset
     * @param integer $limit
     * @param string $order
     * @return \factura_proveedor
     */
    public function all($offset = 0, $limit = FS_ITEM_LIMIT, $order = 'fecha DESC, codigo DESC')
    {
        $sql = "SELECT * FROM " . $this->table_name . " ORDER BY " . $order;
        return $this->all_from($sql, $offset, $limit);
    }

    /**
     * Devuelve un array con las facturas sin pagar.
     * @param integer $offset
     * @param integer $limit
     * @param string $order
     * @return \factura_proveedor
     */
    public function all_sin_pagar($offset = 0, $limit = FS_ITEM_LIMIT, $order = 'fecha ASC, codigo ASC')
    {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE pagada = false ORDER BY " . $order;
        return $this->all_from($sql, $offset, $limit);
    }

    /**
     * Devuelve un array con las facturas del agente/empleado
     * @param string $codagente
     * @param integer $offset
     * @return \factura_proveedor
     */
    public function all_from_agente($codagente, $offset = 0)
    {
        $sql = "SELECT * FROM " . $this->table_name .
            " WHERE codagente = " . $this->var2str($codagente) .
            " ORDER BY fecha DESC, codigo DESC";

        return $this->all_from($sql, $offset);
    }

    /**
     * Devuelve un array con las facturas del proveedor
     * @param string $codproveedor
     * @param integer $offset
     * @return \factura_proveedor
     */
    public function all_from_proveedor($codproveedor, $offset = 0)
    {
        $sql = "SELECT * FROM " . $this->table_name .
            " WHERE codproveedor = " . $this->var2str($codproveedor) .
            " ORDER BY fecha DESC, codigo DESC";

        return $this->all_from($sql, $offset);
    }

    /**
     * Devuelve un array con las facturas comprendidas entre $desde y $hasta
     * @param string $desde
     * @param string $hasta
     * @param string $codserie código de la serie
     * @param string $codagente código del empleado
     * @param string $codproveedor código del proveedor
     * @param string $estado
     * @param string $codpago código de la forma de pago
     * @param string $codalmacen código del almacén
     * @return \factura_proveedor
     */
    public function all_desde($desde, $hasta, $codserie = FALSE, $codagente = FALSE, $codproveedor = FALSE, $estado = FALSE, $codpago = FALSE, $codalmacen = FALSE)
    {
        $faclist = array();
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
        if ($estado) {
            if ($estado == 'pagada') {
                $sql .= " AND pagada = true";
            } else {
                $sql .= " AND pagada = false";
            }
        }
        if ($codpago) {
            $sql .= " AND codpago = " . $this->var2str($codpago);
        }
        if ($codalmacen) {
            $sql .= " AND codalmacen = " . $this->var2str($codalmacen);
        }
        $sql .= " ORDER BY fecha ASC, codigo ASC;";

        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $f) {
                $faclist[] = new \factura_proveedor($f);
            }
        }

        return $faclist;
    }

    /**
     * Devuelve un array con las facturas coincidentes con $query
     * @param string $query
     * @param integer $offset
     * @return \factura_proveedor
     */
    public function search($query, $offset = 0)
    {
        $query = mb_strtolower($this->no_html($query), 'UTF8');

        $sql = "SELECT * FROM " . $this->table_name . " WHERE ";
        if (is_numeric($query)) {
            $sql .= "codigo LIKE '%" . $query . "%' OR numproveedor LIKE '%" . $query
                . "%' OR observaciones LIKE '%" . $query . "%'";
        } else {
            $sql .= "lower(codigo) LIKE '%" . $query . "%' OR lower(numproveedor) LIKE '%" . $query . "%' "
                . "OR lower(observaciones) LIKE '%" . str_replace(' ', '%', $query) . "%'";
        }
        $sql .= " ORDER BY fecha DESC, codigo DESC";

        return $this->all_from($sql, $offset);
    }

    public function cron_job()
    {
        
    }
}
