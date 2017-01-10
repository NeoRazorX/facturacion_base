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

require_model('articulo.php');
require_model('asiento.php');
require_model('caja.php');
require_model('divisa.php');
require_model('ejercicio.php');
require_model('impuesto.php');
require_model('subcuenta.php');
require_model('factura_proveedor.php');
require_model('pago_por_caja.php');
require_model('partida.php');
require_once 'plugins/facturacion_base/extras/libromayor.php';


class contabilidad_asiento extends fs_controller
{
   public $allow_delete;

    /**
     * @var asiento
     */
   public $asiento;
   public $divisa;
   public $ejercicio;
   public $impuesto;
   public $lineas;
   public $resultados;   
   public $subcuenta;
   public $resultados1;
   public $factura_prov;
   public $suma_debe;
   public $suma_haber;
   public $saldo;
   public $alias;
   public $resu;
   public $solapa;

    /**
     * @var array
     */
   public static $partidas = array();
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Asiento', 'contabilidad', FALSE, FALSE);
   }
   
   protected function process()
   {
      $this->asiento = FALSE;
      $this->ppage = $this->page->get('contabilidad_asientos');
      $this->divisa = new divisa();
      $this->ejercicio = new ejercicio();
      $this->impuesto = new impuesto();
      $this->subcuenta = new subcuenta();	  
	  	//// regreso de asiento a la solapa correspondiente
				  if( isset($_GET['solapa']) )
				  	{
						  if($_GET['solapa']=='may') $this->solapa='&mayorizados=TRUE';						  
						  if($_GET['solapa']=='des') $this->solapa='&descuadrados=TRUE';
						  if($_GET['solapa']=='all') $this->solapa='';
					}	
				///////////////////////////////////////	  
      
      /// ¿El usuario tiene permiso para eliminar en esta página?
      $this->allow_delete = $this->user->allow_delete_on(__CLASS__);
      $asiento = new asiento();
      if( isset($_GET['id']) )
      {         
         $this->asiento = $asiento->get($_GET['id']);
      }

/*	 			 print '<script language="JavaScript">'; 
				print 'alert(" id partida : '.$this->asiento->codejercicio.'  id asiento '.$this->asiento->idasiento.' ");'; 
				print '</script>'; 
*/	if( isset($this->asiento->tipodocumento))
	{			
	  if($this->asiento->tipodocumento=='Ingreso proveedor')
	  {
	  $this->factura_prov = new factura_proveedor();
	  $this->factura_prov->codejercicio=$this->asiento->codejercicio;
	  $this->factura_prov->idasiento=$this->asiento->idasiento;
	  $this->resultados1 = $this->factura_prov->facturas_proveedor();
	  }
	  else if($this->asiento->tipodocumento=='Ingreso')
	  {
	  $this->factura_cli = new factura_cliente();
	  $this->factura_cli->codejercicio=$this->asiento->codejercicio;
	  $this->factura_cli->idasiento=$this->asiento->idasiento;
	  $this->resultados1 = $this->factura_cli->facturas_cliente();
	  
	  }
    } 
	
	if(isset($_GET['genlibro']))
	if( $_GET['genlibro'] == 1 )
	{
			$idasiento = $_GET['idasiento'];
			$this->asiento->mayorizado = 1;
			$this->asiento->editable = 0;
			$may_corr = 0;
			$may_inco = 1;
			if( $this->asiento->save())
		 	{
			$libro_mayor = new libro_mayor();
			$partida = new partida();
			$asiento_all = new asiento();	
			$asientos_ejer = $asiento_all->all_por_ejercicio($this->asiento->codejercicio);
			foreach($asientos_ejer as $ext)
						{
			// suma por sub cuenta
			//  SELECT codsubcuenta,sum(`debe`),sum(`haber`) FROM `co_partidas` WHERE `libromayor`=1 group by codsubcuenta			
				//		partida
				 	$libro_mes = substr( $ext->fecha,3,2);
					if( $partida->marca_libro_idasiento($ext->idasiento,$libro_mes,$this->asiento->codejercicio))
					{
					$may_corr = 1;
					}
					else 	$may_inco = 0;	
						}
			}	
			
			if( $may_corr * $may_inco == 1) $this->new_message('Mayorizado correcto.');
			else $this->new_message('Imposible Mayorizar.');
	}
	
	
////////	
	if(isset($_GET['anu_may']))
	{
	 
			$idasiento = $_GET['anu_may'];

			$may_corr = 0;
			$may_inco = 1;
			
			$libro_mayor = new libro_mayor();
			$partida = new partida();
			$asiento_all = new asiento();	
			$asientos_ejer = $asiento_all->all_por_ejercicio($this->asiento->codejercicio);


			foreach($asientos_ejer as $ext)
				{
							$libro_mes = substr( $ext->fecha,3,2);
							if( $partida->marca_libro_idasiento($idasiento,'0','0'))
							{
							$may_corr = 1;
							}
							else 	$may_inco = 0;	
				}
				
			
			
			
			if( $may_corr * $may_inco == 1)
			{ 
			$this->asiento->mayorizado = 0;
			$this->asiento->editable = 1;
			if( $this->asiento->elimina_mayor()) $this->new_message('Mayorizado Anulado.');
			}
			else 
			{
			$this->asiento->mayorizado = 1;
			$this->asiento->editable = 0;
			$this->asiento->save();
			$this->new_message('Imposible Anular Mayorizado.');
			}
	}



/////////// 
    if( isset($_POST['fecha']) AND isset($_POST['query']) )
      {
         $this->new_search();
      }
    else if($this->asiento)
      {
         	$this->page->title = 'Asiento: '.$this->asiento->numero;
         		//////
			 if( isset($_GET['bloquear']) )
			 {
				$this->asiento->editable = FALSE;
				if( $this->asiento->bloquear_on_off() )
				{
				   $this->new_message('Asiento bloqueado correctamente.');
				}
				else $this->new_error_msg('Imposible bloquear el asiento.');
			 }
			 else if( isset($_GET['desbloquear']) )
			 {
				$this->asiento->editable = TRUE;
				if( $this->asiento->bloquear_on_off() )
				{
				   $this->new_message('Asiento desbloqueado correctamente.');
				}
				else $this->new_error_msg('Imposible desbloquear el asiento.');
			 }
         		////
			 if( isset($_POST['fecha']) AND $this->asiento->editable )
			 {
				$this->modificar();
			 }
         
			 /// comprobamos el asiento
			 $this->asiento->full_test();
			 
			 $this->lineas = $this->get_lineas_asiento();
			 $partida = new partida();
			 $valores=$partida->totales_from_asiento($this->asiento->idasiento);
			 $this->suma_debe = $valores['debe'];
			 $this->suma_haber = $valores['haber'];
			 $this->saldo = $valores['saldo'];
	//		 $this->comprobante = $valores['comprobante'];
	//		 $this->referencia = $valores['referencia'];
      }
	  
      else $this->new_error_msg("Asiento no encontrado.");
   }
   
   public function url()
   {
      if( !isset($this->asiento) )
      {
         return parent::url();
      }
      else if($this->asiento)
      {
         return $this->asiento->url();
      }
      else
         return $this->ppage->url();
   }

   public function url_mayorizar()
   {
   return 'index.php?page=mayorizar_subc';
   }
   
       public function url_cambio()
   {
   return 'index.php?page=libro_mayor_generar';
   }
   
   private function new_search()
   {
      /// cambiamos la plantilla HTML
      $this->template = 'ajax/contabilidad_nuevo_asiento';
      
      $eje0 = $this->ejercicio->get_by_fecha($_POST['fecha']);
      if($eje0)
      {
         $this->resultados = $this->subcuenta->search_by_ejercicio($eje0->codejercicio, $this->query);
		 
      }
      else
      {
         $this->resultados = array();
		 
      }
   }
   
   private function modificar()
   {
      /// obtenemos el ejercicio para poder acotar la fecha
      $eje0 = $this->ejercicio->get($this->asiento->codejercicio);
      if($eje0)
      {
         $this->asiento->fecha = $eje0->get_best_fecha($_POST['fecha']);
      }
      else
         $this->new_error_msg('No se encuentra el ejercicio asociado al asiento.');
      
      $this->asiento->concepto = $_POST['concepto'];
	  $this->asiento->tipodocumento = $_POST['concepto'];
	  $this->asiento->cambio_concepto();
      $this->asiento->importe = floatval($_POST['importe']);
      
      /// obtenemos la divisa de las partidas
      $div0 = $this->divisa->get($_POST['divisa']);
      if($div0)
      {
         $this->save_coddivisa($div0->coddivisa);
      }
      
      if( !$eje0 OR !$div0 )
      {
         $this->new_error_msg('Imposible modificar el asiento.');
      }
      else if( $this->asiento->save() )
      {
         $continuar = TRUE;
         $numlineas = intval($_POST['numlineas']);
        //  $this->asiento->tipodocumento = $this->asiento->concepto;
         /// eliminamos las partidas que faltan
         foreach($this->asiento->get_partidas() as $pa)
         {
            $encontrada = FALSE;
            for($i = 1; $i <= $numlineas; $i++)
            {
               if( isset($_POST['idpartida_'.$i]) )
               {
                  if( intval($_POST['idpartida_'.$i]) == $pa->idpartida )
                  {
                     $encontrada = TRUE;
                     break;
                  }
               }
            }
            if( !$encontrada )
            {
               if( !$pa->delete() )
               {
                  $this->new_error_msg('Imposible eliminar la línea debe='.$pa->debe.' haber='.$pa->haber);
                  $continuar = FALSE;
                  break;
               }
            }
         }
         
         
		 /// añadimos y modificamos
         $npartida = new partida();
         for($i = 1; $i <= $numlineas; $i++)
         {
            if( isset($_POST['idpartida_'.$i]) )
            {
               if($_POST['idpartida_'.$i] == '-1')
               {
                  /// las nuevas líneas llevan idpartida = -1
                  $partida = new partida();
               }
               else
               {
                  $partida = $npartida->get( $_POST['idpartida_'.$i] );
                  if( !$partida )
                  {
                     $this->new_error_msg('Partida de '.$_POST['codsubcuenta_'.$i].' no encontrada.');
                     $continuar = FALSE;
                  }
               }
			   // borra la partida para poner nuevos valores
				$partida->delete();
               if($continuar)
               {
                  /// añadimos
                  $sub0 = $this->subcuenta->get_by_codigo($_POST['codsubcuenta_'.$i], $eje0->codejercicio);
                  if($sub0)
                  {
				  
                     $partida->idasiento = $this->asiento->idasiento;
                     $partida->coddivisa = $div0->coddivisa;
                     $partida->tasaconv = $div0->tasaconv;
                     $partida->idsubcuenta = $_POST['idsubcuenta_'.$i];
                     $partida->codsubcuenta = $_POST['codsubcuenta_'.$i];
                     $partida->debe = floatval($_POST['debe_'.$i]);
                     $partida->haber = floatval($_POST['haber_'.$i]);
                     $partida->idconcepto = $this->asiento->idconcepto;
                     $partida->concepto = $this->asiento->concepto;
                     $partida->documento = $this->asiento->documento;
                     $partida->tipodocumento = $this->asiento->tipodocumento;
					 $partida->comprobante = $_POST['comp_'.$i];
					 $partida->referencia = $_POST['ref_'.$i];
                     
                     if( isset($_POST['codcontrapartida_'.$i]) )
                     {
                        if( $_POST['codcontrapartida_'.$i] != '')
                        {
                           $subc1 = $this->subcuenta->get_by_codigo($_POST['codcontrapartida_'.$i], $eje0->codejercicio);
                           if($subc1)
                           {
                              $partida->idcontrapartida = $subc1->idsubcuenta;
                              $partida->codcontrapartida = $subc1->codsubcuenta;
                              $partida->cifnif = $_POST['cifnif_'.$i];
                              $partida->iva = floatval($_POST['iva_'.$i]);
                              $partida->baseimponible = floatval($_POST['baseimp_'.$i]);
                           }
                           else
                           {
                              $this->new_error_msg('Subcuenta '.$_POST['codcontrapartida_'.$i].' no encontrada.');
                              $continuar = FALSE;
                           }
                        }
                     }
                     
                     if( !$partida->save() )
                     {
                        $this->new_error_msg('Imposible guardar la partida de la subcuenta '.$_POST['codsubcuenta_'.$i].'.');
                        $continuar = FALSE;
                     }
                  }
                  else
                  {
                     $this->new_error_msg('Subcuenta '.$_POST['codsubcuenta_'.$i].' de la línea '.$i.' no encontrada.');
                     $continuar = FALSE;
                  }
               }
               else
                  break;
            }
         }
            // Después de que se hicieron todas las actualizaciones cargo las partidas existentes y actualizo
            // al array contabilidad_asiento::$partidas para que se actualicen con las generadas por las cajas
            contabilidad_asiento::cargar_partidas($this->asiento, $div0);
            if(isset($_POST['importar_caja']) &&
                filter_var($_POST['importar_caja'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true) {
                if($continuar) {
                    $continuar = contabilidad_asiento::importar_caja($this->asiento, $div0, $this);
                } else {
                    $this->new_error_msg("Hay algún error relacionado a este asiento, imposible importar cajas");
                }
            }

         if($continuar)
         {
            $this->new_message('Asiento modificado correctamente.');
            $this->new_change('Asiento '.$this->asiento->numero, $this->asiento->url());
         }
      }
      else
         $this->new_error_msg('Imposible modificar el asiento.');
   }
   
   private function get_lineas_asiento()
   {
      $lineas = $this->asiento->get_partidas();
      $subc = new subcuenta();
      
      foreach($lineas as $i => $lin)
      { 
         $subcuenta = $subc->get($lin->idsubcuenta);
         if($subcuenta)
         {
		 	$lineas[$i]->alias = $subcuenta->alias;
            $lineas[$i]->desc_subcuenta = $subcuenta->descripcion;
            $lineas[$i]->saldo = $subcuenta->saldo;
         }
      }
      
      return $lineas;
   }
   
             public function total_descuadrados()
   {
   		$asiento = new asiento();
      $data = $asiento->descuadrados();
      if($data)
      {
         return count($data);
      }
      else
         return 0;
   }

    /**
     * @param asiento $asiento
     * @param divisa $divisa
     */
    public static function cargar_partidas(asiento $asiento, divisa $divisa) {
        $partidas = $asiento->get_partidas();
        if(is_array($partidas) && !empty($partidas)) {
            foreach ($partidas as $partida) {
                self::add_partida($asiento, $divisa, $partida->codsubcuenta, array(
                    'debe' => $partida->debe,
                    'haber' => $partida->haber,
                    'comprobante' => $partida->comprobante
                ));
            }
        }
    }

    /**
     * @param asiento $asiento
     * @param divisa $divisa
     * @param fs_controller $controller
     *
     * @return bool Status de la importacion de las cajas
     */
    public static function importar_caja(asiento $asiento, divisa $divisa, fs_controller $controller) {
        $art = new articulo();
        $continuar = true;
        /** @var caja[] $cajas_importadas */
        $cajas_importadas = array();
        foreach ($_POST['cajas'] as $idcaja) {
            $caja = caja::get($idcaja);
            // Lo primero que tiene que haber en el asiento es una linea con el monto de la caja importada
            // Cuando importe muchas, lo que va a haber es una sola linea con el total de cajas
            contabilidad_asiento::add_partida($asiento, $divisa, '110101001', array(
                'debe' => $caja->dinero_fin,
                'haber' => 0,
                'comprobante' => '<a hreh="'.$caja->url() .'">Caja #' . $caja->id . '</a>,',
            ));
            foreach ($caja->get_recibos() as $recibo) {
                $factura = $recibo->getFactura();
                // Obtengo las lineas de la factura
                $lineas = $factura->get_lineas();

                // Después de acuerdo a la forma de pago del recibo tengo que agregar el pago a cada una de las cuentas
                switch ($recibo->codpago) {
                    case 'CONT':
                        // TODO: Cuando es de contado, a que cuenta va?
                        break;
                    case 'DBT MACRO':
                        contabilidad_asiento::add_partida($asiento, $divisa, '110103001', array(
                            'debe' => 0,
                            'haber' => $recibo->importe,
                            'comprobante' => ''
                        ));
                        break;
                    case 'DBT S. RIO':
                    case 'CTA CTE':
                        contabilidad_asiento::add_partida($asiento, $divisa, '110103006', array(
                            'debe' => 0,
                            'haber' => $recibo->importe,
                            'comprobante' => ''
                        ));
                        break;
                    case 'TRF':
                    case 'CHQ':
                    case 'DEP':
                        contabilidad_asiento::add_partida($asiento, $divisa, '110103003', array(
                            'debe' => 0,
                            'haber' => $recibo->importe,
                            'comprobante' => ''
                        ));
                        break;
                    case 'TC':
                    case 'TD':
                        contabilidad_asiento::add_partida($asiento, $divisa, '1102010000', array(
                            'debe' => 0,
                            'haber' => $recibo->importe,
                            'comprobante' => ''
                        ));
                        break;
                    default:
                        $controller->new_error_msg("La forma de pago no está configurada para ser importada!");
                        break;
                }

                // Después de eso viene la parte "compleja":
                // Si la factura del recibo está pagada
                if($factura->pagada) {
                    // El monto total de la factura es igual al del recibo?
                    if($recibo->importe === $factura->total) {
                        // tomamos todos los articulos y los importamos de acuerdo a la subcuenta de venta
                        // que está declarada en cada articulo (este es el caso "normal", como por ejemplo
                        // el de las COMIDAS_PERSONAL
                        foreach ($lineas as $linea) {
                            $articulo = $art->get($linea->referencia);
                            // Si no hay articulo tengo un grave problema
                            if($articulo && $articulo->codsubcuentaven) {
                                contabilidad_asiento::add_partida($asiento, $divisa, $articulo->codsubcuentaven, array(
                                    'debe' => 0,
                                    'haber' => $linea->pvptotal,
                                    'comprobante' => ''
                                ));
                            } else {
                                $controller->new_error_msg('La factura: <a href="'.$factura->url().'">#'.
                                    $factura->numero . '</a> tiene un un artículo inexistente: ' . $linea->referencia .
                                    'o el artículo no tiene configurada la cuenta a la que deve ser cargada'
                                );
                            }
                        }
                    } else {
                        // Si la factura está paga pero los totales difieren
                        // Eso quiere decir que los pagos están dispersos entre varias cajas, por lo que tengo que
                        // importar solamente el monto del recibo

                        // si el artículo que tiene soalemente es una reserva
                        if(count($lineas) === 1 && $lineas[0]->referencia === 'Reserva') {
                            // se agrega la partida a la subcuenta 210101003
                            contabilidad_asiento::add_partida($asiento, $divisa, '210101003', array(
                                'debe' => 0,
                                'haber' => $recibo->importe,
                                'comprobante' => ''
                            ));
                            contabilidad_asiento::add_partida($asiento, $divisa, '410101001', array(
                                'debe' => 0,
                                'haber' => $factura->total,
                                'comprobante' => ''
                            ));
                        } else {
                            // No se que hacer en estos casos
                            $controller->new_error_msg('La factura <a href="' . $factura->url() .'">#'. $factura->numero
                                . '</a> está paga  pero el importe está distribuido y contiene otros articulos que no'
                                . ', por lo que no puede ser importada en un asiento contable por favor ignore la caja #' . $caja->id);
                            $continuar = false;
                        }

                    }
                } else {
                    // Si la factura no está paga y el artículo es una reserva
                    // entonces los recibos van a la subcuenta 210101003
                    if(count($lineas) === 1 && $lineas[0]->referencia === 'Reserva') {
                        contabilidad_asiento::add_partida($asiento, $divisa, '210101003', array(
                            'debe' => 0,
                            'haber' => $recibo->importe,
                            'comprobante' => ''
                        ));
                    } else {
                        //No se que hacer en estos casos
                        $controller->new_error_msg('La factura <a href="' . $factura->url() .'">#'. $factura->numero
                            . '</a> no está paga y contiene otros articulos que no son una reserva, por lo que no '
                            . ' puede ser importada en un asiento contable  por favor ignore la caja #' . $caja->id);
                        $continuar = false;
                    }
                }
            }
            if($continuar) {
                $cajas_importadas[] = $caja;
            }
        }

        // Si todas las cajas fueron importadas correctamente
        if ($continuar) {
            // Guardo las partidas asociadas al asiento en la BBDD
            foreach(contabilidad_asiento::$partidas as $subcuenta => $partida) {
                // Creamos una nueva partida
                $part = new partida();
                // Cargamos los valores en la partida
                foreach ($partida as $name => $value) {
                    if (property_exists($part, $name)) {
                        $part->$name = $value;
                    }
                }
                $part->referencia = 'Caja importada el ' . date('Y-m-d H:i:s') . ' por ' . $controller->user->get_agente()->get_fullname();
                // Al guardar la partida se actualiza automáticamente los valores de la subcuenta
                // Y el monto del asiento
                if($part->save()) {
                    $continuar = $continuar && true;
                } else {
                    $continuar = false;
                }
            }

            if($continuar) {
                foreach ($cajas_importadas as $caja) {
                    $caja->setIdAsiento($asiento->idasiento);
                    if(!$caja->save()) {
                        $controller->new_error_msg("Error al actualizar la caja #" . $caja->id);
                        $continuar = $continuar && true;
                    } else {
                        $continuar = false;
                    }
                }
                if($continuar) {
                    $controller->new_message('Cajas importada correctamente');
                } else {
                    $controller->new_error_msg('Hubo algún error al vincular el asiento con las cajas');
                }
            } else {
                // Creo que acá tendría que hacer rollback de toda la transacción
                $controller->new_error_msg("Error al guardar una partida al asiento");
            }
        } else {
            $controller->new_error_msg("Error al importar cajas");
        }

        return $continuar;
    }

    /**
     * @param asiento $asiento
     * @param divisa $divisa
     * @param $codsubcuenta
     * @param array $datos
     */
    private static function add_partida(asiento $asiento, divisa $divisa, $codsubcuenta, array $datos) {
        $subcuenta = subcuenta::fetch($codsubcuenta, $asiento->codejercicio);
        if(!isset(contabilidad_asiento::$partidas[$codsubcuenta])) {
            //Si no está en la lista de partidas entonces agrego una partida con la informacion base
            contabilidad_asiento::$partidas[$codsubcuenta] = array(
                'idasiento' => $asiento->idasiento,
                'idsubcuenta' => $subcuenta->idsubcuenta,
                'codsubcuenta' => $codsubcuenta,
                'idconcepto' => $asiento->idconcepto,
                'concepto' => $asiento->concepto,
                'tasaconv' => $divisa->tasaconv,
                'coddivisa' => $divisa->coddivisa,
                'tipodocumento' => $asiento->tipodocumento,
                'documento' => $asiento->documento,
                'codejercicio' => $asiento->codejercicio,
                'debe' => (float) 0.0,
                'haber' => (float) 0.0,
                'comprobante' => '',
                'referencia' => ''
            );
        }

        //Agrego los valores en $datos a la partida
        contabilidad_asiento::$partidas[$codsubcuenta]['debe'] += (float) $datos['debe'];
        contabilidad_asiento::$partidas[$codsubcuenta]['haber'] += (float) $datos['haber'];
        contabilidad_asiento::$partidas[$codsubcuenta]['comprobante'] += $datos['comprobante'];
    }

}
