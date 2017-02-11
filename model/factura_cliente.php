<?php
/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2013-2015  Carlos Garcia Gomez  neorazorx@gmail.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require_model('asiento.php');
require_model('ejercicio.php');
require_model('linea_iva_factura_cliente.php');
require_model('linea_factura_cliente.php');
require_model('secuencia.php');
require_model('serie.php');
require_model('inventario.php');
require_model('forma_pago.php');

/**
 * Factura de un cliente.
 */
class factura_cliente extends fs_model
{
   /**
    * Clave primaria.
    * @var type 
    */
   public $idfactura;
   
   /**
    * Asiento asociado.
    * @var type 
    */
   public $idasiento;
   
   /**
    * Sin uso.
    * @var type 
    */
   public $idpagodevol;
   
   /**
    * Todavía sin uso.
    * @var type 
    */
   public $idfacturarect;
   
   /**
    * Código único de la factura.
    * @var type 
    */
   public $codigo;
   public $numero;
   
   /**
    * Número opcional a disposición del usuario.
    * @var type 
    */
   public $numero2;
   
   /**
    * Todavía sin uso.
    * @var type 
    */
   public $codigorect;
   public $codejercicio;
   public $codserie;
   public $codalmacen;
   
   /**
    * Forma de pago.
    * @var type 
    */
   public $codpago;
   public $coddivisa;
   public $fecha;
   public $hora;
   public $codcliente;
   public $nombrecliente;
   public $nombre;
   public $cifnif;
   public $direccion;
   public $ciudad;
   public $provincia;
   public $apartado;
   
   /**
    * ID de la dirección en dirclientes.
    * @var type 
    */
   public $coddir;
   
   public $codpostal;
   public $codpais;
   public $codagente;
   public $neto;
   public $totaliva;
   public $total;
   
   /**
    * totaleuros = total*tasaconv
    * Esto es para dar compatibilidad a Eneboo. Fuera de eso, no tiene sentido.
    * Ni siquiera hace falta rellenarlo, al hacer save() se calcula el valor.
    * @var type 
    */
   public $totaleuros;
   public $irpf;
   public $totalirpf;
   
   /**
    * % comisión del empleado (agente).
    * @var type 
    */
   public $porcomision;
   public $tasaconv;
   
   /**
    * Sin uso.
    * @var type 
    */
   public $recfinanciero;
   
   public $totalrecargo;
   public $observaciones;
   public $pagada;
   
   /**
    * Sin uso.
    * @var type 
    */
   public $deabono;
   
   /**
    * Sin uso.
    * @var type 
    */
   public $automatica;
   
   /**
    * Sin uso.
    * @var type 
    */
   public $editable;
   
   /**
    * Sin uso.
    * @var type 
    */
   public $nogenerarasiento;
   
   /**
    * Fecha de vencimiento de la factura.
    * @var type 
    */
   public $vencimiento;
   
   public function __construct($f=FALSE)
   {
      parent::__construct('facturascli', 'plugins/facturacion_base/');
      if($f)
      {
         $this->idfactura = $this->intval($f['idfactura']);
         $this->idasiento = $this->intval($f['idasiento']);
         $this->idpagodevol = $this->intval($f['idpagodevol']);
         $this->idfacturarect = $this->intval($f['idfacturarect']);
         $this->codigo = $f['codigo'];
         $this->numero = $f['numero'];
         $this->numero2 = $f['numero2'];
         $this->codigorect = $f['codigorect'];
         $this->codejercicio = $f['codejercicio'];
         $this->codserie = $f['codserie'];
         $this->codalmacen = $f['codalmacen'];
         $this->codpago = $f['codpago'];
         $this->coddivisa = $f['coddivisa'];
         $this->fecha = Date('d-m-Y', strtotime($f['fecha']));
         
         $this->hora = '00:00:00';
         if( !is_null($f['hora']) )
         {
            $this->hora = date('h:i:s', strtotime($f['hora']));
         }
         
         $this->codcliente = $f['codcliente'];
         $this->nombrecliente = $f['nombrecliente'];
         $this->cifnif = $f['cifnif'];
         $this->direccion = $f['direccion'];
         $this->ciudad = $f['ciudad'];
         $this->provincia = $f['provincia'];
         $this->apartado = $f['apartado'];
         $this->coddir = $f['coddir'];
         $this->codpostal = $f['codpostal'];
         $this->codpais = $f['codpais'];
         $this->codagente = $f['codagente'];
         $this->neto = floatval($f['neto']);
         $this->totaliva = floatval($f['totaliva']);
         $this->total = floatval($f['total']);
         $this->totaleuros = floatval($f['totaleuros']);
         $this->irpf = floatval($f['irpf']);
         $this->totalirpf = floatval($f['totalirpf']);
         $this->porcomision = floatval($f['porcomision']);
         $this->tasaconv = floatval($f['tasaconv']);
         $this->recfinanciero = floatval($f['recfinanciero']);
         $this->totalrecargo = floatval($f['totalrecargo']);
         $this->observaciones = $this->no_html($f['observaciones']);
         $this->pagada = $this->str2bool($f['pagada']);
         $this->deabono = $this->str2bool($f['deabono']);
         $this->automatica = $this->str2bool($f['automatica']);
         $this->editable = $this->str2bool($f['editable']);
         $this->nogenerarasiento = $this->str2bool($f['nogenerarasiento']);
         
         $this->vencimiento = Date('d-m-Y', strtotime($f['fecha'].' +1month'));
         if( !is_null($f['vencimiento']) )
         {
            $this->vencimiento = Date('d-m-Y', strtotime($f['vencimiento']));
         }
      }
      else
      {
         $this->idfactura = NULL;
         $this->idasiento = NULL;
         $this->idpagodevol = NULL;
         $this->idfacturarect = NULL;
         $this->codigo = NULL;
         $this->numero = NULL;
         $this->numero2 = NULL;
         $this->codigorect = NULL;
         $this->codejercicio = NULL;
         $this->codserie = NULL;
         $this->codalmacen = NULL;
         $this->codpago = NULL;
         $this->coddivisa = NULL;
         $this->fecha = Date('d-m-Y');
         $this->hora = Date('H:i:s');
         $this->codcliente = NULL;
         $this->nombrecliente = NULL;
         $this->cifnif = NULL;
         $this->direccion = NULL;
         $this->provincia = NULL;
         $this->ciudad = NULL;
         $this->apartado = NULL;
         $this->coddir = NULL;
         $this->codpostal = NULL;
         $this->codpais = NULL;
         $this->codagente = NULL;
         $this->neto = 0;
         $this->totaliva = 0;
         $this->total = 0;
         $this->totaleuros = 0;
         $this->irpf = 0;
         $this->totalirpf = 0;
         $this->porcomision = 0;
         $this->tasaconv = 1;
         $this->recfinanciero = 0;
         $this->totalrecargo = 0;
         $this->observaciones = NULL;
         $this->pagada = FALSE;
         $this->deabono = FALSE;
         $this->automatica = FALSE;
         $this->editable = TRUE;
         $this->nogenerarasiento = FALSE;
         $this->vencimiento = Date('d-m-Y', strtotime('+1month'));
      }
   }

	/**
	 * @var recibo_cliente[]
	 */
    protected $recibos;

    public function getRecibos() {
    	if(!$this->recibos) {
    		$this->recibos = pago_por_caja::getRecibosByFactura($this->idfactura);
	    }
    	return $this->recibos;
    }

	/**
	 * @return float
	 */
    public function getMontoPago() {
	    $total_pago = 0.0;
	    $recibos = $this->getRecibos();
	    if($recibos) {
		    foreach ($recibos as $recibo) {
		        if(is_a($recibo, "recibo_cliente")) {
                    $total_pago += (float) $recibo->importe;
                } else {
		            //Ni idea que pasa
                    var_dump($recibo);
                }
		    }
	    }
		return $total_pago;
    }

	/**
	 * @return float
	 */
	public function getSaldo() {
		$saldo = ((float) $this->total) - $this->getMontoPago();
		return ($saldo < 0) ? 0 : $saldo;
	}

	protected function install()
   {
      new serie();
      new asiento();
      
      return '';
   }
   
   public function observaciones_resume()
   {
      if($this->observaciones == '')
      {
         return '-';
      }
      else if( strlen($this->observaciones) < 60 )
      {
         return $this->observaciones;
      }
      else
         return substr($this->observaciones, 0, 50).'...';
   }
   
   public function vencida()
   {
      if($this->pagada)
      {
         return FALSE;
      }
      else
      {
         return ( strtotime($this->vencimiento) < strtotime(Date('d-m-Y')) );
      }
   }
   
   public function url()
   {
      if( is_null($this->idfactura) )
      {
         return 'index.php?page=ventas_facturas';
      }
      else
         return 'index.php?page=ventas_factura&id='.$this->idfactura;
   }
   
   public function asiento_url()
   {
      if( is_null($this->idasiento) )
      {
         return 'index.php?page=contabilidad_asientos';
      }
      else
         return 'index.php?page=contabilidad_asiento&id='.$this->idasiento;
   }
   
   public function agente_url()
   {
      if( is_null($this->codagente) )
      {
         return "index.php?page=admin_agentes";
      }
      else
         return "index.php?page=admin_agente&cod=".$this->codagente;
   }
   
   public function cliente_url()
   {
      if( is_null($this->codcliente) )
      {
         return "index.php?page=ventas_clientes";
      }
      else
         return "index.php?page=ventas_cliente&cod=".$this->codcliente;
   }
   
   public function get_asiento()
   {
      $asiento = new asiento();
      return $asiento->get($this->idasiento);
   }

    /**
     * @return linea_factura_cliente[]
     */
   public function get_lineas()
   {
      $linea = new linea_factura_cliente();
      return $linea->all_from_factura($this->idfactura);
   }
   
   public function get_lineas_iva()
   {
      $linea_iva = new linea_iva_factura_cliente();
      $lineasi = $linea_iva->all_from_factura($this->idfactura);
      /// si no hay lineas de IVA las generamos
      if( !$lineasi )
      {
         $lineas = $this->get_lineas();
         if($lineas)
         {
            foreach($lineas as $l)
            {
               $i = 0;
               $encontrada = FALSE;
               while($i < count($lineasi))
               {
                  if($l->codimpuesto == $lineasi[$i]->codimpuesto)
                  {
                     $encontrada = TRUE;
                     $lineasi[$i]->neto += $l->pvptotal;
                     $lineasi[$i]->totaliva += ($l->pvptotal*$l->iva)/100;
                     $lineasi[$i]->totalrecargo += ($l->pvptotal*$l->recargo)/100;
                  }
                  $i++;
               }
               if( !$encontrada )
               {
                  $lineasi[$i] = new linea_iva_factura_cliente();
                  $lineasi[$i]->idfactura = $this->idfactura;
                  $lineasi[$i]->codimpuesto = $l->codimpuesto;
                  $lineasi[$i]->iva = $l->iva;
                  $lineasi[$i]->recargo = $l->recargo;
                  $lineasi[$i]->neto = $l->pvptotal;
                  $lineasi[$i]->totaliva = ($l->pvptotal*$l->iva)/100;
                  $lineasi[$i]->totalrecargo = ($l->pvptotal*$l->recargo)/100;
               }
            }
            
            /// redondeamos y guardamos
            if( count($lineasi) == 1 )
            {
               $lineasi[0]->neto = round($lineasi[0]->neto, FS_NF0);
               $lineasi[0]->totaliva = round($lineasi[0]->totaliva, FS_NF0);
               $lineasi[0]->totaliva = round($lineasi[0]->totaliva, FS_NF0);
               $lineasi[0]->totallinea = $lineasi[0]->neto + $lineasi[0]->totaliva + $lineasi[0]->totalrecargo;
               $lineasi[0]->save();
            }
            else
            {
               /*
                * Como el neto y el iva se redondean en la factura, al dividirlo
                * en líneas de iva podemos encontrarnos con un descuadre que
                * hay que calcular y solucionar.
                */
               $t_neto = 0;
               $t_iva = 0;
               foreach($lineasi as $li)
               {
                  $li->neto = bround($li->neto, FS_NF0);
                  $li->totaliva = bround($li->totaliva, FS_NF0);
                  $li->totallinea = $li->neto + $li->totaliva;
                  
                  $t_neto += $li->neto;
                  $t_iva += $li->totaliva;
               }
               
               if( !$this->floatcmp($this->neto, $t_neto) )
               {
                  /*
                   * Sumamos o restamos un céntimo a los netos más altos
                   * hasta que desaparezca el descuadre
                   */
                  $diferencia = round( ($this->neto-$t_neto) * 100 );
                  usort($lineasi, function($a, $b) {
                     if($a->totallinea == $b->totallinea)
                        return 0;
                     else
                        return ($a->totallinea < $b->totallinea) ? 1 : -1;
                  });
                  
                  foreach($lineasi as $i => $value)
                  {
                     if($diferencia > 0)
                     {
                        $lineasi[$i]->neto += .01;
                        $diferencia--;
                     }
                     else if($diferencia < 0)
                     {
                        $lineasi[$i]->neto -= .01;
                        $diferencia++;
                     }
                     else
                        break;
                  }
               }
               
               if( !$this->floatcmp($this->totaliva, $t_iva) )
               {
                  /*
                   * Sumamos o restamos un céntimo a los netos más altos
                   * hasta que desaparezca el descuadre
                   */
                  $diferencia = round( ($this->totaliva-$t_iva) * 100 );
                  usort($lineasi, function($a, $b) {
                     if($a->totallinea == $b->totallinea)
                        return 0;
                     else
                        return ($a->totallinea < $b->totallinea) ? 1 : -1;
                  });
                  
                  foreach($lineasi as $i => $value)
                  {
                     if($diferencia > 0)
                     {
                        $lineasi[$i]->totaliva += .01;
                        $diferencia--;
                     }
                     else if($diferencia < 0)
                     {
                        $lineasi[$i]->totaliva -= .01;
                        $diferencia++;
                     }
                     else
                        break;
                  }
               }
               
               foreach($lineasi as $i => $value)
               {
                  $lineasi[$i]->totallinea = $value->neto + $value->totaliva + $value->totalrecargo;
                  $lineasi[$i]->save();
               }
            }
         }
      }
      return $lineasi;
   }
   
   public function get($id)
   {
      $fact = $this->db->select("SELECT * FROM ".$this->table_name." WHERE idfactura = ".$this->var2str($id).";");
      if($fact)
      {
         return new factura_cliente($fact[0]);
      }
      else
         return FALSE;
   }
   
   public function get_by_codigo($cod)
   {
      $fact = $this->db->select("SELECT * FROM ".$this->table_name." WHERE codigo = ".$this->var2str($cod).";");
      if($fact)
      {
         return new factura_cliente($fact[0]);
      }
      else
         return FALSE;
   }

    public function getFormaPago() {
        $formapago = new forma_pago();

        return $formapago->get($this->codpago)->descripcion;
    }

    public function exists()
   {
      if( is_null($this->idfactura) )
      {
         return FALSE;
      }
      else
         return $this->db->select("SELECT * FROM ".$this->table_name." WHERE idfactura = ".$this->var2str($this->idfactura).";");
   }
   
   public function new_codigo()
   {
      /// buscamos un hueco
      $encontrado = FALSE;
      $num = intval(FS_NFACTURA_CLI); /// definido en el config2
      $fecha = $this->fecha;
      $numeros = $this->db->select("SELECT ".$this->db->sql_to_int('numero')." as numero,fecha
         FROM ".$this->table_name." WHERE codejercicio = ".$this->var2str($this->codejercicio).
         " AND codserie = ".$this->var2str($this->codserie)." ORDER BY numero ASC;");
      if($numeros)
      {
         foreach($numeros as $n)
         {
            if( intval($n['numero']) > $num )
            {
               $encontrado = TRUE;
               $fecha = Date('d-m-Y', strtotime($n['fecha']));
               break;
            }
            else
               $num++;
         }
      }
      
      if($encontrado)
      {
         $this->numero = $num;
         $this->fecha = $fecha;
      }
      else
      {
         $this->numero = $num;
         
         /// nos guardamos la secuencia para abanq/eneboo
         $sec = new secuencia();
         $sec = $sec->get_by_params2($this->codejercicio, $this->codserie, 'nfacturacli');
         if($sec)
         {
            if($sec->valorout <= $this->numero)
            {
               $sec->valorout = 1 + $this->numero;
               $sec->save();
            }
         }
      }
      
      if(FS_NEW_CODIGO == 'eneboo')
      {
         $this->codigo = $this->codejercicio.sprintf('%02s', $this->codserie).sprintf('%06s', $this->numero);
      }
      else
      {
         $this->codigo = 'FAC'.$this->codejercicio.$this->codserie.$this->numero;
      }
   }
   
   public function test()
   {

       if($this->default_items->getRequirenum2() && $this->numero2 != '') {
           $res = $this->db->select("SELECT idfactura,numero2 FROM " . $this->table_name . " WHERE numero2 = " . $this->var2str($this->numero2));
           if(is_array($res) && isset($res[0]) && $res[0]['idfactura'] != $this->idfactura) {
               $this->new_error_msg('El número de factura ya está siendo utilizado en otra factura');
               return false;
           }
       }

      $this->observaciones = $this->no_html($this->observaciones);
      $this->totaleuros = $this->total * $this->tasaconv;
      
      if( $this->floatcmp($this->total, $this->neto+$this->totaliva-$this->totalirpf+$this->totalrecargo, FS_NF0, TRUE) )
      {
         return TRUE;
      }
      else
      {
         $this->new_error_msg("Error grave: El total está mal calculado. ¡Informa del error!");
         return FALSE;
      }
   }
   
   public function full_test($duplicados = TRUE)
   {
      $status = TRUE;
      
      /// comprobamos la fecha de la factura
      $ejercicio = new ejercicio();
      $eje0 = $ejercicio->get($this->codejercicio);
      if($eje0)
      {
         if( strtotime($this->fecha) < strtotime($eje0->fechainicio) OR strtotime($this->fecha) > strtotime($eje0->fechafin) )
         {
            $status = FALSE;
            $this->new_error_msg("La fecha de esta factura está fuera del rango del <a target='_blank' href='".$eje0->url()."'>ejercicio</a>.");
         }
      }
      $numero0 = intval($this->numero)-1;
      if( $numero0 > 0 )
      {
         $codigo0 = $this->codejercicio . sprintf('%02s', $this->codserie) . sprintf('%06s', $numero0);
         $fac0 = $this->get_by_codigo($codigo0);
         if($fac0)
         {
            if( strtotime($fac0->fecha) > strtotime($this->fecha) )
            {
               $status = FALSE;
               $this->new_error_msg("La fecha de esta factura es anterior a la fecha de <a href='".
                       $fac0->url()."'>la factura anterior</a>.");
            }
         }
      }
      $numero2 = intval($this->numero)+1;
      $codigo2 = $this->codejercicio . sprintf('%02s', $this->codserie) . sprintf('%06s', $numero2);
      $fac2 = $this->get_by_codigo($codigo2);
      if($fac2)
      {
         if( strtotime($fac2->fecha) < strtotime($this->fecha) )
         {
            $status = FALSE;
            $this->new_error_msg("La fecha de esta factura es posterior a la fecha de <a href='".
                    $fac2->url()."'>la factura siguiente</a>.");
         }
      }
      
      /// comprobamos las líneas
      $neto = 0;
      $iva = 0;
      $irpf = 0;
      $recargo = 0;
      foreach($this->get_lineas() as $l)
      {
         if( !$l->test() )
            $status = FALSE;
         
         $neto += $l->pvptotal;
         $iva += $l->pvptotal * $l->iva / 100;
         $irpf += $l->pvptotal * $l->irpf / 100;
         $recargo += $l->pvptotal * $l->recargo / 100;
      }
      
      $neto = round($neto, FS_NF0);
      $iva = round($iva, FS_NF0);
      $irpf = round($irpf, FS_NF0);
      $recargo = round($recargo, FS_NF0);
      $total = $neto + $iva - $irpf + $recargo;
      
      if( !$this->floatcmp($this->neto, $neto, FS_NF0, TRUE) )
      {
         $this->new_error_msg("Valor neto de la factura incorrecto. Valor correcto: ".$neto);
         $status = FALSE;
      }
      else if( !$this->floatcmp($this->totaliva, $iva, FS_NF0, TRUE) )
      {
         $this->new_error_msg("Valor totaliva de la factura incorrecto. Valor correcto: ".$iva);
         $status = FALSE;
      }
      else if( !$this->floatcmp($this->totalirpf, $irpf, FS_NF0, TRUE) )
      {
         $this->new_error_msg("Valor totalirpf de la factura incorrecto. Valor correcto: ".$irpf);
         $status = FALSE;
      }
      else if( !$this->floatcmp($this->totalrecargo, $recargo, FS_NF0, TRUE) )
      {
         $this->new_error_msg("Valor totalrecargo de la factura incorrecto. Valor correcto: ".$recargo);
         $status = FALSE;
      }
      else if( !$this->floatcmp($this->total, $total, FS_NF0, TRUE) )
      {
         $this->new_error_msg("Valor total de la factura incorrecto. Valor correcto: ".$total);
         $status = FALSE;
      }
      else if( !$this->floatcmp($this->totaleuros, $this->total * $this->tasaconv, FS_NF0, TRUE) )
      {
         $this->new_error_msg("Valor totaleuros de la factura incorrecto.
            Valor correcto: ".round($this->total * $this->tasaconv, FS_NF0));
         $status = FALSE;
      }
      
      /// comprobamos las líneas de IVA
      $this->get_lineas_iva();
      $linea_iva = new linea_iva_factura_cliente();
      $status = $linea_iva->factura_test($this->idfactura, $neto, $iva, $recargo);
      
      /// comprobamos el asiento
      if( isset($this->idasiento) )
      {
         $asiento = $this->get_asiento();
         if($asiento)
         {
            if($asiento->tipodocumento != 'Ingreso' )
            {
               $this->new_error_msg("Esta factura apunta a un <a href='".$this->asiento_url()."'>asiento incorrecto</a>.");
               $status = FALSE;
            }
         }
         else
         {
            $this->new_error_msg("Asiento no encontrado.");
            $status = FALSE;
         }
      }
      
      if($status AND $duplicados)
      {
         /// comprobamos si es un duplicado
         $facturas = $this->db->select("SELECT * FROM ".$this->table_name." WHERE fecha = ".$this->var2str($this->fecha)."
            AND codcliente = ".$this->var2str($this->codcliente)." AND total = ".$this->var2str($this->total)."
            AND observaciones = ".$this->var2str($this->observaciones)." AND idfactura != ".$this->var2str($this->idfactura).";");
         if($facturas)
         {
            foreach($facturas as $fac)
            {
               /// comprobamos las líneas
               $aux = $this->db->select("SELECT referencia FROM lineasfacturascli WHERE
                  idfactura = ".$this->var2str($this->idfactura)."
                  AND referencia NOT IN (SELECT referencia FROM lineasfacturascli
                  WHERE idfactura = ".$this->var2str($fac['idfactura']).");");
               if( !$aux )
               {
                  $this->new_error_msg("Esta factura es un posible duplicado de
                     <a href='index.php?page=ventas_factura&id=".$fac['idfactura']."'>esta otra</a>.
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
      if( $this->test() )
      {
         $this->clean_cache();
         if( $this->exists() )
         {
		 
		 
		 /// toma el asiento de la factura para que no cambie cuando hay devolución al asiento de egreso
		  	$varfactura = new factura_cliente();
	  		$factura=$varfactura->get($this->idfactura);	
		 	if($factura AND $factura->idasiento==NULL) $valor_asiento=$this->idasiento;
		 	else $valor_asiento=$factura->idasiento;
			////////
		 
            $sql = "UPDATE ".$this->table_name." SET idasiento = ".$this->var2str($valor_asiento).
                    ", idpagodevol = ".$this->var2str($this->idpagodevol).
                    ", idfacturarect = ".$this->var2str($this->idfacturarect).
                    ", codigo = ".$this->var2str($this->codigo).
                    ", numero = ".$this->var2str($this->numero).
                    ", numero2 = ".$this->var2str($this->numero2).
                    ", codigorect = ".$this->var2str($this->codigorect).
                    ", codejercicio = ".$this->var2str($this->codejercicio).
                    ", codserie = ".$this->var2str($this->codserie).
                    ", codalmacen = ".$this->var2str($this->codalmacen).
                    ", codpago = ".$this->var2str($this->codpago).
                    ", coddivisa = ".$this->var2str($this->coddivisa).
                    ", fecha = ".$this->var2str($this->fecha).
                    ", codcliente = ".$this->var2str($this->codcliente).
                    ", nombrecliente = ".$this->var2str($this->nombrecliente).
                    ", cifnif = ".$this->var2str($this->cifnif).
                    ", direccion = ".$this->var2str($this->direccion).
                    ", ciudad = ".$this->var2str($this->ciudad).
                    ", provincia = ".$this->var2str($this->provincia).
                    ", apartado = ".$this->var2str($this->apartado).
                    ", coddir = ".$this->var2str($this->coddir).
                    ", codpostal = ".$this->var2str($this->codpostal).
                    ", codpais = ".$this->var2str($this->codpais).
                    ", codagente = ".$this->var2str($this->codagente).
                    ", neto = ".$this->var2str($this->neto).
                    ", totaliva = ".$this->var2str($this->totaliva).
                    ", total = ".$this->var2str($this->total).
                    ", totaleuros = ".$this->var2str($this->totaleuros).
                    ", irpf = ".$this->var2str($this->irpf).
                    ", totalirpf = ".$this->var2str($this->totalirpf).
                    ", porcomision = ".$this->var2str($this->porcomision).
                    ", tasaconv = ".$this->var2str($this->tasaconv).
                    ", recfinanciero = ".$this->var2str($this->recfinanciero).
                    ", totalrecargo = ".$this->var2str($this->totalrecargo).
                    ", observaciones = ".$this->var2str($this->observaciones).
                    ", pagada = ".$this->var2str($this->pagada).
                    ", deabono = ".$this->var2str($this->deabono).
                    ", automatica = ".$this->var2str($this->automatica).
                    ", editable = ".$this->var2str($this->editable).
                    ", nogenerarasiento = ".$this->var2str($this->nogenerarasiento).
                    ", hora = ".$this->var2str($this->hora).
                    ", vencimiento = ".$this->var2str($this->vencimiento).
                    " WHERE idfactura = ".$this->var2str($this->idfactura).";";
            
            return $this->db->exec($sql);
         }
         else
         {
            $this->new_codigo();
            $sql = "INSERT INTO ".$this->table_name." (idasiento,idpagodevol,idfacturarect,codigo,numero,
               codigorect,codejercicio,codserie,codalmacen,codpago,coddivisa,fecha,codcliente,nombrecliente,
               cifnif,direccion,ciudad,provincia,apartado,coddir,codpostal,codpais,codagente,neto,totaliva,total,totaleuros,
               irpf,totalirpf,porcomision,tasaconv,recfinanciero,totalrecargo,pagada,observaciones,deabono,automatica,editable,
               nogenerarasiento,hora,numero2,vencimiento) VALUES (".$this->var2str($this->idasiento).
                    ",".$this->var2str($this->idpagodevol).
                    ",".$this->var2str($this->idfacturarect).
                    ",".$this->var2str($this->codigo).
                    ",".$this->var2str($this->numero).
                    ",".$this->var2str($this->codigorect).
                    ",".$this->var2str($this->codejercicio).
                    ",".$this->var2str($this->codserie).
                    ",".$this->var2str($this->codalmacen).
                    ",".$this->var2str($this->codpago).
                    ",".$this->var2str($this->coddivisa).
                    ",".$this->var2str($this->fecha).
                    ",".$this->var2str($this->codcliente).
                    ",".$this->var2str($this->nombrecliente).
                    ",".$this->var2str($this->cifnif).
                    ",".$this->var2str($this->direccion).
                    ",".$this->var2str($this->ciudad).
                    ",".$this->var2str($this->provincia).
                    ",".$this->var2str($this->apartado).
                    ",".$this->var2str($this->coddir).
                    ",".$this->var2str($this->codpostal).
                    ",".$this->var2str($this->codpais).
                    ",".$this->var2str($this->codagente).
                    ",".$this->var2str($this->neto).
                    ",".$this->var2str($this->totaliva).
                    ",".$this->var2str($this->total).
                    ",".$this->var2str($this->totaleuros).
                    ",".$this->var2str($this->irpf).
                    ",".$this->var2str($this->totalirpf).
                    ",".$this->var2str($this->porcomision).
                    ",".$this->var2str($this->tasaconv).
                    ",".$this->var2str($this->recfinanciero).
                    ",".$this->var2str($this->totalrecargo).
                    ",".$this->var2str($this->pagada).
                    ",".$this->var2str($this->observaciones).
                    ",".$this->var2str($this->deabono).
                    ",".$this->var2str($this->automatica).
                    ",".$this->var2str($this->editable).
                    ",".$this->var2str($this->nogenerarasiento).
                    ",".$this->var2str($this->hora).
                    ",".$this->var2str($this->numero2).
                    ",".$this->var2str($this->vencimiento).");";
            
            if( $this->db->exec($sql) )
            {
               $this->idfactura = $this->db->lastval();
               return TRUE;
            }
            else
               return FALSE;
         }
      }
      else
         return FALSE;
   }
   
   public function delete()
   {
      $this->clean_cache();
      
      if( $this->db->exec("DELETE FROM ".$this->table_name." WHERE idfactura = ".$this->var2str($this->idfactura).";") )
      {
         if($this->idasiento)
         {
            /**
             * Delegamos la eliminación del asiento en la clase correspondiente.
             */
            $asiento = new asiento();
            $asi0 = $asiento->get($this->idasiento);
            if($asi0)
            {
               $asi0->delete();
            }
         }
         
         /// desvinculamos el/los albaranes asociados
         $this->db->exec("UPDATE albaranescli SET idfactura = NULL, ptefactura = TRUE WHERE idfactura = ".$this->var2str($this->idfactura).";");
         
         return TRUE;
      }
      else
         return FALSE;
   }
   
   private function clean_cache()
   {
      $this->cache->delete('factura_cliente_huecos');
   }
   
   public function all($offset=0, $limit=FS_ITEM_LIMIT)
   {
      $faclist = array();
      $facturas = $this->db->select_limit("SELECT * FROM ".$this->table_name." ORDER BY fecha DESC, codigo DESC", $limit, $offset);
      if($facturas)
      {
         foreach($facturas as $f)
            $faclist[] = new factura_cliente($f);
      }
      return $faclist;
   }
   
         public function facturas_cliente($offset=0, $limit=FS_ITEM_LIMIT)
   {
   
      $faclist = array();
      $facturas = $this->db->select_limit("SELECT *,nombrecliente as nombre FROM ".$this->table_name." WHERE idasiento = ".$this->var2str($this->idasiento)." AND codejercicio = ".$this->var2str($this->codejercicio)." ORDER BY fecha DESC, codigo DESC", $limit, $offset);
      if($facturas)
      {
         foreach($facturas as $f)
            $faclist[] = new factura_cliente($f);
      }
      return $faclist;
   }
   
   public function all_sin_pagar($offset=0, $limit=FS_ITEM_LIMIT)
   {
      $faclist = array();
      $facturas = $this->db->select_limit("SELECT * FROM ".$this->table_name.
         " WHERE pagada = false ORDER BY vencimiento ASC, codigo ASC", $limit, $offset);
      if($facturas)
      {
         foreach($facturas as $f)
            $faclist[] = new factura_cliente($f);
      }
      return $faclist;
   }
   
   public function all_from_agente($codagente, $offset=0)
   {
      $faclist = array();
      $facturas = $this->db->select_limit("SELECT * FROM ".$this->table_name.
         " WHERE codagente = ".$this->var2str($codagente).
         " ORDER BY fecha DESC, codigo DESC", FS_ITEM_LIMIT, $offset);
      if($facturas)
      {
         foreach($facturas as $f)
            $faclist[] = new factura_cliente($f);
      }
      return $faclist;
   }
   
   public function all_from_cliente($codcliente, $offset=0)
   {
      $faclist = array();
      $facturas = $this->db->select_limit("SELECT * FROM ".$this->table_name.
         " WHERE codcliente = ".$this->var2str($codcliente).
         " ORDER BY fecha DESC, codigo DESC", FS_ITEM_LIMIT, $offset);
      if($facturas)
      {
         foreach($facturas as $f)
            $faclist[] = new factura_cliente($f);
      }
      return $faclist;
   }
   
   public function all_desde($desde, $hasta, $serie=FALSE)
   {
      $faclist = array();
      $sql = "SELECT * FROM ".$this->table_name." WHERE fecha >= ".$this->var2str($desde)." AND fecha <= ".$this->var2str($hasta);
      if($serie)
      {
         $sql .= " AND codserie = ".$this->var2str($serie);
      }
      $sql .= " ORDER BY fecha ASC, codigo ASC;";
      
      $facturas = $this->db->select($sql);
      if($facturas)
      {
         foreach($facturas as $f)
            $faclist[] = new factura_cliente($f);
      }
      return $faclist;
   }
   
   public function search($query, $offset=0)
   {
      $faclist = array();
      $query = strtolower( $this->no_html($query) );
      
      $consulta = "SELECT * FROM ".$this->table_name." WHERE ";
      if( is_numeric($query) )
      {
         $consulta .= "codigo LIKE '%".$query."%' OR numero2 LIKE '%".$query."%' OR observaciones LIKE '%".$query."%'
            OR total BETWEEN ".($query-.01)." AND ".($query+.01);
      }
      else if( preg_match('/^([0-9]{1,2})-([0-9]{1,2})-([0-9]{4})$/i', $query) )
      {
         $consulta .= "fecha = ".$this->var2str($query)." OR observaciones LIKE '%".$query."%'";
      }
      else
      {
         $consulta .= "lower(codigo) LIKE '%".$query."%' OR lower(numero2) LIKE '%".$query."%' "
                 . "OR lower(observaciones) LIKE '%".str_replace(' ', '%', $query)."%'";
      }
      $consulta .= " ORDER BY fecha DESC, codigo DESC";
      
      $facturas = $this->db->select_limit($consulta, FS_ITEM_LIMIT, $offset);
      if($facturas)
      {
         foreach($facturas as $f)
            $faclist[] = new factura_cliente($f);
      }
      return $faclist;
   }
   
   public function search_from_cliente($codcliente, $desde, $hasta, $serie, $obs='')
   {
      $faclist = array();
      $sql = "SELECT * FROM ".$this->table_name." WHERE codcliente = ".$this->var2str($codcliente).
         " AND fecha BETWEEN ".$this->var2str($desde)." AND ".$this->var2str($hasta).
         " AND codserie = ".$this->var2str($serie);
      
      if($obs != '')
         $sql .= " AND lower(observaciones) = ".$this->var2str(strtolower($obs));
      
      $sql .= " ORDER BY fecha DESC, codigo DESC;";
      
      $facturas = $this->db->select($sql);
      if($facturas)
      {
         foreach($facturas as $f)
            $faclist[] = new factura_cliente($f);
      }
      return $faclist;
   }
   
      public function boton_anular($idfactura)
   {
   
      $factura = new factura_cliente();
         $fact = $factura->get($idfactura);
      if($fact)
      {
         /// ¿Sumamos stock?
         $art0 = new articulo();
		 $inventario = new inventario();
         foreach($fact->get_lineas() as $linea)
         {
            if( is_null($linea->idalbaran) )
            {
               $articulo = $art0->get($linea->referencia);
               if($articulo)
               {
                  $articulo->sum_stock($fact->codalmacen, $linea->cantidad);
				  $inventario->inventario_agregar( $fact->codalmacen,$linea->referencia,$linea->cantidad,$linea->pvpunitario);
               }
            }
         }
         
   
    $sql = "UPDATE ".$this->table_name." SET idpagodevol = '1'   WHERE idfactura = ".$this->var2str($idfactura).";";
           if( $this->db->exec($sql) )
            {
               $this->idfactura = $this->db->lastval();
               return TRUE;
            }
            else
               return FALSE;  
		}
		else
         $this->new_error_msg("Factura no encontrada.");	   
   }
   
   public function huecos()
   {
      $error = TRUE;
      $huecolist = $this->cache->get_array2('factura_cliente_huecos', $error, TRUE);
      if( $error )
      {
         $ejercicio = new ejercicio();
         foreach($ejercicio->all_abiertos() as $eje)
         {
            $codserie = '';
            $num = intval(FS_NFACTURA_CLI); /// definido en el config2
            $numeros = $this->db->select("SELECT codserie,".$this->db->sql_to_int('numero')." as numero,fecha,hora
               FROM ".$this->table_name." WHERE codejercicio = ".$this->var2str($eje->codejercicio).
               " ORDER BY codserie ASC, numero ASC;");
            if( $numeros )
            {
               foreach($numeros as $n)
               {
                  if( $n['codserie'] != $codserie )
                  {
                     $codserie = $n['codserie'];
                     $num = intval(FS_NFACTURA_CLI); /// definido en el config2
                  }
                  
                  if( intval($n['numero']) != $num )
                  {
                     while($num < intval($n['numero']))
                     {
                        $huecolist[] = array(
                            'codigo' => $eje->codejercicio . sprintf('%02s', $codserie) . sprintf('%06s', $num),
                            'fecha' => Date('d-m-Y', strtotime($n['fecha'])),
                            'hora' => $n['hora']
                        );
                        $num++;
                     }
                  }
                  
                  $num++;
               }
            }
         }
         $this->cache->set('factura_cliente_huecos', $huecolist, 3600, TRUE);
      }
      return $huecolist;
   }
}
