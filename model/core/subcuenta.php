<?php
/**
 * This file is part of facturacion_base
 * Copyright (C) 2013-2019 Carlos Garcia Gomez <neorazorx@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
namespace FacturaScripts\model;

/**
 * El cuarto nivel de un plan contable. Está relacionada con una única cuenta.
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class subcuenta extends \fs_extended_model
{

    /**
     *
     * @var string
     */
    public $codcuenta;

    /**
     *
     * @var string
     */
    public $coddivisa;

    /**
     *
     * @var string
     */
    public $codejercicio;

    /**
     *
     * @var string
     */
    public $codimpuesto;

    /**
     * Código de la subcuenta.
     * @var string
     */
    public $codsubcuenta;

    /**
     *
     * @var float|int
     */
    public $debe;

    /**
     *
     * @var string
     */
    public $descripcion;

    /**
     *
     * @var float|int
     */
    public $haber;

    /**
     * ID de la cuenta a la que pertenece.
     * @var integer
     */
    public $idcuenta;

    /**
     * Clave primaria.
     * @var integer
     */
    public $idsubcuenta;

    /**
     *
     * @var float|int
     */
    public $iva;

    /**
     *
     * @var float|int
     */
    public $recargo;

    /**
     *
     * @var float|int
     */
    public $saldo;

    /**
     * 
     * @param array|bool $data
     */
    public function __construct($data = FALSE)
    {
        parent::__construct('co_subcuentas', $data);
    }

    /**
     * 
     * @return \subcuenta[]
     */
    public function all()
    {
        return $this->all_from("SELECT * FROM " . $this->table_name() . " ORDER BY idsubcuenta DESC;");
    }

    /**
     * 
     * @param int $idcuenta
     *
     * @return \subcuenta[]
     */
    public function all_from_cuenta($idcuenta)
    {
        $sql = "SELECT * FROM " . $this->table_name() . " WHERE idcuenta = " . $this->var2str($idcuenta)
            . " ORDER BY codsubcuenta ASC;";

        return $this->all_from($sql);
    }

    /**
     * Devuelve las subcuentas del ejercicio $codeje cuya cuenta madre
     * está marcada como cuenta especial $id.
     *
     * @param int    $id
     * @param string $codeje
     *
     * @return \subcuenta
     */
    public function all_from_cuentaesp($id, $codeje)
    {
        $sql = "SELECT * FROM co_subcuentas WHERE idcuenta IN "
            . "(SELECT idcuenta FROM co_cuentas WHERE idcuentaesp = " . $this->var2str($id)
            . " AND codejercicio = " . $this->var2str($codeje) . ") ORDER BY codsubcuenta ASC;";

        return $this->all_from($sql);
    }

    /**
     * Devuelve las subcuentas de un ejercicio:
     * - Todas si $random = false.
     * - $limit si $random = true.
     *
     * @param string $codejercicio
     * @param bool   $random
     * @param int    $limit
     *
     * @return \subcuenta[]
     */
    public function all_from_ejercicio($codejercicio, $random = FALSE, $limit = 0)
    {
        $sublist = [];

        if ($random && $limit) {
            if (strtolower(FS_DB_TYPE) == 'mysql') {
                $sql = "SELECT * FROM " . $this->table_name() . " WHERE codejercicio = "
                    . $this->var2str($codejercicio) . " ORDER BY RAND()";
            } else {
                $sql = "SELECT * FROM " . $this->table_name() . " WHERE codejercicio = "
                    . $this->var2str($codejercicio) . " ORDER BY random()";
            }
            $subcuentas = $this->db->select_limit($sql, $limit, 0);
        } else {
            $sql = "SELECT * FROM " . $this->table_name() . " WHERE codejercicio = "
                . $this->var2str($codejercicio) . " ORDER BY codsubcuenta ASC;";
            $subcuentas = $this->db->select($sql);
        }

        if ($subcuentas) {
            foreach ($subcuentas as $s) {
                $sublist[] = new \subcuenta($s);
            }
        }

        return $sublist;
    }

    public function clean_cache()
    {
        $libro_mayor = 'tmp/' . FS_TMP_NAME . 'libro_mayor/' . $this->idsubcuenta . '.pdf';
        if (file_exists($libro_mayor) && !@unlink($libro_mayor)) {
            $this->new_error_msg('Error al eliminar ' . $libro_mayor);
        }

        $libro_diario = 'tmp/' . FS_TMP_NAME . 'libro_diario/' . $this->codejercicio . '.pdf';
        if (file_exists($libro_diario) && !@unlink($libro_diario)) {
            $this->new_error_msg('Error al eliminar ' . $libro_diario);
        }

        $inventarios = 'tmp/' . FS_TMP_NAME . 'inventarios_balances/' . $this->codejercicio . '.pdf';
        if (file_exists($inventarios) && !@unlink($inventarios)) {
            $this->new_error_msg('Error al eliminar ' . $inventarios);
        }
    }

    public function clear()
    {
        parent::clear();
        $this->coddivisa = $this->default_items->coddivisa();
        $this->descripcion = '';
        $this->debe = 0.0;
        $this->haber = 0.0;
        $this->saldo = 0.0;
        $this->recargo = 0.0;
        $this->iva = 0.0;
    }

    /**
     * 
     * @return int
     */
    public function count_partidas()
    {
        $part = new \partida();
        return $part->count_from_subcuenta($this->idsubcuenta);
    }

    /**
     * 
     * @return bool
     */
    public function delete()
    {
        $this->clean_cache();
        return parent::delete();
    }

    /**
     * 
     * @param string $cod
     * @param string $codejercicio
     * @param bool   $crear
     *
     * @return \subcuenta|boolean
     */
    public function get_by_codigo($cod, $codejercicio, $crear = FALSE)
    {
        $sql = "SELECT * FROM " . $this->table_name() . " WHERE codsubcuenta = " . $this->var2str($cod)
            . " AND codejercicio = " . $this->var2str($codejercicio) . ";";

        $subc = $this->db->select($sql);
        if ($subc) {
            return new \subcuenta($subc[0]);
        } else if ($crear) {
            /// buscamos la subcuenta equivalente en otro ejercicio
            $sql = "SELECT * FROM " . $this->table_name() . " WHERE codsubcuenta = " . $this->var2str($cod)
                . " ORDER BY idsubcuenta DESC;";
            $subc = $this->db->select($sql);
            if ($subc) {
                $old_sc = new \subcuenta($subc[0]);

                /// buscamos la cuenta equivalente es ESTE ejercicio
                $cuenta = new \cuenta();
                $new_c = $cuenta->get_by_codigo($old_sc->codcuenta, $codejercicio);
                if ($new_c) {
                    $new_sc = new \subcuenta();
                    $new_sc->codcuenta = $new_c->codcuenta;
                    $new_sc->coddivisa = $old_sc->coddivisa;
                    $new_sc->codejercicio = $codejercicio;
                    $new_sc->codimpuesto = $old_sc->codimpuesto;
                    $new_sc->codsubcuenta = $old_sc->codsubcuenta;
                    $new_sc->descripcion = $old_sc->descripcion;
                    $new_sc->idcuenta = $new_c->idcuenta;
                    $new_sc->iva = $old_sc->iva;
                    $new_sc->recargo = $old_sc->recargo;
                    if ($new_sc->save()) {
                        return $new_sc;
                    }

                    return FALSE;
                }

                $this->new_error_msg('No se ha encontrado la cuenta equivalente a ' . $old_sc->codcuenta . ' en el ejercicio ' . $codejercicio
                    . ' <a href="index.php?page=contabilidad_ejercicio&cod=' . $codejercicio . '">¿Has importado el plan contable?</a>');
                return FALSE;
            }

            $this->new_error_msg('No se ha encontrado ninguna subcuenta equivalente a ' . $cod . ' para copiar.');
        }

        return FALSE;
    }

    /**
     * 
     * @return \cuenta
     */
    public function get_cuenta()
    {
        $cuenta = new \cuenta();
        return $cuenta->get($this->idcuenta);
    }

    /**
     * Devuelve la primera subcuenta del ejercicio $codeje cuya cuenta madre
     * está marcada como cuenta especial $id.
     * @param int    $id
     * @param string $codeje
     *
     * @return \subcuenta|boolean
     */
    public function get_cuentaesp($id, $codeje)
    {
        $sql = "SELECT * FROM co_subcuentas WHERE idcuenta IN "
            . "(SELECT idcuenta FROM co_cuentas WHERE idcuentaesp = " . $this->var2str($id)
            . " AND codejercicio = " . $this->var2str($codeje) . ") ORDER BY codsubcuenta ASC;";

        $data = $this->db->select($sql);
        if ($data) {
            return new \subcuenta($data[0]);
        }

        return FALSE;
    }

    /**
     * Devuelve la descripción en base64.
     * @return string
     */
    public function get_descripcion_64()
    {
        return base64_encode($this->descripcion);
    }

    /**
     * 
     * @return \ejercicio
     */
    public function get_ejercicio()
    {
        $eje = new \ejercicio();
        return $eje->get($this->codejercicio);
    }

    /**
     * 
     * @param int $offset
     *
     * @return \partida[]
     */
    public function get_partidas($offset = 0)
    {
        $part = new \partida();
        return $part->all_from_subcuenta($this->idsubcuenta, $offset);
    }

    /**
     * 
     * @return \partida[]
     */
    public function get_partidas_full()
    {
        $part = new \partida();
        return $part->full_from_subcuenta($this->idsubcuenta);
    }

    /**
     * 
     * @return array
     */
    public function get_totales()
    {
        $part = new \partida();
        return $part->totales_from_subcuenta($this->idsubcuenta);
    }

    /**
     * 
     * @return string
     */
    protected function install()
    {
        $this->clean_cache();

        /// eliminamos todos los PDFs relacionados
        $paths = [
            'tmp/' . FS_TMP_NAME . 'libro_mayor',
            'tmp/' . FS_TMP_NAME . 'libro_diario',
            'tmp/' . FS_TMP_NAME . 'inventarios_balances'
        ];
        foreach ($paths as $path) {
            if (!file_exists($path)) {
                continue;
            }

            foreach (glob($path . '/*') as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }

        /// forzamos la creación de la tabla de cuentas
        new \cuenta();

        return '';
    }

    /**
     * 
     * @return string
     */
    public function model_class_name()
    {
        return 'subcuenta';
    }

    /**
     * 
     * @return string
     */
    public function primary_column()
    {
        return 'idsubcuenta';
    }

    /**
     * 
     * @return bool
     */
    public function save()
    {
        return $this->test() ? parent::save() : false;
    }

    /**
     * 
     * @param string $query
     *
     * @return \subcuenta[]
     */
    public function search($query)
    {
        $query = mb_strtolower($this->no_html($query), 'UTF8');
        $sql = "SELECT * FROM " . $this->table_name() . " WHERE codsubcuenta LIKE '" . $query . "%'"
            . " OR codsubcuenta LIKE '%" . $query . "'"
            . " OR lower(descripcion) LIKE '%" . $query . "%'"
            . " ORDER BY codejercicio DESC, codcuenta ASC;";

        return $this->all_from($sql);
    }

    /**
     * Devuelve los resultados de la búsuqeda $query sobre las subcuentas del
     * ejercicio $codejercicio
     *
     * @param string $codejercicio
     * @param string $query
     *
     * @return \subcuenta[]
     */
    public function search_by_ejercicio($codejercicio, $query)
    {
        $query = $this->escape_string(mb_strtolower(trim($query), 'UTF8'));

        $sublist = $this->cache->get_array('search_subcuenta_ejercicio_' . $codejercicio . '_' . $query);
        if (count($sublist) < 1) {
            $sql = "SELECT * FROM " . $this->table_name() . " WHERE codejercicio = " . $this->var2str($codejercicio)
                . " AND (codsubcuenta LIKE '" . $query . "%' OR codsubcuenta LIKE '%" . $query . "'"
                . " OR lower(descripcion) LIKE '%" . $query . "%') ORDER BY codcuenta ASC;";

            $sublist = $this->all_from($sql);
            $this->cache->set('search_subcuenta_ejercicio_' . $codejercicio . '_' . $query, $sublist, 300);
        }

        return $sublist;
    }

    /**
     * 
     * @return float
     */
    public function tasaconv()
    {
        if (isset($this->coddivisa)) {
            $divisa = new \divisa();
            $div0 = $divisa->get($this->coddivisa);
            if ($div0) {
                return $div0->tasaconv;
            }
        }

        return 1;
    }

    /**
     * 
     * @return bool
     */
    public function test()
    {
        $this->descripcion = $this->no_html($this->descripcion);

        $limpiar_cache = FALSE;
        $totales = $this->get_totales();

        if (abs($this->debe - $totales['debe']) > .001) {
            $this->debe = $totales['debe'];
            $limpiar_cache = TRUE;
        }

        if (abs($this->haber - $totales['haber']) > .001) {
            $this->haber = $totales['haber'];
            $limpiar_cache = TRUE;
        }

        if (abs($this->saldo - $totales['saldo']) > .001) {
            $this->saldo = $totales['saldo'];
            $limpiar_cache = TRUE;
        }

        if ($limpiar_cache) {
            $this->clean_cache();
        }

        if (strlen($this->codsubcuenta) > 0 && strlen($this->descripcion) > 0) {
            return TRUE;
        }

        $this->new_error_msg('Faltan datos en la subcuenta.');
        return FALSE;
    }

    /**
     * 
     * @return bool
     */
    public function tiene_saldo()
    {
        return !$this->floatcmp($this->debe, $this->haber, FS_NF0, TRUE);
    }

    /**
     * 
     * @param string $type
     *
     * @return string
     */
    public function url($type = 'auto')
    {
        if (is_null($this->idsubcuenta) || $type == 'list') {
            return 'index.php?page=contabilidad_cuentas';
        }

        return 'index.php?page=contabilidad_subcuenta&id=' . $this->idsubcuenta;
    }

    /**
     * 
     * @param string $sql
     *
     * @return \subcuenta[]
     */
    private function all_from($sql)
    {
        $sublist = [];
        $data = $this->db->select($sql);
        if ($data) {
            foreach ($data as $a) {
                $sublist[] = new \subcuenta($a);
            }
        }

        return $sublist;
    }
}
