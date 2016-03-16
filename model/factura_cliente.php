<?php
/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2013-2016  Carlos Garcia Gomez  neorazorx@gmail.com
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

require_model('asiento.php');
require_model('ejercicio.php');
require_model('linea_iva_factura_cliente.php');
require_model('linea_factura_cliente.php');
require_model('secuencia.php');
require_model('serie.php');

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
    * ID del asiento relacionado, si lo hay.
    * @var type 
    */
   public $idasiento;
   
   /**
    * ID del asiento de pago relacionado, si lo hay.
    * @var type 
    */
   public $idasientop;
   
   /**
    * ID de la factura que rectifica.
    * @var type 
    */
   public $idfacturarect;
   
   /**
    * Código único de la factura. Para humanos.
    * @var type 
    */
   public $codigo;
   
   /**
    * Número de la factura.
    * Único dentro de la serie+ejercicio.
    * @var type 
    */
   public $numero;
   
   /**
    * Número opcional a disposición del usuario.
    * @var type 
    */
   public $numero2;
   
   /**
    * Código de la factura que rectifica.
    * @var type 
    */
   public $codigorect;
   
   /**
    * Ejercicio relacionado. El que corresponde a la fecha.
    * @var type 
    */
   public $codejercicio;
   
   /**
    * Serie relacionada.
    * @var type 
    */
   public $codserie;
   
   /**
    * Almacén del que sale la mercancía.
    * @var type 
    */
   public $codalmacen;
   
   /**
    * Forma de pago.
    * @var type 
    */
   public $codpago;
   
   /**
    * Divisa de la factura.
    * @var type 
    */
   public $coddivisa;
   public $fecha;
   public $hora;
   
   /**
    * Código identificador del cliente de la factura.
    * @var type 
    */
   public $codcliente;
   public $nombrecliente;
   public $cifnif;
   public $direccion;
   public $ciudad;
   public $provincia;
   public $apartado;
   
   /// datos de transporte
   public $envio_codtrans;
   public $envio_codigo;
   public $envio_nombre;
   public $envio_apellidos;
   public $envio_direccion;
   public $envio_codpostal;
   public $envio_ciudad;
   public $envio_provincia;
   
   /**
    * ID de la dirección en dirclientes.
    * Modelo direccion_cliente.
    * @var type 
    */
   public $coddir;
   
   public $codpostal;
   public $codpais;
   
   /**
    * Empleado que ha creado la factura.
    * Modelo agente.
    * @var type 
    */
   public $codagente;
   
   /**
    * Suma de los pvptotal de las líneas.
    * Es el total antes de impuestos.
    * @var type 
    */
   public $neto;
   
   /**
    * Suma total del IVA de las líneas.
    * @var type 
    */
   public $totaliva;
   
   /**
    * Suma total de la factura, con impuestos.
    * @var type 
    */
   public $total;
   
   /**
    * totaleuros = total*tasaconv
    * Esto es para dar compatibilidad a Eneboo. Fuera de eso, no tiene sentido.
    * Ni siquiera hace falta rellenarlo, al hacer save() se calcula el valor.
    * @var type 
    */
   private $totaleuros;
   
   /**
    * % de retención IRPF de la factura.
    * Puede variar en cada línea.
    * @var type 
    */
   public $irpf;
   
   /**
    * Suma total de retenciones IRPF de las líneas.
    * @var type 
    */
   public $totalirpf;
   
   /**
    * % comisión del empleado (agente).
    * @var type 
    */
   public $porcomision;
   
   /**
    * Tasa de conversión a Euros de la divisa de la factura.
    * @var type 
    */
   public $tasaconv;
   
   /**
    * Suma del recargo de equivalencia de las líneas.
    * @var type 
    */
   public $totalrecargo;
   
   public $observaciones;
   public $pagada;
   public $anulada;
   
   /**
    * Fecha de vencimiento de la factura.
    * @var type 
    */
   public $vencimiento;
   
   /**
    * Fecha en la que se envió la factura por email.
    * @var type 
    */
   public $femail;
   
   public function __construct($f=FALSE)
   {
      parent::__construct('facturascli');
      if($f)
      {
         $this->idfactura = $this->intval($f['idfactura']);
         $this->idasiento = $this->intval($f['idasiento']);
         $this->idasientop = $this->intval($f['idasientop']);
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
            $this->hora = date('H:i:s', strtotime($f['hora']));
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
         $this->totalrecargo = floatval($f['totalrecargo']);
         $this->observaciones = $this->no_html($f['observaciones']);
         $this->pagada = $this->str2bool($f['pagada']);
         $this->anulada = $this->str2bool($f['anulada']);
         
         $this->vencimiento = Date('d-m-Y', strtotime($f['fecha'].' +1month'));
         if( !is_null($f['vencimiento']) )
         {
            $this->vencimiento = Date('d-m-Y', strtotime($f['vencimiento']));
         }
         
         $this->femail = NULL;
         if( !is_null($f['femail']) )
         {
            $this->femail = Date('d-m-Y', strtotime($f['femail']));
         }
         
         $this->envio_codtrans = $f['codtrans'];
         $this->envio_codigo = $f['codigoenv'];
         $this->envio_nombre = $f['nombreenv'];
         $this->envio_apellidos = $f['apellidosenv'];
         $this->envio_direccion = $f['direccionenv'];
         $this->envio_codpostal = $f['codpostalenv'];
         $this->envio_ciudad = $f['ciudadenv'];
         $this->envio_provincia = $f['provinciaenv'];
      }
      else
      {
         $this->idfactura = NULL;
         $this->idasiento = NULL;
         $this->idasientop = NULL;
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
         $this->totalrecargo = 0;
         $this->observaciones = NULL;
         $this->pagada = FALSE;
         $this->anulada = FALSE;
         $this->vencimiento = Date('d-m-Y', strtotime('+1month'));
         $this->femail = NULL;
         $this->envio_codtrans = NULL;
         $this->envio_codigo = NULL;
         $this->envio_nombre = NULL;
         $this->envio_apellidos = NULL;
         $this->envio_direccion = NULL;
         $this->envio_codpostal = NULL;
         $this->envio_ciudad = NULL;
         $this->envio_provincia = NULL;
      }
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
   
   /**
    * Establece la fecha y la hora, pero respetando la numeración.
    * Devuelve TRUE si se asigna una fecha distinta.
    * @param type $fecha
    * @param type $hora
    * @return boolean
    */
   public function set_fecha_hora($fecha, $hora)
   {
      $cambio = FALSE;
      
      if( is_null($this->numero) )
      {
         /// buscamos la última fecha usada en una factura en esta serie y ejercicio
         $sql = "SELECT MAX(fecha) as fecha FROM ".$this->table_name
                 . " WHERE codserie = ".$this->var2str($this->codserie)
                 . " AND codejercicio = ".$this->var2str($this->codejercicio).";";
         
         $data = $this->db->select($sql);
         if($data)
         {
            if( strtotime($data[0]['fecha']) > strtotime($fecha) )
            {
               $fecha = date('d-m-Y', strtotime($data[0]['fecha']));
               $cambio = TRUE;
            }
         }
         
         /// ahora buscamos la última hora usada para esa fecha, serie y ejercicio
         $sql = "SELECT MAX(hora) as hora FROM ".$this->table_name
                 . " WHERE codserie = ".$this->var2str($this->codserie)
                 . " AND codejercicio = ".$this->var2str($this->codejercicio)
                 . " AND fecha = ".$this->var2str($fecha).";";
         
         $data = $this->db->select($sql);
         if($data)
         {
            if( strtotime($data[0]['hora']) > strtotime($hora) OR $cambio )
            {
               $hora = date('H:i:s', strtotime($data[0]['hora']));
            }
         }
      }
      
      $this->fecha = $fecha;
      $this->hora = $hora;
      
      return $cambio;
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
   
   public function asiento_pago_url()
   {
      if( is_null($this->idasientop) )
      {
         return 'index.php?page=contabilidad_asientos';
      }
      else
         return 'index.php?page=contabilidad_asiento&id='.$this->idasientop;
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
   
   public function get_asiento_pago()
   {
      $asiento = new asiento();
      return $asiento->get($this->idasientop);
   }
   
   /**
    * Devulve las líneas de la factura.
    * @return linea_factura_cliente
    */
   public function get_lineas()
   {
      $linea = new linea_factura_cliente();
      return $linea->all_from_factura($this->idfactura);
   }
   
   /**
    * Devuelve las líneas de IVA de la factura.
    * Si no hay, las crea.
    * @return \linea_iva_factura_cliente
    */
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
                  if($l->iva == $lineasi[$i]->iva AND $l->recargo == $lineasi[$i]->recargo)
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
   
   /**
    * Devuelve un array con todas las facturas rectificativas de esta factura.
    * @return \factura_cliente
    */
   public function get_rectificativas()
   {
      $devoluciones = array();
      
      $data = $this->db->select("SELECT * FROM ".$this->table_name." WHERE idfacturarect = ".$this->var2str($this->idfactura).";");
      if($data)
      {
         foreach($data as $d)
         {
            $devoluciones[] = new factura_cliente($d);
         }
      }
      
      return $devoluciones;
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
   
   public function get_by_num_serie($num, $serie, $eje)
   {
      $sql = "SELECT * FROM ".$this->table_name." WHERE numero = ".$this->var2str($num)
              ." AND codserie = ".$this->var2str($serie)
              ." AND codejercicio = ".$this->var2str($eje).";";
      
      $fact = $this->db->select($sql);
      if($fact)
      {
         return new factura_cliente($fact[0]);
      }
      else
         return FALSE;
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
      /// buscamos el número inicial para la serie
      $num = 1;
      $serie0 = new serie();
      $serie = $serie0->get($this->codserie);
      if($serie)
      {
         /// ¿Se ha definido un nº de factura inicial para esta serie y ejercicio?
         if($this->codejercicio == $serie->codejercicio)
         {
            $num = $serie->numfactura;
         }
      }
      
      /// buscamos un hueco
      $encontrado = FALSE;
      $fecha = $this->fecha;
      $hora = $this->hora;
      $data = $this->db->select("SELECT ".$this->db->sql_to_int('numero')." as numero,fecha,hora
         FROM ".$this->table_name." WHERE codejercicio = ".$this->var2str($this->codejercicio).
         " AND codserie = ".$this->var2str($this->codserie)." ORDER BY numero ASC;");
      if($data)
      {
         foreach($data as $d)
         {
            if( intval($d['numero']) < $num )
            {
               /**
                * El número de la factura es menor que el inicial.
                * El usuario ha cambiado el número inicial después de hacer
                * facturas.
                */
            }
            else if( intval($d['numero']) == $num )
            {
               /// el número es correcto, avanzamos
               $num++;
            }
            else
            {
               /// Hemos encontrado un hueco y debemos usar el número y la fecha.
               $encontrado = TRUE;
               $fecha = Date('d-m-Y', strtotime($d['fecha']));
               $hora = Date('H:i:s', strtotime($d['hora']));
               break;
            }
         }
      }
      
      if($encontrado)
      {
         $this->numero = $num;
         $this->fecha = $fecha;
         $this->hora = $hora;
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
         $fac0 = $this->get_by_num_serie($numero0, $this->codserie, $this->codejercicio);
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
      $fac2 = $this->get_by_num_serie($numero2, $this->codserie, $this->codejercicio);
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
         {
            $status = FALSE;
         }
         
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
      if( !$linea_iva->factura_test($this->idfactura, $neto, $iva, $recargo) )
      {
         $status = FALSE;
      }
      
      /// comprobamos el asiento
      if( isset($this->idasiento) )
      {
         $asiento = $this->get_asiento();
         if($asiento)
         {
            if($asiento->tipodocumento != 'Factura de cliente' OR $asiento->documento != $this->codigo)
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
         $data = $this->db->select("SELECT * FROM ".$this->table_name." WHERE fecha = ".$this->var2str($this->fecha)."
            AND codcliente = ".$this->var2str($this->codcliente)." AND total = ".$this->var2str($this->total)."
            AND observaciones = ".$this->var2str($this->observaciones)." AND idfactura != ".$this->var2str($this->idfactura).";");
         if($data)
         {
            foreach($data as $fac)
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
            $sql = "UPDATE ".$this->table_name." SET idasiento = ".$this->var2str($this->idasiento).
                    ", idasientop = ".$this->var2str($this->idasientop).
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
                    ", totalrecargo = ".$this->var2str($this->totalrecargo).
                    ", observaciones = ".$this->var2str($this->observaciones).
                    ", pagada = ".$this->var2str($this->pagada).
                    ", anulada = ".$this->var2str($this->anulada).
                    ", hora = ".$this->var2str($this->hora).
                    ", vencimiento = ".$this->var2str($this->vencimiento).
                    ", femail = ".$this->var2str($this->femail).
                    ", codtrans = ".$this->var2str($this->envio_codtrans).
                    ", codigoenv = ".$this->var2str($this->envio_codigo).
                    ", nombreenv = ".$this->var2str($this->envio_nombre).
                    ", apellidosenv = ".$this->var2str($this->envio_apellidos).
                    ", direccionenv = ".$this->var2str($this->envio_direccion).
                    ", codpostalenv = ".$this->var2str($this->envio_codpostal).
                    ", ciudadenv = ".$this->var2str($this->envio_ciudad).
                    ", provinciaenv = ".$this->var2str($this->envio_provincia).
                    "  WHERE idfactura = ".$this->var2str($this->idfactura).";";
            
            return $this->db->exec($sql);
         }
         else
         {
            $this->new_codigo();
            $sql = "INSERT INTO ".$this->table_name." (idasiento,idasientop,idfacturarect,codigo,numero,
               codigorect,codejercicio,codserie,codalmacen,codpago,coddivisa,fecha,codcliente,
               nombrecliente,cifnif,direccion,ciudad,provincia,apartado,coddir,codpostal,codpais,
               codagente,neto,totaliva,total,totaleuros,irpf,totalirpf,porcomision,tasaconv,
               totalrecargo,pagada,anulada,observaciones,hora,numero2,vencimiento,femail,codtrans,
               codigoenv,nombreenv,apellidosenv,direccionenv,codpostalenv,ciudadenv,provinciaenv) VALUES 
                     (".$this->var2str($this->idasiento).
                    ",".$this->var2str($this->idasientop).
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
                    ",".$this->var2str($this->totalrecargo).
                    ",".$this->var2str($this->pagada).
                    ",".$this->var2str($this->anulada).
                    ",".$this->var2str($this->observaciones).
                    ",".$this->var2str($this->hora).
                    ",".$this->var2str($this->numero2).
                    ",".$this->var2str($this->vencimiento).
                    ",".$this->var2str($this->femail).
                    ",".$this->var2str($this->envio_codtrans).
                    ",".$this->var2str($this->envio_codigo).
                    ",".$this->var2str($this->envio_nombre).
                    ",".$this->var2str($this->envio_apellidos).
                    ",".$this->var2str($this->envio_direccion).
                    ",".$this->var2str($this->envio_codpostal).
                    ",".$this->var2str($this->envio_ciudad).
                    ",".$this->var2str($this->envio_provincia).");";
            
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
             * Delegamos la eliminación de los asientos en la clase correspondiente.
             */
            $asiento = new asiento();
            $asi0 = $asiento->get($this->idasiento);
            if($asi0)
            {
               $asi0->delete();
            }
            
            $asi1 = $asiento->get($this->idasientop);
            if($asi1)
            {
               $asi1->delete();
            }
         }
         
         /// desvinculamos el/los albaranes asociados
         $this->db->exec("UPDATE albaranescli SET idfactura = NULL, ptefactura = TRUE WHERE idfactura = "
                 .$this->var2str($this->idfactura).";");
         
         return TRUE;
      }
      else
         return FALSE;
   }
   
   private function clean_cache()
   {
      $this->cache->delete('factura_cliente_huecos');
   }
   
   /**
    * Devuelve un array con las últimas facturas (con el orden por defecto).
    * Si alteras el orden puedes obtener lo que desees.
    * @param type $offset
    * @param type $limit
    * @param type $order
    * @return \factura_cliente
    */
   public function all($offset=0, $limit=FS_ITEM_LIMIT, $order='fecha DESC, codigo DESC')
   {
      $faclist = array();
      
      $data = $this->db->select_limit("SELECT * FROM ".$this->table_name." ORDER BY ".$order, $limit, $offset);
      if($data)
      {
         foreach($data as $f)
         {
            $faclist[] = new factura_cliente($f);
         }
      }
      
      return $faclist;
   }
   
   /**
    * Devuelve un array con las facturas sin pagar
    * @param type $offset
    * @param type $limit
    * @param type $order
    * @return \factura_cliente
    */
   public function all_sin_pagar($offset=0, $limit=FS_ITEM_LIMIT, $order='vencimiento ASC, codigo ASC')
   {
      $faclist = array();
      $sql = "SELECT * FROM ".$this->table_name." WHERE pagada = false ORDER BY ".$order;
      
      $data = $this->db->select_limit($sql, $limit, $offset);
      if($data)
      {
         foreach($data as $f)
         {
            $faclist[] = new factura_cliente($f);
         }
      }
      
      return $faclist;
   }
   
   /**
    * Devuelve un array con las facturas del agente/empleado
    * @param type $codagente
    * @param type $offset
    * @return \factura_cliente
    */
   public function all_from_agente($codagente, $offset=0)
   {
      $faclist = array();
      $sql = "SELECT * FROM ".$this->table_name.
         " WHERE codagente = ".$this->var2str($codagente).
         " ORDER BY fecha DESC, codigo DESC";
      
      $data = $this->db->select_limit($sql, FS_ITEM_LIMIT, $offset);
      if($data)
      {
         foreach($data as $f)
         {
            $faclist[] = new factura_cliente($f);
         }
      }
      
      return $faclist;
   }
   
   /**
    * Devuelve un array con las facturas del cliente $codcliente
    * @param type $codcliente
    * @param type $offset
    * @return \factura_cliente
    */
   public function all_from_cliente($codcliente, $offset=0)
   {
      $faclist = array();
      $sql = "SELECT * FROM ".$this->table_name.
         " WHERE codcliente = ".$this->var2str($codcliente).
         " ORDER BY fecha DESC, codigo DESC";
      
      $data = $this->db->select_limit($sql, FS_ITEM_LIMIT, $offset);
      if($data)
      {
         foreach($data as $f)
         {
            $faclist[] = new factura_cliente($f);
         }
      }
      
      return $faclist;
   }
   
   /**
    * Devuelve un array con las facturas comprendidas entre $desde y $hasta
    * @param type $desde
    * @param type $hasta
    * @param type $codserie
    * @param type $codagente
    * @param type $codcliente
    * @param type $estado
    * @return \factura_cliente
    */
   public function all_desde($desde, $hasta, $codserie=FALSE, $codagente=FALSE, $codcliente=FALSE, $estado=FALSE)
   {
      $faclist = array();
      $sql = "SELECT * FROM ".$this->table_name." WHERE fecha >= ".$this->var2str($desde)." AND fecha <= ".$this->var2str($hasta);
      if($codserie)
      {
         $sql .= " AND codserie = ".$this->var2str($codserie);
      }
      if($codagente)
      {
         $sql .= " AND codagente = ".$this->var2str($codagente);
      }
      if($codcliente)
      {
         $sql .= " AND codcliente = ".$this->var2str($codcliente);
      }
      if($estado)
      {
         if($estado == 'pagada')
         {
            $sql .= " AND pagada";
         }
         else
         {
            $sql .= " AND pagada = false";
         }
      }
      $sql .= " ORDER BY fecha ASC, codigo ASC;";
      
      $data = $this->db->select($sql);
      if($data)
      {
         foreach($data as $f)
         {
            $faclist[] = new factura_cliente($f);
         }
      }
      
      return $faclist;
   }
   
   /**
    * Devuelve un array con las facturas que coinciden con $query
    * @param type $query
    * @param type $offset
    * @return \factura_cliente
    */
   public function search($query, $offset=0)
   {
      $faclist = array();
      $query = strtolower( $this->no_html($query) );
      
      $consulta = "SELECT * FROM ".$this->table_name." WHERE ";
      if( is_numeric($query) )
      {
         $consulta .= "codigo LIKE '%".$query."%' OR numero2 LIKE '%".$query."%' OR observaciones LIKE '%".$query."%'";
      }
      else
      {
         $consulta .= "lower(codigo) LIKE '%".$query."%' OR lower(numero2) LIKE '%".$query."%' "
                 . "OR lower(observaciones) LIKE '%".str_replace(' ', '%', $query)."%'";
      }
      $consulta .= " ORDER BY fecha DESC, codigo DESC";
      
      $data = $this->db->select_limit($consulta, FS_ITEM_LIMIT, $offset);
      if($data)
      {
         foreach($data as $f)
         {
            $faclist[] = new factura_cliente($f);
         }
      }
      
      return $faclist;
   }
   
   /**
    * Devuelve un array con las facturas del cliente $codcliente que coinciden con $query
    * @param type $codcliente
    * @param type $desde
    * @param type $hasta
    * @param type $serie
    * @param type $obs
    * @return \factura_cliente
    */
   public function search_from_cliente($codcliente, $desde, $hasta, $serie, $obs='')
   {
      $faclist = array();
      $sql = "SELECT * FROM ".$this->table_name." WHERE codcliente = ".$this->var2str($codcliente).
         " AND fecha BETWEEN ".$this->var2str($desde)." AND ".$this->var2str($hasta).
         " AND codserie = ".$this->var2str($serie);
      
      if($obs != '')
      {
         $sql .= " AND lower(observaciones) = ".$this->var2str(strtolower($obs));
      }
      
      $sql .= " ORDER BY fecha DESC, codigo DESC;";
      
      $data = $this->db->select($sql);
      if($data)
      {
         foreach($data as $f)
         {
            $faclist[] = new factura_cliente($f);
         }
      }
      
      return $faclist;
   }
   
   /**
    * Devuelve un array con los huecos en la numeración.
    * @return type
    */
   public function huecos()
   {
      $error = TRUE;
      $huecolist = $this->cache->get_array2('factura_cliente_huecos', $error);
      if($error)
      {
         $ejercicio = new ejercicio();
         $serie = new serie();
         foreach($ejercicio->all_abiertos() as $eje)
         {
            $codserie = '';
            $num = 1;
            $data = $this->db->select("SELECT codserie,".$this->db->sql_to_int('numero')." as numero,fecha,hora
               FROM ".$this->table_name." WHERE codejercicio = ".$this->var2str($eje->codejercicio).
               " ORDER BY codserie ASC, numero ASC;");
            if($data)
            {
               foreach($data as $d)
               {
                  if($d['codserie'] != $codserie)
                  {
                     $codserie = $d['codserie'];
                     $num = 1;
                     
                     $se = $serie->get($codserie);
                     if($se)
                     {
                        /// ¿Se ha definido un nº inicial de factura para esta serie y ejercicio?
                        if($eje->codejercicio == $se->codejercicio)
                        {
                           $num = $se->numfactura;
                        }
                     }
                  }
                  
                  if( intval($d['numero']) < $num )
                  {
                     /**
                      * El número de la factura es menor que el inicial.
                      * El usuario ha cambiado el número inicial después de hacer
                      * facturas.
                      */
                  }
                  else if( intval($d['numero']) == $num )
                  {
                     /// el número es correcto, avanzamos
                     $num++;
                  }
                  else
                  {
                     /**
                      * Hemos encontrado un hueco y debemos usar el número y la fecha.
                      * La variable pasos permite dejar de añadir huecos al llegar a 100,
                      * así evitamos agotar la memoria en caso de error grave.
                      */
                     $pasos = 0;
                     while($num < intval($d['numero']) AND $pasos < 100)
                     {
                        $huecolist[] = array(
                            'codigo' => $eje->codejercicio . sprintf('%02s', $codserie) . sprintf('%06s', $num),
                            'fecha' => Date('d-m-Y', strtotime($d['fecha'])),
                            'hora' => $d['hora']
                        );
                        $num++;
                        $pasos++;
                     }
                     
                     /// avanzamos uno más
                     $num++;
                  }
               }
            }
         }
         
         $this->cache->set('factura_cliente_huecos', $huecolist, 3600);
      }
      
      return $huecolist;
   }
}
