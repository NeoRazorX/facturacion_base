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
 * Description of documento_venta
 *
 * @author carlos
 */
trait documento_venta
{

    /**
     * Código único de la factura. Para humanos.
     * @var string
     */
    public $codigo;

    /**
     * Número de la factura.
     * Único dentro de la serie+ejercicio.
     * @var string
     */
    public $numero;

    /**
     * Número opcional a disposición del usuario.
     * @var string
     */
    public $numero2;

    /**
     * Ejercicio relacionado. El que corresponde a la fecha.
     * @var string
     */
    public $codejercicio;

    /**
     * Serie relacionada.
     * @var string
     */
    public $codserie;

    /**
     * Almacén del que sale la mercancía.
     * @var string
     */
    public $codalmacen;

    /**
     * Forma de pago.
     * @var string
     */
    public $codpago;

    /**
     * Divisa de la factura.
     * @var string
     */
    public $coddivisa;

    /**
     * TODO
     * @var
     */
    public $fecha;

    /**
     * TODO
     * @var
     */
    public $hora;

    /**
     * Código identificador del cliente de la factura.
     * @var string
     */
    public $codcliente;

    /**
     * TODO
     * @var
     */
    public $nombrecliente;

    /**
     * TODO
     * @var
     */
    public $cifnif;

    /**
     * TODO
     * @var
     */
    public $direccion;

    /**
     * TODO
     * @var
     */
    public $ciudad;

    /**
     * TODO
     * @var
     */
    public $provincia;

    /**
     * TODO
     * @var
     */
    public $apartado;

    /**
     * TODO
     * @var string
     */
    public $envio_codtrans;

    /**
     * TODO
     * @var
     */
    public $envio_codigo;

    /**
     * TODO
     * @var
     */
    public $envio_nombre;

    /**
     * TODO
     * @var
     */
    public $envio_apellidos;

    /**
     * TODO
     * @var
     */
    public $envio_apartado;

    /**
     * TODO
     * @var
     */
    public $envio_direccion;

    /**
     * TODO
     * @var
     */
    public $envio_codpostal;

    /**
     * TODO
     * @var
     */
    public $envio_ciudad;

    /**
     * TODO
     * @var
     */
    public $envio_provincia;

    /**
     * TODO
     * @var
     */
    public $envio_codpais;

    /**
     * ID de la dirección en dirclientes.
     * Modelo direccion_cliente.
     * @var string
     */
    public $coddir;

    /**
     * TODO
     * @var
     */
    public $codpostal;

    /**
     * TODO
     * @var
     */
    public $codpais;

    /**
     * Empleado que ha creado la factura.
     * Modelo agente.
     * @var string
     */
    public $codagente;

    /**
     * Importe total antes de descuentos e impuestos.
     * Es la suma del pvptotal de las líneas.
     * @var float
     */
    public $netosindto;

    /**
     * Importe total antes de impuestos.
     * Es la suma del pvptotal de las líneas.
     * @var float
     */
    public $neto;

    /**
     * Descuento porcentual 1
     * @var float
     */
    public $dtopor1;

    /**
     * Descuento porcentual 2
     * @var float
     */
    public $dtopor2;

    /**
     * Descuento porcentual 3
     * @var float
     */
    public $dtopor3;

    /**
     * Descuento porcentual 4
     * @var float
     */
    public $dtopor4;

    /**
     * Descuento porcentual 5
     * @var float
     */
    public $dtopor5;

    /**
     * Suma total del IVA de las líneas.
     * @var float
     */
    public $totaliva;

    /**
     * Suma total de la factura, con impuestos.
     * @var float
     */
    public $total;

    /**
     * Total expresado en euros, por si no fuese la divisa de la factura.
     * totaleuros = total/tasaconv
     * No hace falta rellenarlo, al hacer save() se calcula el valor.
     * @var float
     */
    public $totaleuros;

    /**
     * % de retención IRPF de la factura. Se obtiene de la serie.
     * Cada línea puede tener un % distinto.
     * @var float
     */
    public $irpf;

    /**
     * Suma total de las retenciones IRPF de las líneas de la factura.
     * @var float
     */
    public $totalirpf;

    /**
     * % de comisión del empleado (agente).
     * @var float
     */
    public $porcomision;

    /**
     * Tasa de conversión a Euros de la divisa de la factura.
     * @var float
     */
    public $tasaconv;

    /**
     * Suma total del recargo de equivalencia de las líneas.
     * @var float
     */
    public $totalrecargo;

    /**
     * Observacioens del la factura
     * @var float
     */
    public $observaciones;

    /**
     * Fecha en la que se envió la factura por email.
     * @var string 
     */
    public $femail;

    /**
     * Número de documentos adjuntos.
     * @var integer 
     */
    public $numdocs;

    private function load_data_trait($data)
    {
        $this->codigo = $data['codigo'];
        $this->codagente = $data['codagente'];
        $this->codpago = $data['codpago'];
        $this->codserie = $data['codserie'];
        $this->codejercicio = $data['codejercicio'];
        $this->codcliente = $data['codcliente'];
        $this->coddivisa = $data['coddivisa'];
        $this->codalmacen = $data['codalmacen'];
        $this->codpais = $data['codpais'];
        $this->coddir = $data['coddir'];
        $this->codpostal = $data['codpostal'];
        $this->numero = $data['numero'];
        $this->numero2 = $data['numero2'];
        $this->nombrecliente = $data['nombrecliente'];
        $this->cifnif = $data['cifnif'];
        $this->direccion = $data['direccion'];
        $this->ciudad = $data['ciudad'];
        $this->provincia = $data['provincia'];
        $this->apartado = $data['apartado'];
        $this->fecha = Date('d-m-Y', strtotime($data['fecha']));

        $this->hora = '00:00:00';
        if (!is_null($data['hora'])) {
            $this->hora = date('H:i:s', strtotime($data['hora']));
        }

        $this->netosindto = isset($data['netosindto']) ? floatval($data['netosindto']) : 0.0;
        $this->neto = floatval($data['neto']);

        if ($this->neto > $this->netosindto) {
            $this->netosindto = $this->neto;
        }

        $this->dtopor1 = isset($data['dtopor1']) ? floatval($data['dtopor1']) : 0.0;
        $this->dtopor2 = isset($data['dtopor2']) ? floatval($data['dtopor2']) : 0.0;
        $this->dtopor3 = isset($data['dtopor3']) ? floatval($data['dtopor3']) : 0.0;
        $this->dtopor4 = isset($data['dtopor4']) ? floatval($data['dtopor4']) : 0.0;
        $this->dtopor5 = isset($data['dtopor5']) ? floatval($data['dtopor5']) : 0.0;
        $this->total = floatval($data['total']);
        $this->totaliva = floatval($data['totaliva']);
        $this->totaleuros = floatval($data['totaleuros']);
        $this->irpf = floatval($data['irpf']);
        $this->totalirpf = floatval($data['totalirpf']);
        $this->porcomision = floatval($data['porcomision']);
        $this->tasaconv = floatval($data['tasaconv']);
        $this->totalrecargo = floatval($data['totalrecargo']);
        $this->observaciones = $data['observaciones'];

        $this->femail = NULL;
        if (!is_null($data['femail'])) {
            $this->femail = Date('d-m-Y', strtotime($data['femail']));
        }

        $this->envio_codtrans = $data['codtrans'];
        $this->envio_codigo = $data['codigoenv'];
        $this->envio_nombre = $data['nombreenv'];
        $this->envio_apellidos = $data['apellidosenv'];
        $this->envio_apartado = $data['apartadoenv'];
        $this->envio_direccion = $data['direccionenv'];
        $this->envio_codpostal = $data['codpostalenv'];
        $this->envio_ciudad = $data['ciudadenv'];
        $this->envio_provincia = $data['provinciaenv'];
        $this->envio_codpais = $data['codpaisenv'];

        $this->numdocs = intval($data['numdocs']);
    }

    /**
     * 
     * @param fs_default_items $default_items
     */
    private function clear_trait($default_items)
    {
        $this->codigo = NULL;
        $this->codagente = NULL;
        $this->codpago = $default_items->codpago();
        $this->codserie = $default_items->codserie();
        $this->codejercicio = NULL;
        $this->codcliente = NULL;
        $this->coddivisa = NULL;
        $this->codalmacen = $default_items->codalmacen();
        $this->codpais = NULL;
        $this->coddir = NULL;
        $this->codpostal = '';
        $this->numero = NULL;
        $this->numero2 = NULL;
        $this->nombrecliente = '';
        $this->cifnif = '';
        $this->direccion = NULL;
        $this->ciudad = NULL;
        $this->provincia = NULL;
        $this->apartado = NULL;
        $this->fecha = Date('d-m-Y');
        $this->hora = Date('H:i:s');
        $this->netosindto = 0.0;
        $this->neto = 0.0;
        $this->dtopor1 = 0.0;
        $this->dtopor2 = 0.0;
        $this->dtopor3 = 0.0;
        $this->dtopor4 = 0.0;
        $this->dtopor5 = 0.0;
        $this->total = 0.0;
        $this->totaliva = 0.0;
        $this->totaleuros = 0.0;
        $this->irpf = 0.0;
        $this->totalirpf = 0.0;
        $this->porcomision = 0.0;
        $this->tasaconv = 1.0;
        $this->totalrecargo = 0.0;
        $this->observaciones = NULL;
        $this->femail = NULL;

        $this->envio_codtrans = NULL;
        $this->envio_codigo = NULL;
        $this->envio_nombre = NULL;
        $this->envio_apellidos = NULL;
        $this->envio_apartado = NULL;
        $this->envio_direccion = NULL;
        $this->envio_codpostal = NULL;
        $this->envio_ciudad = NULL;
        $this->envio_provincia = NULL;
        $this->envio_codpais = NULL;

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

    public function cliente_url()
    {
        if (is_null($this->codcliente)) {
            return "index.php?page=ventas_clientes";
        }

        return "index.php?page=ventas_cliente&cod=" . $this->codcliente;
    }

    public function test_trait()
    {
        $this->nombrecliente = $this->no_html($this->nombrecliente);
        if ($this->nombrecliente == '') {
            $this->nombrecliente = '-';
        }

        $this->direccion = $this->no_html($this->direccion);
        $this->ciudad = $this->no_html($this->ciudad);
        $this->provincia = $this->no_html($this->provincia);
        $this->envio_nombre = $this->no_html($this->envio_nombre);
        $this->envio_apellidos = $this->no_html($this->envio_apellidos);
        $this->envio_direccion = $this->no_html($this->envio_direccion);
        $this->envio_ciudad = $this->no_html($this->envio_ciudad);
        $this->envio_provincia = $this->no_html($this->envio_provincia);
        $this->numero2 = $this->no_html($this->numero2);
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

        /// Descuento total adicional del total del documento
        $t_dto_due = (1 - ((1 - $this->dtopor1 / 100) * (1 - $this->dtopor2 / 100) * (1 - $this->dtopor3 / 100) * (1 - $this->dtopor4 / 100) * (1 - $this->dtopor5 / 100))) * 100;
        $due_totales = (1 - $t_dto_due / 100);

        foreach ($this->get_lineas() as $lin) {
            if (!$lin->test()) {
                $status = FALSE;
            }

            $codimpuesto = ($lin->codimpuesto === null ) ? 0 : $lin->codimpuesto;
            if (!array_key_exists($codimpuesto, $subtotales)) {
                $subtotales[$codimpuesto] = array(
                    'netosindto' => 0,
                    'neto' => 0,
                    'iva' => 0, // Total IVA
                    'recargo' => 0, // Total Recargo
                );
            }

            /// Acumulamos por tipos de IVAs
            $subtotales[$codimpuesto]['netosindto'] += $lin->pvptotal;
            $subtotales[$codimpuesto]['neto'] += $due_totales * $lin->pvptotal;
            $subtotales[$codimpuesto]['iva'] += $due_totales * $lin->pvptotal * $lin->iva / 100;
            $subtotales[$codimpuesto]['recargo'] += $due_totales * $lin->pvptotal * $lin->recargo / 100;
            $irpf += $due_totales * $lin->pvptotal * $lin->irpf / 100;
            
            /// Cálculo anterior
            $neto_alt += $due_totales * $lin->pvptotal;
            $iva_alt += $due_totales * $lin->pvptotal * $lin->iva / 100;
        }

        /// redondeamos y sumamos
        $netosindto = 0;
        $neto = 0;
        $iva = 0;
        $recargo = 0;
        $irpf = round($irpf, FS_NF0);
        foreach ($subtotales as $subt) {
            $netosindto += round($subt['netosindto'], FS_NF0);
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
