<?php
/*
 * This file is part of presupuestos_y_pedidos
 * Copyright (C) 2014-2017  Carlos Garcia Gomez      neorazorx@gmail.com
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

/**
 * Línea de presupuesto de cliente.
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class linea_presupuesto_cliente extends \fs_model
{

    /**
     * Clave primaria.
     * @var integer
     */
    public $idlinea;

    /**
     * ID del presupuesto.
     * @var integer
     */
    public $idpresupuesto;

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
     * Listado de presupuestos.
     * @var array
     */
    private static $presupuestos;

    public function __construct($data = FALSE)
    {
        parent::__construct('lineaspresupuestoscli');

        if (!isset(self::$presupuestos)) {
            self::$presupuestos = array();
        }

        if ($data) {
            $this->idlinea = intval($data['idlinea']);
            $this->idpresupuesto = intval($data['idpresupuesto']);
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
            $this->idpresupuesto = NULL;
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

    public function pvp_iva()
    {
        return $this->pvpunitario * (100 + $this->iva) / 100;
    }

    public function total_iva()
    {
        return $this->pvptotal * (100 + $this->iva - $this->irpf + $this->recargo) / 100;
    }

    public function descripcion()
    {
        return nl2br($this->descripcion);
    }

    public function show_codigo()
    {
        $codigo = 'desconocido';

        $encontrado = FALSE;
        foreach (self::$presupuestos as $p) {
            if ($p->idpresupuesto == $this->idpresupuesto) {
                $codigo = $p->codigo;
                $encontrado = TRUE;
                break;
            }
        }

        if (!$encontrado) {
            $pre = new \presupuesto_cliente();
            self::$presupuestos[] = $pre->get($this->idpresupuesto);
            $codigo = self::$presupuestos[count(self::$presupuestos) - 1]->codigo;
        }

        return $codigo;
    }

    public function show_fecha()
    {
        $fecha = 'desconocida';

        $encontrado = FALSE;
        foreach (self::$presupuestos as $p) {
            if ($p->idpresupuesto == $this->idpresupuesto) {
                $fecha = $p->fecha;
                $encontrado = TRUE;
                break;
            }
        }

        if (!$encontrado) {
            $pre = new \presupuesto_cliente();
            self::$presupuestos[] = $pre->get($this->idpresupuesto);
            $fecha = self::$presupuestos[count(self::$presupuestos) - 1]->fecha;
        }

        return $fecha;
    }

    public function show_nombrecliente()
    {
        $nombre = 'desconocido';

        $encontrado = FALSE;
        foreach (self::$presupuestos as $p) {
            if ($p->idpresupuesto == $this->idpresupuesto) {
                $nombre = $p->nombrecliente;
                $encontrado = TRUE;
                break;
            }
        }

        if (!$encontrado) {
            $pre = new \presupuesto_cliente();
            self::$presupuestos[] = $pre->get($this->idpresupuesto);
            $nombre = self::$presupuestos[count(self::$presupuestos) - 1]->nombrecliente;
        }

        return $nombre;
    }

    public function url()
    {
        return 'index.php?page=ventas_presupuesto&id=' . $this->idpresupuesto;
    }

    public function articulo_url()
    {
        if (is_null($this->referencia) OR $this->referencia == '') {
            return "index.php?page=ventas_articulos";
        }

        return "index.php?page=ventas_articulo&ref=" . urlencode($this->referencia);
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
            $this->new_error_msg("Error en el valor de pvptotal de la línea " . $this->referencia . " del " . FS_PRESUPUESTO . ". Valor correcto: " . $total . " y se recibe " . $this->pvptotal);
            return FALSE;
        } else if (!$this->floatcmp($this->pvpsindto, $totalsindto, FS_NF0, TRUE)) {
            $this->new_error_msg("Error en el valor de pvpsindto de la línea " . $this->referencia . " del " . FS_PRESUPUESTO . ". Valor correcto: " . $totalsindto . " y se recibe " . $this->pvpsindto);
            return FALSE;
        }

        return TRUE;
    }

    public function save()
    {
        if ($this->test()) {
            if ($this->exists()) {
                $sql = "UPDATE " . $this->table_name . " SET cantidad = " . $this->var2str($this->cantidad)
                    . ", codimpuesto = " . $this->var2str($this->codimpuesto)
                    . ", descripcion = " . $this->var2str($this->descripcion)
                    . ", dtopor = " . $this->var2str($this->dtopor)
                    . ", dtopor2 = " . $this->var2str($this->dtopor2)
                    . ", dtopor3 = " . $this->var2str($this->dtopor3)
                    . ", dtopor4 = " . $this->var2str($this->dtopor4)
                    . ", idpresupuesto = " . $this->var2str($this->idpresupuesto)
                    . ", irpf = " . $this->var2str($this->irpf)
                    . ", iva = " . $this->var2str($this->iva)
                    . ", pvpsindto = " . $this->var2str($this->pvpsindto)
                    . ", pvptotal = " . $this->var2str($this->pvptotal)
                    . ", pvpunitario = " . $this->var2str($this->pvpunitario)
                    . ", recargo = " . $this->var2str($this->recargo)
                    . ", referencia = " . $this->var2str($this->referencia)
                    . ", codcombinacion = " . $this->var2str($this->codcombinacion)
                    . ", orden = " . $this->var2str($this->orden)
                    . ", mostrar_cantidad = " . $this->var2str($this->mostrar_cantidad)
                    . ", mostrar_precio = " . $this->var2str($this->mostrar_precio)
                    . "  WHERE idlinea = " . $this->var2str($this->idlinea) . ";";

                return $this->db->exec($sql);
            }

            $sql = "INSERT INTO " . $this->table_name . " (cantidad,codimpuesto,descripcion,dtopor,dtopor2,dtopor3,dtopor4,
               idpresupuesto,irpf,iva,pvpsindto,pvptotal,pvpunitario,recargo,referencia,codcombinacion,
               orden,mostrar_cantidad,mostrar_precio) VALUES 
                      (" . $this->var2str($this->cantidad)
                . "," . $this->var2str($this->codimpuesto)
                . "," . $this->var2str($this->descripcion)
                . "," . $this->var2str($this->dtopor)
                . "," . $this->var2str($this->dtopor2)
                . "," . $this->var2str($this->dtopor3)
                . "," . $this->var2str($this->dtopor4)
                . "," . $this->var2str($this->idpresupuesto)
                . "," . $this->var2str($this->irpf)
                . "," . $this->var2str($this->iva)
                . "," . $this->var2str($this->pvpsindto)
                . "," . $this->var2str($this->pvptotal)
                . "," . $this->var2str($this->pvpunitario)
                . "," . $this->var2str($this->recargo)
                . "," . $this->var2str($this->referencia)
                . "," . $this->var2str($this->codcombinacion)
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
     * Devuelve todas las líneas del presupuesto $idp
     * @param integer $idp
     * @return \linea_presupuesto_cliente
     */
    public function all_from_presupuesto($idp)
    {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE idpresupuesto = " . $this->var2str($idp)
            . " ORDER BY orden DESC, idlinea ASC;";

        $data = $this->db->select($sql);
        return $this->all_from_data($data);
    }

    /**
     * Devuelve todas las líneas que hagan referencia al artículo $ref
     * @param string $ref
     * @param integer $offset
     * @param integer $limit
     * @return \linea_presupuesto_cliente
     */
    public function all_from_articulo($ref, $offset = 0, $limit = FS_ITEM_LIMIT)
    {
        $sql = "SELECT * FROM " . $this->table_name . " WHERE referencia = " . $this->var2str($ref)
            . " ORDER BY idpresupuesto DESC";

        $data = $this->db->select_limit($sql, $limit, $offset);
        return $this->all_from_data($data);
    }

    /**
     * Busca todas las coincidencias de $query en las líneas.
     * @param string $query
     * @param integer $offset
     * @return \linea_presupuesto_cliente
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
        $sql .= " ORDER BY idpresupuesto DESC, idlinea ASC";

        $data = $this->db->select_limit($sql, FS_ITEM_LIMIT, $offset);
        return $this->all_from_data($data);
    }

    /**
     * Busca todas las coincidencias de $query en las líneas del cliente $codcliente
     * @param string $codcliente
     * @param string $ref
     * @param string $obs
     * @param integer $offset
     * @return \linea_presupuesto_cliente
     */
    public function search_from_cliente2($codcliente, $ref = '', $obs = '', $offset = 0)
    {
        $ref = mb_strtolower($this->no_html($ref), 'UTF8');
        $obs = mb_strtolower($this->no_html($obs), 'UTF8');

        $sql = "SELECT * FROM " . $this->table_name . " WHERE idpresupuesto IN
         (SELECT idpresupuesto FROM presupuestoscli WHERE codcliente = " . $this->var2str($codcliente) . "
         AND lower(observaciones) LIKE '" . $obs . "%') AND ";
        if (is_numeric($ref)) {
            $sql .= "(referencia LIKE '%" . $ref . "%' OR descripcion LIKE '%" . $ref . "%')";
        } else {
            $buscar = str_replace(' ', '%', $ref);
            $sql .= "(lower(referencia) LIKE '%" . $buscar . "%' OR lower(descripcion) LIKE '%" . $buscar . "%')";
        }
        $sql .= " ORDER BY idpresupuesto DESC, idlinea ASC";

        $data = $this->db->select_limit($sql, FS_ITEM_LIMIT, $offset);
        return $this->all_from_data($data);
    }

    private function all_from_data(&$data)
    {
        $linealist = array();
        if ($data) {
            foreach ($data as $l) {
                $linealist[] = new \linea_presupuesto_cliente($l);
            }
        }

        return $linealist;
    }
}
