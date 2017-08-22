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

/**
 * Albarán de cliente o albarán de venta. Representa la entrega a un cliente
 * de un material que se le ha vendido. Implica la salida de ese material
 * del almacén de la empresa.
 * 
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class albaran_cliente extends \fs_model
{

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
     * Código identificador único. Para humanos.
     * @var string
     */
    public $codigo;

    /**
     * Serie relacionada.
     * @var string
     */
    public $codserie;

    /**
     * Ejercicio relacionado. El que corresponde a la fecha.
     * @var string
     */
    public $codejercicio;

    /**
     * Código del cliente del albarán.
     * @var string
     */
    public $codcliente;

    /**
     * Empleado que ha creado este albarán.
     * @var string
     */
    public $codagente;

    /**
     * Forma de pago asociada.
     * @var string
     */
    public $codpago;

    /**
     * Divisa del albarán.
     * @var string
     */
    public $coddivisa;

    /**
     * Almacén del que sale la mercancía.
     * @var string
     */
    public $codalmacen;

    /**
     * País del cliente.
     * @var string
     */
    public $codpais;

    /**
     * ID de la dirección del cliente.
     * Modelo direccion_cliente.
     * @var string
     */
    public $coddir;

    /**
     * Código postal del cliente.
     * @var string
     */
    public $codpostal;

    /**
     * Número del albarán.
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
     * @var
     */
    public $fecha;

    /**
     * TODO
     * @var
     */
    public $hora;

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
     * Importe total del albarán, con impuestos.
     * @var float
     */
    public $total;

    /**
     * Suma total del IVA de las líneas.
     * @var float
     */
    public $totaliva;

    /**
     * Total expresado en euros, por si no fuese la divisa del albarán.
     * totaleuros = total/tasaconv
     * No hace falta rellenarlo, al hacer save() se calcula el valor.
     * @var float
     */
    public $totaleuros;

    /**
     * % de retención IRPF del albarán. Se obtiene de la serie.
     * Cada línea puede tener un % distinto.
     * @var float
     */
    public $irpf;

    /**
     * Suma total de las retenciones IRPF de las líneas.
     * @var float
     */
    public $totalirpf;

    /**
     * % de comisión del empleado (agente).
     * @var float
     */
    public $porcomision;

    /**
     * Tasa de conversión a Euros de la divisa seleccionada.
     * @var float
     */
    public $tasaconv;

    /**
     * Suma total del recargo de equivalencia de las líneas.
     * @var float
     */
    public $totalrecargo;

    /**
     * Observaciones del pedido
     * @var float
     */
    public $observaciones;

    /**
     * TRUE => está pendiente de factura.
     * @var bool
     */
    public $ptefactura;

    /**
     * Fecha en la que se envió el albarán por email.
     * @var string 
     */
    public $femail;

    /// datos de transporte

    /**
     * TODO
     * @var
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
     * Número de documentos adjuntos.
     * @var integer 
     */
    public $numdocs;

    public function __construct($data = FALSE)
    {
        parent::__construct('albaranescli');
        if ($data) {
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

            $this->codigo = $data['codigo'];
            $this->codagente = $data['codagente'];
            $this->codserie = $data['codserie'];
            $this->codejercicio = $data['codejercicio'];
            $this->codcliente = $data['codcliente'];
            $this->codpago = $data['codpago'];
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
            $this->observaciones = $this->no_html($data['observaciones']);

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
        } else {
            $this->idalbaran = NULL;
            $this->idfactura = NULL;
            $this->codigo = NULL;
            $this->codagente = NULL;
            $this->codserie = $this->default_items->codserie();
            $this->codejercicio = NULL;
            $this->codcliente = NULL;
            $this->codpago = $this->default_items->codpago();
            $this->coddivisa = NULL;
            $this->codalmacen = $this->default_items->codalmacen();
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
            $this->ptefactura = TRUE;
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
    }

    protected function install()
    {
        /// nos aseguramos de que se comprueba la tabla de facturas antes
        new \factura_cliente();

        return '';
    }

    public function show_hora($s = TRUE)
    {
        if ($s) {
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

        if ($this->idfactura) {
            $this->ptefactura = FALSE;
        }

        if ($this->floatcmp($this->total, $this->neto + $this->totaliva - $this->totalirpf + $this->totalrecargo, FS_NF0, TRUE)) {
            return TRUE;
        }

        $this->new_error_msg("Error grave: El total está mal calculado. ¡Avisa al informático!");
        return FALSE;
    }

    /**
     * Comprobaciones extra del albarán, devuelve TRUE si está todo correcto
     * @param boolean $duplicados
     * @return boolean
     */
    public function full_test($duplicados = TRUE)
    {
        $status = TRUE;

        /// comprobamos las líneas
        $netos = array();
        $netosindto = 0;
        $netocondto = 0;
        $subtotal = 0;
        $neto = 0;
        $iva = 0;
        $irpf = 0;
        $recargo = 0;
        $total = 0;
        
        // Descuento total adicional del total del documento
        $t_dto_due = (1-((1-$this->dtopor1/100)*(1-$this->dtopor2/100)*(1-$this->dtopor3/100)*(1-$this->dtopor4/100)*(1-$this->dtopor5/100)))*100;
        $due_totales = (1-$t_dto_due/100);
            
        foreach ($this->get_lineas() as $l) {
            if (!$l->test()) {
                $status = FALSE;
            }
            $codimpuesto = ($l->codimpuesto === null ) ? 0 : $l->codimpuesto;
            if (!array_key_exists($codimpuesto, $netos)) {
                $netos[$codimpuesto] = array(
                    'subtotal' => 0,    // Subtotal (Sumatorio de netos de línea)
                    'base' => 0,        // Base imponible
                    'iva' => 0,         // Total IVA
                    'irpf' => 0,        // Total IRPF
                    'recargo' => 0      // Total Recargo
                );
            }
            // Acumulamos por tipos de IVAs, que es el desglose de pie de página
            $netosindto = $l->pvptotal;                 // Precio neto por línea
            $netocondto = $netosindto * $due_totales;   // Precio neto - % desc sobre total
            $netos[$codimpuesto]['subtotal'] += $netosindto;
            $netos[$codimpuesto]['base'] += $netocondto;
            $netos[$codimpuesto]['iva'] += $netocondto * ($l->iva / 100);
            $netos[$codimpuesto]['irpf'] += $netocondto * ($this->irpf / 100);
            $netos[$codimpuesto]['recargo'] += $netocondto * ($l->recargo / 100);
        }

        foreach ($netos as $codimp => $ne) {
            $subtotal += $ne['subtotal'];               // Subtotal (Sumatorio de netos de línea)
            $neto += $ne['base'];                       // Sumatorio de bases imponibles
            $iva += $ne['iva'];                         // Sumatorio de IVAs
            $irpf += $ne['irpf'];                       // Sumatorio de IRPFs
            $recargo += $ne['recargo'];                 // Sumatorio de REs
            $total = $neto + $iva - $irpf + $recargo;   // Sumatorio del total
        }

        if (!$this->floatcmp($this->neto, $neto, FS_NF0, TRUE)) {
            $this->new_error_msg("Valor neto del " . FS_ALBARAN . ' ' . $this->codigo . " incorrecto. Valor correcto: " . $neto . " y tiene el valor " . $this->neto);
            $status = FALSE;
        } elseif (!$this->floatcmp($this->totaliva, $iva, FS_NF0, TRUE)) {
            $this->new_error_msg("Valor totaliva del " . FS_ALBARAN . ' ' . $this->codigo . " incorrecto. Valor correcto: " . $iva . " y tiene el valor " . $this->totaliva);
            $status = FALSE;
        } elseif (!$this->floatcmp($this->totalirpf, $irpf, FS_NF0, TRUE)) {
            $this->new_error_msg("Valor totalirpf del " . FS_ALBARAN . ' ' . $this->codigo . " incorrecto. Valor correcto: " . $irpf . " y tiene el valor " . $this->totalirpf);
            $status = FALSE;
        } elseif (!$this->floatcmp($this->totalrecargo, $recargo, FS_NF0, TRUE)) {
            $this->new_error_msg("Valor totalrecargo del " . FS_ALBARAN . ' ' . $this->codigo . " incorrecto. Valor correcto: " . $recargo . " y tiene el valor " . $this->totalrecargo);
            $status = FALSE;
        } elseif (!$this->floatcmp($this->total, $total, FS_NF0, TRUE)) {
            $this->new_error_msg("Valor total del " . FS_ALBARAN . ' ' . $this->codigo . " incorrecto. Valor correcto: " . $total . " y tiene el valor " . $this->total);
            $status = FALSE;
        }

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

        if ($status AND $duplicados) {
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
        $this->db->exec("UPDATE " . $this->table_name . " SET idfactura = NULL WHERE idfactura IS NOT NULL"
            . " AND idfactura NOT IN (SELECT idfactura FROM facturascli);");
        /// asignamos netosindto a neto a todos los que estén a 0
        $this->db->exec("UPDATE " . $this->table_name . " SET netosindto = neto WHERE netosindto = 0;");
    }
}
