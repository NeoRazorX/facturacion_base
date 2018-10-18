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
 * Description of factura
 *
 * @author carlos
 */
trait factura
{
    /**
     * Clave primaria.
     * @var integer 
     */
    public $idfactura;

    /**
     * ID de la factura a la que rectifica.
     * @var integer 
     */
    public $idfacturarect;

    /**
     * ID del asiento relacionado, si lo hay.
     * @var integer 
     */
    public $idasiento;

    /**
     * ID del asiento de pago relacionado, si lo hay.
     * @var integer 
     */
    public $idasientop;

    /**
     * Código de la factura a la que rectifica.
     * @var string 
     */
    public $codigorect;

    /**
     *
     * @var boolean
     */
    public $pagada;

    /**
     *
     * @var boolean
     */
    public $anulada;

    public function asiento_url()
    {
        if (is_null($this->idasiento)) {
            return 'index.php?page=contabilidad_asientos';
        }

        return 'index.php?page=contabilidad_asiento&id=' . $this->idasiento;
    }

    public function asiento_pago_url()
    {
        if (is_null($this->idasientop)) {
            return 'index.php?page=contabilidad_asientos';
        }

        return 'index.php?page=contabilidad_asiento&id=' . $this->idasientop;
    }

    public function get_asiento()
    {
        $asiento = new \asiento();
        return $asiento->get($this->idasiento);
    }

    public function get_asiento_pago()
    {
        $asiento = new \asiento();
        return $asiento->get($this->idasientop);
    }

    private function get_lineas_iva_trait($class_name, $due_totales = 1)
    {
        $linea_iva = new $class_name();

        $lineas = $this->get_lineas();
        $lineasi = $linea_iva->all_from_factura($this->idfactura);

        /// si no hay lineas de IVA, las generamos
        if (empty($lineasi) && !empty($lineas)) {
            /// necesitamos los totales por impuesto
            $subtotales = [];
            foreach ($this->get_lineas() as $lin) {
                $codimpuesto = ($lin->codimpuesto === null ) ? 0 : $lin->codimpuesto;
                if (!array_key_exists($codimpuesto, $subtotales)) {
                    $subtotales[$codimpuesto] = array(
                        'neto' => 0,
                        'iva' => 0,
                        'ivapor' => $lin->iva,
                        'recargo' => 0,
                        'recargopor' => $lin->recargo
                    );
                }

                // Acumulamos por tipos de IVAs
                $subtotales[$codimpuesto]['neto'] += $lin->pvptotal * $due_totales;
                $subtotales[$codimpuesto]['iva'] += $lin->pvptotal * $due_totales * ($lin->iva / 100);
                $subtotales[$codimpuesto]['recargo'] += $lin->pvptotal * $due_totales * ($lin->recargo / 100);
            }

            /// redondeamos
            foreach ($subtotales as $codimp => $subt) {
                $subtotales[$codimp]['neto'] = round($subt['neto'], FS_NF0);
                $subtotales[$codimp]['iva'] = round($subt['iva'], FS_NF0);
                $subtotales[$codimp]['recargo'] = round($subt['recargo'], FS_NF0);
            }

            /// ahora creamos las líneas de iva
            foreach ($subtotales as $codimp => $subt) {
                $lineasi[$codimp] = new $class_name();
                $lineasi[$codimp]->idfactura = $this->idfactura;
                $lineasi[$codimp]->codimpuesto = ($codimp === 0) ? null : $codimp;
                $lineasi[$codimp]->iva = $subt['ivapor'];
                $lineasi[$codimp]->recargo = $subt['recargopor'];
                $lineasi[$codimp]->neto = $subt['neto'];
                $lineasi[$codimp]->totaliva = $subt['iva'];
                $lineasi[$codimp]->totalrecargo = $subt['recargo'];
                $lineasi[$codimp]->totallinea = $subt['neto'] + $subt['iva'] + $subt['recargo'];
                $lineasi[$codimp]->save();
            }
        }

        return $lineasi;
    }
}
