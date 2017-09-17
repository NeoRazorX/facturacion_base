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
 * Description of documento_compra
 *
 * @author carlos
 */
trait documento_compra
{

    /**
     *
     * @var string
     */
    public $cifnif;

    /**
     * Empleado que ha creado la factura.
     * Modelo agente.
     * @var string 
     */
    public $codagente;

    /**
     * Almacén en el que entra la mercancía.
     * @var string 
     */
    public $codalmacen;

    /**
     * Divisa de la factura.
     * @var string 
     */
    public $coddivisa;

    /**
     * Ejercicio relacionado. El que corresponde a la fecha.
     * @var string 
     */
    public $codejercicio;

    /**
     * Código único de la factura. Para humanos.
     * @var string 
     */
    public $codigo;

    /**
     * Forma d epago usada.
     * @var string 
     */
    public $codpago;

    /**
     * Proveedor de la factura.
     * @var string 
     */
    public $codproveedor;

    /**
     * Serie de la factura.
     * @var string 
     */
    public $codserie;
    public $fecha;
    public $hora;

    /**
     * % de retención IRPF de la factura.
     * Cada línea puede tener uno distinto.
     * @var double
     */
    public $irpf;

    /**
     * Suma total antes de impuestos.
     * @var double
     */
    public $neto;

    /**
     * Nombre del proveedor.
     * @var string 
     */
    public $nombre;

    /**
     * Número de la factura.
     * Único dentro de serie+ejercicio.
     * @var string 
     */
    public $numero;

    /**
     * Número de factura del proveedor, si lo hay.
     * @var string 
     */
    public $numproveedor;
    public $observaciones;

    /**
     * Tasa de conversión a Euros de la divisa de la factura.
     * @var double
     */
    public $tasaconv;

    /**
     * Importe total de la factura, con impuestos.
     * @var double
     */
    public $total;

    /**
     * Total expresado en euros, por si no fuese la divisa de la factura.
     * totaleuros = total/tasaconv
     * No hace falta rellenarlo, al hacer save() se calcula el valor.
     * @var double
     */
    public $totaleuros;

    /**
     * Suma total de retenciones IRPF de las líneas.
     * @var double
     */
    public $totalirpf;

    /**
     * Suma total del IVA de las líneas.
     * @var double
     */
    public $totaliva;

    /**
     * Suma del recargo de equivalencia de las líneas.
     * @var double
     */
    public $totalrecargo;

    /**
     * Número de documentos adjuntos.
     * @var integer 
     */
    public $numdocs;

    private function load_data_trait($data)
    {
        $this->cifnif = $data['cifnif'];
        $this->codagente = $data['codagente'];
        $this->codalmacen = $data['codalmacen'];
        $this->coddivisa = $data['coddivisa'];
        $this->codejercicio = $data['codejercicio'];
        $this->codigo = $data['codigo'];
        $this->codpago = $data['codpago'];
        $this->codproveedor = $data['codproveedor'];
        $this->codserie = $data['codserie'];
        $this->fecha = Date('d-m-Y', strtotime($data['fecha']));

        $this->hora = '00:00:00';
        if (!is_null($data['hora'])) {
            $this->hora = date('H:i:s', strtotime($data['hora']));
        }

        $this->irpf = floatval($data['irpf']);
        $this->neto = floatval($data['neto']);
        $this->nombre = $data['nombre'];
        $this->numero = $data['numero'];
        $this->numproveedor = $data['numproveedor'];
        $this->observaciones = $this->no_html($data['observaciones']);
        $this->tasaconv = floatval($data['tasaconv']);
        $this->total = floatval($data['total']);
        $this->totaleuros = floatval($data['totaleuros']);
        $this->totalirpf = floatval($data['totalirpf']);
        $this->totaliva = floatval($data['totaliva']);
        $this->totalrecargo = floatval($data['totalrecargo']);
        $this->numdocs = intval($data['numdocs']);
    }

    /**
     * 
     * @param fs_default_items $default_items
     */
    private function clear_trait($default_items)
    {
        $this->cifnif = '';
        $this->codagente = NULL;
        $this->codalmacen = $default_items->codalmacen();
        $this->coddivisa = NULL;
        $this->codejercicio = NULL;
        $this->codigo = NULL;
        $this->codpago = $default_items->codpago();
        $this->codproveedor = NULL;
        $this->codserie = $default_items->codserie();
        $this->fecha = Date('d-m-Y');
        $this->hora = Date('H:i:s');
        $this->irpf = 0.0;
        $this->neto = 0.0;
        $this->nombre = '';
        $this->numero = NULL;
        $this->numproveedor = NULL;
        $this->observaciones = NULL;
        $this->tasaconv = 1.0;
        $this->total = 0.0;
        $this->totaleuros = 0.0;
        $this->totalirpf = 0.0;
        $this->totaliva = 0.0;
        $this->totalrecargo = 0.0;
        $this->numdocs = 0;
    }

    public function show_hora($segundos = TRUE)
    {
        if ($segundos) {
            return Date('H:i:s', strtotime($this->hora));
        }

        return Date('H:i', strtotime($this->hora));
    }

    public function observaciones_resume()
    {
        if ($this->observaciones == '') {
            return '-';
        } else if (strlen($this->observaciones) < 60) {
            return $this->observaciones;
        }

        return substr($this->observaciones, 0, 50) . '...';
    }

    public function agente_url()
    {
        if (is_null($this->codagente)) {
            return "index.php?page=admin_agentes";
        }

        return "index.php?page=admin_agente&cod=" . $this->codagente;
    }

    public function proveedor_url()
    {
        if (is_null($this->codproveedor)) {
            return "index.php?page=compras_proveedores";
        }

        return "index.php?page=compras_proveedor&cod=" . $this->codproveedor;
    }
    
    public function test_trait()
    {
        $this->nombre = $this->no_html($this->nombre);
        if ($this->nombre == '') {
            $this->nombre = '-';
        }

        $this->numproveedor = $this->no_html($this->numproveedor);
        $this->observaciones = $this->no_html($this->observaciones);

        /**
         * Usamos el euro como divisa puente a la hora de sumar, comparar
         * o convertir cantidades en varias divisas. Por este motivo necesimos
         * muchos decimales.
         */
        $this->totaleuros = round($this->total / $this->tasaconv, 5);

        if ($this->floatcmp($this->total, $this->neto + $this->totaliva - $this->totalirpf + $this->totalrecargo, FS_NF0, TRUE)) {
            return TRUE;
        }

        $this->new_error_msg("Error grave: El total está mal calculado. ¡Informa del error!");
        return FALSE;
    }
    
    public function full_test_trait($tipo_doc)
    {
        $status = TRUE;
        $subtotales = [];
        $irpf = 0;
        
        /// calculamos también con el método anterior
        $neto_alt = 0;
        $iva_alt = 0;

        foreach ($this->get_lineas() as $lin) {
            if (!$lin->test()) {
                $status = FALSE;
            }

            $codimpuesto = ($lin->codimpuesto === null ) ? 0 : $lin->codimpuesto;
            if (!array_key_exists($codimpuesto, $subtotales)) {
                $subtotales[$codimpuesto] = array(
                    'neto' => 0,
                    'iva' => 0, // Total IVA
                    'recargo' => 0, // Total Recargo
                );
            }

            /// Acumulamos por tipos de IVAs
            $subtotales[$codimpuesto]['neto'] += $lin->pvptotal;
            $subtotales[$codimpuesto]['iva'] += $lin->pvptotal * $lin->iva / 100;
            $subtotales[$codimpuesto]['recargo'] += $lin->pvptotal * $lin->recargo / 100;
            $irpf += $lin->pvptotal * $lin->irpf / 100;
            
            /// Cálculo anterior
            $neto_alt += $lin->pvptotal;
            $iva_alt += $lin->pvptotal * $lin->iva / 100;
        }

        /// redondeamos y sumamos
        $neto = 0;
        $iva = 0;
        $recargo = 0;
        $irpf = round($irpf, FS_NF0);
        foreach ($subtotales as $subt) {
            $neto += round($subt['neto'], FS_NF0);
            $iva += round($subt['iva'], FS_NF0);
            $recargo += round($subt['recargo'], FS_NF0);
        }
        $neto_alt = round($neto_alt, FS_NF0);
        $iva_alt = round($iva_alt, FS_NF0);

        $total = $neto + $iva - $irpf + $recargo;
        $total_alt = $neto_alt + $iva_alt - $irpf + $recargo;

        if (!$this->floatcmp($this->neto, $neto, FS_NF0, TRUE) && !$this->floatcmp($this->neto, $neto_alt, FS_NF0, TRUE)) {
            $this->new_error_msg("Valor neto de " . $tipo_doc . " " . $this->codigo . " incorrecto (" . $this->neto . "). Valor correcto: " . $neto);
            $status = FALSE;
        }
        
        if (!$this->floatcmp($this->totaliva, $iva, FS_NF0, TRUE) && !$this->floatcmp($this->totaliva, $iva_alt, FS_NF0, TRUE)) {
            $this->new_error_msg("Valor totaliva de " . $tipo_doc . " " . $this->codigo . " incorrecto (" . $this->totaliva . "). Valor correcto: " . $iva);
            $status = FALSE;
        }
        
        if (!$this->floatcmp($this->totalirpf, $irpf, FS_NF0, TRUE)) {
            $this->new_error_msg("Valor totalirpf de " . $tipo_doc . " " . $this->codigo . " incorrecto (" . $this->totalirpf . "). Valor correcto: " . $irpf);
            $status = FALSE;
        }
        
        if (!$this->floatcmp($this->totalrecargo, $recargo, FS_NF0, TRUE)) {
            $this->new_error_msg("Valor totalrecargo de " . $tipo_doc . " " . $this->codigo . " incorrecto (" . $this->totalrecargo . "). Valor correcto: " . $recargo);
            $status = FALSE;
        }
        
        if (!$this->floatcmp($this->total, $total, FS_NF0, TRUE) && !$this->floatcmp($this->total, $total_alt, FS_NF0, TRUE)) {
            $this->new_error_msg("Valor total de " . $tipo_doc . " " . $this->codigo . " incorrecto (" . $this->total . "). Valor correcto: " . $total);
            $status = FALSE;
        }

        return $status;
    }

    abstract protected function new_error_msg($msg);

    abstract public function floatcmp($f1, $f2, $precision = 10, $round = FALSE);

    abstract public function get_lineas();
}
