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

/**
 * Description of linea_documento_compra
 *
 * @author carlos
 */
trait linea_documento_compra
{

    /**
     * Clave primaria.
     * @var integer 
     */
    public $idlinea;

    /**
     *
     * @var float 
     */
    public $cantidad;

    /**
     * Código del impuesto relacionado.
     * @var string 
     */
    public $codimpuesto;

    /**
     *
     * @var string
     */
    public $descripcion;

    /**
     * % de descuento.
     * @var float 
     */
    public $dtopor;

    /**
     * % de retención IRPF
     * @var float 
     */
    public $irpf;

    /**
     * % del impuesto relacionado.
     * @var float 
     */
    public $iva;

    /**
     * Importe neto sin descuento, es decir, pvpunitario * cantidad.
     * @var float 
     */
    public $pvpsindto;

    /**
     * Importe neto de la linea, sin impuestos.
     * @var float 
     */
    public $pvptotal;

    /**
     * Precio de un unidad.
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

    private function load_data_trait($data)
    {
        $this->cantidad = floatval($data['cantidad']);
        $this->codimpuesto = $data['codimpuesto'];
        $this->descripcion = $data['descripcion'];
        $this->dtopor = floatval($data['dtopor']);
        $this->idlinea = $this->intval($data['idlinea']);
        $this->irpf = floatval($data['irpf']);
        $this->iva = floatval($data['iva']);
        $this->pvpsindto = floatval($data['pvpsindto']);
        $this->pvptotal = floatval($data['pvptotal']);
        $this->pvpunitario = floatval($data['pvpunitario']);
        $this->recargo = floatval($data['recargo']);
        $this->referencia = $data['referencia'];
        $this->codcombinacion = $data['codcombinacion'];
    }

    private function clear_trait()
    {
        $this->cantidad = 0.0;
        $this->codimpuesto = NULL;
        $this->descripcion = '';
        $this->dtopor = 0.0;
        $this->idlinea = NULL;
        $this->irpf = 0.0;
        $this->iva = 0.0;
        $this->pvpsindto = 0.0;
        $this->pvptotal = 0.0;
        $this->pvpunitario = 0.0;
        $this->recargo = 0.0;
        $this->referencia = NULL;
        $this->codcombinacion = NULL;
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

    public function articulo_url()
    {
        if (is_null($this->referencia) OR $this->referencia == '') {
            return "index.php?page=ventas_articulos";
        }

        return "index.php?page=ventas_articulo&ref=" . urlencode($this->referencia);
    }

    public function test()
    {
        $this->descripcion = $this->no_html($this->descripcion);
        $total = $this->pvpunitario * $this->cantidad * (100 - $this->dtopor) / 100;
        $totalsindto = $this->pvpunitario * $this->cantidad;

        if (!$this->floatcmp($this->pvptotal, $total, FS_NF0, TRUE)) {
            $this->new_error_msg("Error en el valor de pvptotal de la línea " . $this->referencia . " del documento. Valor correcto: " . $total);
            return FALSE;
        } else if (!$this->floatcmp($this->pvpsindto, $totalsindto, FS_NF0, TRUE)) {
            $this->new_error_msg("Error en el valor de pvpsindto de la línea " . $this->referencia . " del documento. Valor correcto: " . $totalsindto);
            return FALSE;
        }

        return TRUE;
    }
    
    abstract protected function new_error_msg($msg);

    abstract public function floatcmp($f1, $f2, $precision = 10, $round = FALSE);
}
