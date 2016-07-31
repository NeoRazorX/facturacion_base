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
require_model('empresa.php');
require_model('asiento.php');
require_model('asiento_factura.php');
require_model('ejercicio.php');
require_model('factura_proveedor.php');
require_model('forma_pago.php');
require_model('partida.php');
require_model('proveedor.php');
require_model('subcuenta.php');
require_model('recibo_proveedor.php');
require_model('fabricante.php');
require_model('familia.php');
require_model('articulo_proveedor.php');

class compras_factura extends fs_controller
{
   public $empresa;
   public $agente;
   public $allow_delete;
   public $ejercicio;
   public $factura;
   public $forma_pago;
   public $mostrar_boton_pagada;
   private $subcuenta_pro;
   public $factura_anulada;
   public $impuesto;
   public $serie;
   public $subcuentas;
   public $list_subcuen;
   public $fabricante;
   public $familia;
   public $proveedor_s;
   public $codserie;
   public $divisa;
   public $tasaconv;
   public $view_subcuen;
   public $view_subcuen_dev;  
   
   
   
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Factura de proveedor', 'compras', FALSE, FALSE);
   }
   
   protected function process()
   {
   	  $this->empresa = new empresa();
      $this->ppage = $this->page->get('compras_facturas');
      $this->agente = FALSE;
      $this->ejercicio = new ejercicio();
      $factura = new factura_proveedor();
      $this->factura = FALSE;
      $this->forma_pago = new forma_pago();
	  $this->impuesto = new impuesto();
	  $this->serie = new serie();
	  $this->divisa = new divisa();
	  $this->subcuentas = new subcuenta();
	  $this->proveedor_s = FALSE;
	  $this->proveedor = new proveedor();
	  $this->fabricante = new fabricante();
	  $this->familia = new familia();
	  $this->articulo_prov = new articulo_proveedor();
	  $this->artsubcuentas = new articulo();
	  $this->view_subcuen = $this->subcuentas->subcoenta_compras($this->empresa->codejercicio);
	  $this->view_subcuen_dev = $this->subcuentas->subcoenta_compras_credito($this->empresa->codejercicio);
	  
	  $this->list_subcuen = $this->subcuentas->subcoenta_compras($this->empresa->codejercicio);
	  ///   tecla anular factura
	  if( isset($_POST['id']))
	  {
	  $var_idpagodevol=$factura->get($_POST['id']);
	  $this->factura_anulada=$var_idpagodevol->idpagodevol;
   	  }
	  if( isset($_GET['id']))
	  {
	  $var_idpagodevol=$factura->get($_GET['id']);
	  $this->factura_anulada=$var_idpagodevol->idpagodevol;
   	  }
      /// ¿El usuario tiene permiso para eliminar en esta página?
      $this->allow_delete = $this->user->allow_delete_on(__CLASS__);

      /**
       * Si hay alguna extensión de tipo config y texto no_button_pagada,
       * desactivamos el botón de pagada/sin pagar.
       */
      $this->mostrar_boton_pagada = TRUE;
      foreach($this->extensions as $ext)
      {
         if($ext->type == 'config' AND $ext->text == 'no_button_pagada')
         {
            $this->mostrar_boton_pagada = FALSE;
            break;
         }
      }
      
      if( isset($_POST['idfactura']) )
      {
	  	$this->modifica_factura();

      }
      else if( isset($_GET['id']) )
      {	  
	  
         $this->factura = $factura->get($_GET['id']);
      }
	   
      if($this->factura)
      {
         $this->page->title = $this->factura->codigo;
         
         if( isset($_POST['gen_asiento']) AND isset($_POST['petid']) AND $_POST['gen_asiento'] == '1' )
         {
            if( $this->duplicated_petition($_POST['petid']) )
            {
               $this->new_error_msg('Petición duplicada. Evita hacer doble clic sobre los botones.');
            }
            else
 				{
				if( substr($this->factura->numproveedor, 0,1) == 'B' || substr($this->factura->numproveedor, 0,1) =='F' || substr($this->factura->numproveedor, 0,1) =='T' || substr($this->factura->numproveedor, 0,1) =='D' ) 
					$this->generar_asiento();
				else if ( substr($this->factura->numproveedor, 0,1) =='Q' || substr($this->factura->numproveedor, 0,1) =='C' )
					{			
					$this->generar_asiento_credito();			
					}
					else 
					{
						$asiento_credito = new asiento_factura();
						$asiento_credito->nuevo_asiento_devolucion_prov($_REQUEST['id']);
					}
				}	
         }
         else if( isset($_REQUEST['pagada']) )
         {
            $this->factura->pagada = ($_REQUEST['pagada'] == 'TRUE');
            if( $this->factura->save() )
            {
               $this->new_message("Factura modificada correctamente.");
            }
            else
               $this->new_error_msg("¡Imposible modificar la factura!");
         }
		 else if( isset($_REQUEST['gen_devolucion']) )
		 {
	
		 $this->nuevo_asiento_devolucion(); // Compras_factura

		 }
		 else if( isset($_REQUEST['reestab_anulada']) )
		 {
	
		 $this->restab_anulada(); // Compras_factura

		 }
		 
		 
         
         /// comprobamos la factura
 //        $this->factura->full_test();
         
         /// cargamos el agente
         if( !is_null($this->factura->codagente) )
         {
            $agente = new agente();
            $this->agente = $agente->get($this->factura->codagente);
         }
      }
      else
         $this->new_error_msg("¡Factura de proveedor no encontrada!");
   }
   
   public function url()
   {
      if( !isset($this->factura) )
      {
         return parent::url();
      }
      else if($this->factura)
      {
         return $this->factura->url();
      }
      else
         return $this->page->url();
   }
   
   public function modifica_factura()
   {
   		$continuar = TRUE;
   	  $factura = new factura_proveedor();
	  $fact_save=0;
	  
         $this->factura = $factura->get($_POST['idfactura']);
         $this->factura->numproveedor = $_POST['numproveedor'];
         $this->factura->observaciones = $_POST['observaciones'];
         $this->factura->codpago = $_POST['forma_pago'];
		 $this->factura->cai = $_POST['cai'];
		 $this->factura->caivence = $_POST['caivence'];
		 $this->factura->fecha = $_POST['fecha'];
		 $this->factura->hora = $_POST['hora'];
		 $this->factura->codpago = $_POST['forma_pago'];
		 $this->factura->cifnif = 0;
		 $this->factura->codserie = $_POST['codserie'];
		 $this->factura->coddivisa = $_POST['coddivisa'];
		 $this->factura->tasaconv = $_POST['tasaconv'];
		 
		$eje0 = $this->ejercicio->get_by_fecha($_POST['fecha']);
      if(  $eje0 )	  		
          $this->factura->hora = $_POST['hora'];
      else
      {
         $this->new_error_msg('Ejercicio no encontrado.');
         $continuar = FALSE;
      }
        

      if( $continuar == TRUE ) 
	  {
				 if( $this->factura->save() )
				 {
				  $fact_save=1;
				 $this->nueva_factura_proveedor($fact_save);
					$asiento = $this->factura->get_asiento();
					if($asiento)
					{
					   $asiento->fecha = $this->factura->fecha;
					   if( !$asiento->save() )
						  $this->new_error_msg("Imposible modificar la fecha del asiento.");
					}
					$this->new_message("Factura modificada correctamente.");
					$this->new_change('Factura Proveedor '.$this->factura->codigo, $this->factura->url());
				 }
				 else
					$this->new_error_msg("¡Imposible modificar la factura!");
		}
		else $this->new_error_msg("¡Imposible modificar la factura!");
   }

 //////////////////////////////////////////////////////////  
   //////////////////////////////////////////////////////////////////  
 ///////////////  Genera FACTURA  ORDEN DEBITO  ORDEN CREDITO
   private function nueva_factura_proveedor($fact_save)
   {
      $continuar = TRUE;
      
      $proveedor = $this->proveedor->get($_POST['proveedor']);
      if( $proveedor )
         $this->save_codproveedor( $proveedor->codproveedor );
      else
      {
         $this->new_error_msg('Proveedor no encontrado.');
         $continuar = FALSE;
      }
      if( isset($_POST['almacen']))
	  {
      $almacen = $this->almacen->get($_POST['almacen']);
      if( $almacen )
         $this->save_codalmacen( $almacen->codalmacen );
      else
      {
         $this->new_error_msg('Almacén no encontrado.');
         $continuar = FALSE;
      }
	  }
      
      $eje0 = new ejercicio();
      $ejercicio = $eje0->get_by_fecha($_POST['fecha']);
      if( $ejercicio )
         $this->save_codejercicio( $ejercicio->codejercicio );
      else
      {
         $this->new_error_msg('Ejercicio no encontrado.');
         $continuar = FALSE;
      }
      if( isset($_POST['serie']))
	  {
      $serie = $this->serie->get($_POST['serie']);
      if( !$serie )
      {
         $this->new_error_msg('Serie no encontrada.');
         $continuar = FALSE;
      }
	  }
      
      $forma_pago = $this->forma_pago->get($_POST['forma_pago']);
      if( $forma_pago )
         $this->save_codpago( $forma_pago->codpago );
      else
      {
         $this->new_error_msg('Forma de pago no encontrada.');
         $continuar = FALSE;
      }
      if( isset($_POST['divisa']))
	  {
      $divisa = $this->divisa->get($_POST['divisa']);
      if( $divisa )
         $this->save_coddivisa( $divisa->coddivisa );
      else
      {
         $this->new_error_msg('Divisa no encontrada.');
         $continuar = FALSE;
      }
	  }
      
      $factura = new factura_proveedor();
      

      
      if( $continuar )
      {
 /*        $factura->fecha = $_POST['fecha'];
         $factura->hora = $_POST['hora'];
         $factura->codproveedor = $proveedor->codproveedor;
         $factura->nombre = $proveedor->nombre;
		 $factura->idpagodevol=0;
         $factura->cifnif = $proveedor->cifnif;
         
         $factura->codejercicio = $ejercicio->codejercicio;
         $factura->codserie = $_POST['codserie'];
         $factura->codpago = $forma_pago->codpago;
         $factura->coddivisa = $_POST['coddivisa'];
         $factura->tasaconv = $factura->tasaconv;
		 $factura->tipo = $_POST['tipo'];
		 $factura->cai = $_POST['cai'];
         $factura->caivence = $_POST['caivence'];
*/		         

         
         if($forma_pago->genrecibos == 'Pagados')
         {
//            $factura->pagada = TRUE;
         }
         
         if( $fact_save == 1 )
         {
            $art0 = new articulo();
            $n = floatval($_POST['numlineas']);
			      $linea = new linea_factura_proveedor();
                  $linea->idfactura = $this->factura->idfactura;
				  $linea->delete_idfac();
						  
            for($i = 0; $i < $n; $i++)
            {
               if( isset($_POST['referencia_'.$i]) )
               {
					$linea->idfactura = $this->factura->idfactura;
					$linea->idlinea = '';
                  $linea->descripcion = $_POST['desc_'.$i];                     
                  $linea->irpf = floatval($_POST['irpf_'.$i]);
                  $linea->pvpunitario = floatval($_POST['pvp_'.$i]);
                  $linea->cantidad = floatval($_POST['cantidad_'.$i]);
                  $linea->dtopor = floatval($_POST['dto_'.$i]);
                  $linea->pvpsindto = ($linea->pvpunitario * $linea->cantidad);
                  $linea->pvptotal = floatval($_POST['neto_'.$i]);

				  $postot = strlen($_POST['subcuenta_'.$i]);				  
				  $poscad = strpos($_POST['subcuenta_'.$i], '/');
				  $posid = strpos($_POST['subcuenta_'.$i], '%');				  				  
				  $subcuencod = substr($_POST['subcuenta_'.$i], 0, $poscad);
				  $subcuendes = substr($_POST['subcuenta_'.$i],$poscad+1,$posid-$postot);
				  $idsubcuen = substr($_POST['subcuenta_'.$i],$posid+1);
	  
				  $linea->codsubcuenta = $subcuencod;				  				  
                  $linea->subcuentadesc = $subcuendes;
				  $linea->idsubcuenta = $idsubcuen;
				  
				  
				  
                  $articulo = $art0->get($_POST['referencia_'.$i]);
				  
				  ////////////////////////////////////////////////////////////////////////
				  ////  GUARDA subcuenta en articulo cuando se carga la factura
				  ////////////////////////////////////////////////////
				  $artval = $this->artsubcuentas->get_ref($_POST['referencia_'.$i]);


				  if($artval != $subcuencod || $subcuencod==NULL )
				  {
				  if($_POST['tipo'] == 'F' || $_POST['tipo'] == 'D' )
            		{				  
					$this->artsubcuentas->guarda_subcuenta_comp($_POST['referencia_'.$i],$subcuencod,$subcuendes);
					}
				   else $this->artsubcuentas->guarda_subcuenta_dev($_POST['referencia_'.$i],$subcuencod,$subcuendes);
					
					
				  }
				//////////////////////////////////////////////////////////////////
				////////////////////////////////////////////////////////////////
				
                  if($articulo)
                  {
                     $linea->referencia = $articulo->referencia;
                  }
                  
                  if( $linea->save() )
                  {
                     if($articulo)
                     {
                        if( isset($_POST['costemedio']) )
                        {
                           if($articulo->costemedio == 0)
                           {
                              $articulo->costemedio = $linea->pvptotal/$linea->cantidad;
                           }
                           else
                           {
                              $articulo->costemedio = $articulo->get_costemedio();
                              if($articulo->costemedio == 0)
                              {
                                 $articulo->costemedio = $linea->pvptotal/$linea->cantidad;
                              }
                           }
                           
                           $this->actualizar_precio_proveedor($factura->codproveedor, $linea);
                        }
                        
                        if( isset($_POST['stock']) )
                        {
                           $articulo->sum_stock($factura->codalmacen, $linea->cantidad);
                        }
                        else if( isset($_POST['costemedio']) )
                        {
                           $articulo->save();
                        }
                     }
                     
                     $factura->neto += $linea->pvptotal;
                     $factura->totaliva += ($linea->pvptotal * $linea->iva/100);
                     $factura->totalirpf += ($linea->pvptotal * $linea->irpf/100);
                     $factura->totalrecargo += ($linea->pvptotal * $linea->recargo/100);
                  }
                  else
                  {
                     $this->new_error_msg("¡Imposible guardar la linea con referencia: ".$linea->referencia);
                     $continuar = FALSE;
                  }
               }
            }
            
            if($continuar)
            {
               /// redondeamos
               $this->factura->neto = round($factura->neto, FS_NF0);
               $this->factura->totaliva = round($factura->totaliva, FS_NF0);
               $this->factura->totalirpf = round($factura->totalirpf, FS_NF0);
               $this->factura->totalrecargo = round($factura->totalrecargo, FS_NF0);
               $this->factura->total = $this->factura->neto + $this->factura->totaliva - $this->factura->totalirpf + $this->factura->totalrecargo;
               
               if( abs(floatval($_POST['atotal']) - $this->factura->total) >= .02 )
               {
                  $this->new_error_msg("El total difiere entre el controlador y la vista (".
                          $this->factura->total." frente a ".$_POST['atotal']."). Debes informar del error.");
                  $this->factura->delete();
               }
               else if( $this->factura->save() )
               {
			   
///////// GENERA  ASIENTO			   
 //                 $this->generar_asiento($factura);
                  $this->new_message("<a href='".$factura->url()."'>Factura</a> guardada correctamente.");
                  $this->new_change('Factura Proveedor '.$factura->codigo, $factura->url(), TRUE);
                  
                  if($_POST['redir'] == 'TRUE')
                  {
                     header('Location: '.$factura->url());
                  }
               }
               else
                  $this->new_error_msg("¡Imposible actualizar la <a href='".$factura->url()."'>factura</a>!");
            }
            else if( $factura->delete() )
            {
               $this->new_message("Factura eliminada correctamente.");
            }
            else
               $this->new_error_msg("¡Imposible eliminar la <a href='".$factura->url()."'>factura</a>!");
         }
         else
            $this->new_error_msg("¡Imposible guardar la factura!");
      }
	  
   } 
   
   
   
   
 /////////////////////////////////////////////////////////
 /////////  DEVOLUCIÓN
 //////////////////////////////////////////////////////
 
   private function nuevo_asiento_devolucion()
   {
   		$anular= new factura_proveedor();
		$orden = new orden_prov();
	  	$facturadev = new asiento_factura();
	  	if($facturadev->nuevo_asiento_devolucion_prov($_REQUEST['id']))
		$anular->boton_anular($_REQUEST['id']);

		$factura = new factura_proveedor();
	  	$var_idpagodevol=$factura->get($_GET['id']);
		// Dá el valor al botón
	  	$this->factura_anulada=$var_idpagodevol->idpagodevol;
		
		      foreach($orden->ultimovalor_orden_prov($var_idpagodevol->codproveedor) as $f){}
			  
 

//		$dif_importe = $f->importe - $var_idpagodevol->total;
//		$orden->nuevoimporte_orden($f->idorden,$dif_importe);

	}  	
 
 /////////////////////////////////////
 /////////////////////////////////////
 ////// Restablecer factura anulada
 	private function restab_anulada()
	{
	
	
	$anular= new factura_proveedor();
	$anular->boton_restab_anulada($_REQUEST['id']);
	
		$factura = new factura_proveedor();
	  	$var_idpagodevol=$factura->get($_REQUEST['id']);
 
		$this->factura_anulada=$var_idpagodevol->idpagodevol;
	}
   
   
   
   
   
   private function generar_asiento()
   {
   		// toma un asiento existente con su id
      if( $this->factura->get_asiento() ) //factura_proveedor
      {
         $this->new_error_msg('Ya hay un asiento asociado a esta factura.');
      }
      else
      {
         $asiento_factura = new asiento_factura();
         $asiento_factura->soloasiento = TRUE;
		 // Genera la partida que está en asiento_factura
         if( $asiento_factura->generar_asiento_compra($this->factura) )
         {
            $this->new_message("<a href='".$asiento_factura->asiento->url()."'>Asiento</a> generado correctamente.");
            $this->new_change('Factura Proveedor '.$this->factura->codigo, $this->factura->url());
         }
         
         foreach($asiento_factura->errors as $err)
         {
            $this->new_error_msg($err);
         }
         
         foreach($asiento_factura->messages as $msg)
         {
            $this->new_message($msg);
         }
      }
   }
   
      private function generar_asiento_credito()
   {
   		// toma un asiento existente con su id
      if( $this->factura->get_asiento() ) //factura_proveedor
      {
         $this->new_error_msg('Ya hay un asiento asociado a esta factura.');
      }
      else
      {
         $asiento_factura = new asiento_factura();
         $asiento_factura->soloasiento = TRUE;
		 // Genera la partida que está en asiento_factura
         if( $asiento_factura->generar_asiento_compra_credito($this->factura) )
         {
            $this->new_message("<a href='".$asiento_factura->asiento->url()."'>Asiento</a> generado correctamente.");
            $this->new_change('Factura Proveedor '.$this->factura->codigo, $this->factura->url());
         }
         
         foreach($asiento_factura->errors as $err)
         {
            $this->new_error_msg($err);
         }
         
         foreach($asiento_factura->messages as $msg)
         {
            $this->new_message($msg);
         }
      }
   }
   
   
      private function new_search()
   {
      /// desactivamos la plantilla HTML
      $this->template = FALSE;
      
      $this->results = $this->search_from_proveedor();
      
      /// completamos los datos
      foreach($this->results as $i => $value)
      {
         $this->results[$i]->query = $this->query;
         $this->results[$i]->coste = $value->preciocoste();
         $this->results[$i]->dtopor = 0;
         
         if( isset($_REQUEST['codproveedor']) )
         {
            $ap = $this->articulo_prov->get_by($value->referencia, $_REQUEST['codproveedor']);
            if($ap)
            {
               $this->results[$i]->coste = $ap->precio;
               $this->results[$i]->dtopor = $ap->dto;
            }
         }
      }
      
      header('Content-Type: application/json');
      echo json_encode($this->results);
   }
   
   
      private function get_precios_articulo()
   {
      /// cambiamos la plantilla HTML
      $this->template = 'ajax/nueva_compra_precios';
      
      $articulo = new articulo();
      $this->articulo = $articulo->get($_POST['referencia4precios']);
   }
   
   
      private function actualizar_precio_proveedor($codproveedor, $linea)
   {
      if( !is_null($linea->referencia) )
      {
         $artp = $this->articulo_prov->get_by($linea->referencia, $codproveedor);
         if(!$artp)
         {
            $artp = new articulo_proveedor();
            $artp->codproveedor = $codproveedor;
            $artp->referencia = $linea->referencia;
            $artp->refproveedor = $linea->referencia;
            $artp->codimpuesto = $linea->codimpuesto;
            $artp->descripcion = $linea->descripcion;
         }
         
         $artp->precio = $linea->pvpunitario;
         $artp->dto = $linea->dtopor;
         $artp->save();
      }
   }
   
   
     private function search_from_proveedor()
   {
      $artilist = array();
      $query = $this->articulo_prov->no_html( strtolower($this->query) );
      $sql = "SELECT * FROM articulos";
      $separador = ' WHERE';
      
      if($_REQUEST['codfamilia'] != '')
      {
         $sql .= $separador." codfamilia = ".$this->articulo_prov->var2str($_REQUEST['codfamilia']);
         $separador = ' AND';
      }
      
      if($_REQUEST['codfabricante'] != '')
      {
         $sql .= $separador." codfabricante = ".$this->articulo_prov->var2str($_REQUEST['codfabricante']);
         $separador = ' AND';
      }
      
      if( isset($_REQUEST['con_stock']) )
      {
         $sql .= $separador." stockfis > 0";
         $separador = ' AND';
      }
      
      if( isset($_REQUEST['solo_proveedor']) AND isset($_REQUEST['codproveedor']) )
      {
         $sql .= $separador." referencia IN (SELECT referencia FROM articulosprov WHERE codproveedor = "
                 .$this->articulo_prov->var2str($_REQUEST['codproveedor']).")";
         $separador = ' AND';
      }
      
      if( is_numeric($this->query) )
      {
         $sql .= $separador." (lower(referencia) = ".$this->articulo_prov->var2str($this->query)
                 . " OR referencia LIKE '%".$this->query."%' OR equivalencia LIKE '%".$this->query."%'"
                 . " OR descripcion LIKE '%".$this->query."%' OR codbarras = '".$this->query."')";
      }
      else
      {
         $buscar = str_replace(' ', '%', $this->query);
         $sql .= $separador." (lower(referencia) = ".$this->articulo_prov->var2str($this->query)
                 . " OR lower(referencia) LIKE '%".$buscar."%' OR lower(equivalencia) LIKE '%".$buscar."%'"
                 . " OR lower(descripcion) LIKE '%".$buscar."%')";
      }
      
      $sql .= " ORDER BY referencia ASC";
      
      $data = $this->db->select_limit($sql, FS_ITEM_LIMIT, 0);
      if($data)
      {
         foreach($data as $a)
         {
            $artilist[] = new articulo($a);
         }
      }
      
      return $artilist;
   }
   
   
   
   
}
