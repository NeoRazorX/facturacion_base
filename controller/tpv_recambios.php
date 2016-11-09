<?php
/*
 * This file is part of FacturaScripts
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

require_model('agente.php');
require_model('almacen.php');
require_model('articulo.php');
require_model('articulo_combinacion.php');
require_model('asiento_factura.php');
require_model('caja.php');
require_model('cliente.php');
require_model('divisa.php');
require_model('ejercicio.php');
require_model('fabricante.php');
require_model('factura_cliente.php');
require_model('familia.php');
require_model('forma_pago.php');
require_model('grupo_clientes.php');
require_model('impuesto.php');
require_model('regularizacion_iva.php');
require_model('serie.php');
require_model('tarifa.php');
require_model('terminal_caja.php');

class tpv_recambios extends fs_controller
{
   public $agente;
   public $almacen;
   public $allow_delete;
   public $articulo;
   public $caja;
   public $cliente;
   public $cliente_s;
   public $divisa;
   public $ejercicio;
   public $equivalentes;
   public $fabricante;
   public $familia;
   public $forma_pago;
   public $imprimir_descripciones;
   public $imprimir_observaciones;
   public $impuesto;
   public $results;
   public $serie;
   public $terminal;
   public $ultimas_compras;
   public $ultimas_ventas;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'TPV Genérico', 'TPV');
   }
   
   protected function private_core()
   {
      $this->share_extensions();
      
      /// ¿El usuario tiene permiso para eliminar en esta página?
      $this->allow_delete = $this->user->allow_delete_on(__CLASS__);
      
      $this->articulo = new articulo();
      $this->cliente = new cliente();
      $this->cliente_s = FALSE;
      $this->fabricante = new fabricante();
      $this->familia = new familia();
      $this->impuesto = new impuesto();
      $this->results = array();
      
      if( isset($_REQUEST['buscar_cliente']) )
      {
         $this->buscar_cliente();
      }
      else if( isset($_REQUEST['datoscliente']) )
      {
         $this->datos_cliente();
      }
      else if($this->query != '')
      {
         $this->new_search();
      }
      else if( isset($_REQUEST['referencia4precios']) )
      {
         $this->get_precios_articulo();
      }
      else if( isset($_POST['referencia4combi']) )
      {
         $this->get_combinaciones_articulo();
      }
      else
      {
         $this->agente = $this->user->get_agente();
         $this->almacen = new almacen();
         $this->divisa = new divisa();
         $this->ejercicio = new ejercicio();
         $this->forma_pago = new forma_pago();
         $this->serie = new serie();
         
         $this->imprimir_descripciones = isset($_COOKIE['imprimir_desc']);
         $this->imprimir_observaciones = isset($_COOKIE['imprimir_obs']);
         
         if($this->agente)
         {
            $this->caja = FALSE;
            $this->terminal = FALSE;
            $caja = new caja();
            $terminal0 = new terminal_caja();
            foreach($caja->all_by_agente($this->agente->codagente) as $cj)
            {
               if( $cj->abierta() )
               {
                  $this->caja = $cj;
                  $this->terminal = $terminal0->get($cj->fs_id);
                  break;
               }
            }
            
            if(!$this->caja)
            {
               if( isset($_POST['terminal']) )
               {
                  $this->terminal = $terminal0->get($_POST['terminal']);
                  if(!$this->terminal)
                  {
                     $this->new_error_msg('Terminal no encontrado.');
                  }
                  else if( $this->terminal->disponible() )
                  {
                     $this->caja = new caja();
                     $this->caja->fs_id = $this->terminal->id;
                     $this->caja->codagente = $this->agente->codagente;
                     $this->caja->dinero_inicial = floatval($_POST['d_inicial']);
                     $this->caja->dinero_fin = floatval($_POST['d_inicial']);
                     if( $this->caja->save() )
                     {
                        $this->new_message("Caja iniciada con ".$this->show_precio($this->caja->dinero_inicial) );
                     }
                     else
                        $this->new_error_msg("¡Imposible guardar los datos de caja!");
                  }
                  else
                     $this->new_error_msg('El terminal ya no está disponible.');
               }
               else if( isset($_GET['terminal']) )
               {
                  $this->terminal = $terminal0->get($_GET['terminal']);
                  if($this->terminal)
                  {
                     $this->terminal->abrir_cajon();
                     $this->terminal->save();
                  }
                  else
                     $this->new_error_msg('Terminal no encontrado.');
               }
            }
            
            if($this->caja)
            {
               if( isset($_POST['cliente']) )
               {
                  $this->cliente_s = $this->cliente->get($_POST['cliente']);
               }
               else if($this->terminal)
               {
                  $this->cliente_s = $this->cliente->get($this->terminal->codcliente);
               }
               
               if(!$this->cliente_s)
               {
                  foreach($this->cliente->all() as $cli)
                  {
                     $this->cliente_s = $cli;
                     break;
                  }
               }
               
               if( isset($_GET['abrir_caja']) )
               {
                  $this->abrir_caja();
               }
               else if( isset($_GET['cerrar_caja']) )
               {
                  $this->cerrar_caja();
               }
               else if( isset($_POST['cliente']) )
               {
                  if( intval($_POST['numlineas']) > 0 )
                  {
                     $this->nueva_factura_cliente();
                  }
               }
               else if( isset($_GET['reticket']) )
               {
                  $this->reimprimir_ticket();
               }
            }
            else
            {
               $this->results = $terminal0->disponibles();
            }
         }
         else
         {
            $this->new_error_msg('No tienes un <a href="'.$this->user->url().'">agente asociado</a>
               a tu usuario, y por tanto no puedes hacer tickets.');
         }
      }
   }
   
   private function buscar_cliente()
   {
      /// desactivamos la plantilla HTML
      $this->template = FALSE;
      
      $json = array();
      foreach($this->cliente->search($_REQUEST['buscar_cliente']) as $cli)
      {
         $json[] = array('value' => $cli->razonsocial, 'data' => $cli->codcliente, 'full' => $cli);
      }
      
      header('Content-Type: application/json');
      echo json_encode( array('query' => $_REQUEST['buscar_cliente'], 'suggestions' => $json) );
   }
   
   private function datos_cliente()
   {
      /// desactivamos la plantilla HTML
      $this->template = FALSE;
      
      header('Content-Type: application/json');
      echo json_encode( $this->cliente->get($_REQUEST['datoscliente']) );
   }
   
   private function new_search()
   {
      /// desactivamos la plantilla HTML
      $this->template = FALSE;
      
      $fsvar = new fs_var();
      $multi_almacen = $fsvar->simple_get('multi_almacen');
      $stock = new stock();
      
      $codfamilia = '';
      if( isset($_REQUEST['codfamilia']) )
      {
         $codfamilia = $_REQUEST['codfamilia'];
      }
      $codfabricante = '';
      if( isset($_REQUEST['codfabricante']) )
      {
         $codfabricante = $_REQUEST['codfabricante'];
      }
      $con_stock = isset($_REQUEST['con_stock']);
      $this->results = $this->articulo->search($this->query, 0, $codfamilia, $con_stock, $codfabricante);
      
      /// añadimos el descuento y la cantidad
      foreach($this->results as $i => $value)
      {
         $this->results[$i]->query = $this->query;
         $this->results[$i]->dtopor = 0;
         $this->results[$i]->cantidad = 1;
         
         $this->results[$i]->stockalm = $value->stockfis;
         if( $multi_almacen AND isset($_REQUEST['codalmacen']) )
         {
            $this->results[$i]->stockalm = $stock->total_from_articulo($this->results[$i]->referencia, $_REQUEST['codalmacen']);
         }
      }
      
      /// ejecutamos las funciones de las extensiones
      foreach($this->extensions as $ext)
      {
         if($ext->type == 'function' AND $ext->params == 'new_search')
         {
            $name = $ext->text;
            $name($this->db, $this->results);
         }
      }
      
      if( isset($_REQUEST['codcliente']) )
      {
         $cliente = $this->cliente->get($_REQUEST['codcliente']);
         if($cliente)
         {
            if($cliente->codgrupo)
            {
               $grupo0 = new grupo_clientes();
               $tarifa0 = new tarifa();
               
               $grupo = $grupo0->get($cliente->codgrupo);
               if($grupo)
               {
                  $tarifa = $tarifa0->get($grupo->codtarifa);
                  if($tarifa)
                  {
                     $tarifa->set_precios($this->results);
                  }
               }
            }
         }
      }
      
      header('Content-Type: application/json');
      echo json_encode($this->results);
   }
   
   private function get_precios_articulo()
   {
      /// cambiamos la plantilla HTML
      $this->template = 'ajax/tpv_recambios_precios';
      
      $this->articulo = $this->articulo->get($_REQUEST['referencia4precios']);
   }
   
   private function get_combinaciones_articulo()
   {
      /// cambiamos la plantilla HTML
      $this->template = 'ajax/tpv_recambios_combinaciones';
      
      $this->results = array();
      $comb1 = new articulo_combinacion();
      foreach($comb1->all_from_ref($_POST['referencia4combi']) as $com)
      {
         if( isset($this->results[$com->codigo]) )
         {
            $this->results[$com->codigo]['desc'] .= ', '.$com->nombreatributo.' - '.$com->valor;
            $this->results[$com->codigo]['txt'] .= ', '.$com->nombreatributo.' - '.$com->valor;
         }
         else
         {
            $this->results[$com->codigo] = array(
                'ref' => $_POST['referencia4combi'],
                'desc' => base64_decode($_POST['desc'])."\n".$com->nombreatributo.' - '.$com->valor,
                'pvp' => floatval($_POST['pvp']) + $com->impactoprecio,
                'dto' => floatval($_POST['dto']),
                'codimpuesto' => $_POST['codimpuesto'],
                'cantidad' => floatval($_POST['cantidad']),
                'txt' => $com->nombreatributo.' - '.$com->valor
            );
         }
      }
   }
   
   public function get_tarifas_articulo($ref)
   {
      $tarlist = array();
      $articulo = new articulo();
      $tarifa = new tarifa();
      
      foreach($tarifa->all() as $tar)
      {
         $art = $articulo->get($ref);
         if($art)
         {
            $art->dtopor = 0;
            $aux = array($art);
            $tar->set_precios($aux);
            $tarlist[] = $aux[0];
         }
      }
      
      return $tarlist;
   }

   private function nueva_factura_cliente()
   {
      $continuar = TRUE;
      
      $ejercicio = $this->ejercicio->get_by_fecha($_POST['fecha']);
      if(!$ejercicio)
      {
         $this->new_error_msg('Ejercicio no encontrado o está cerrado.');
         $continuar = FALSE;
      }
      
      $serie = $this->serie->get($_POST['serie']);
      if(!$serie)
      {
         $this->new_error_msg('Serie no encontrada.');
         $continuar = FALSE;
      }
      
      $forma_pago = $this->forma_pago->get($_POST['forma_pago']);
      if($forma_pago)
      {
         $this->save_codpago($_POST['forma_pago']);
      }
      else
      {
         $this->new_error_msg('Forma de pago no encontrada.');
         $continuar = FALSE;
      }
      
      $divisa = $this->divisa->get($_POST['divisa']);
      if(!$divisa)
      {
         $this->new_error_msg('Divisa no encontrada.');
         $continuar = FALSE;
      }
      
      if( isset($_POST['imprimir_desc']) )
      {
         $this->imprimir_descripciones = TRUE;
         setcookie('imprimir_desc', TRUE, time()+FS_COOKIES_EXPIRE);
      }
      else
      {
         $this->imprimir_descripciones = FALSE;
         setcookie('imprimir_desc', FALSE, time()-FS_COOKIES_EXPIRE);
      }
      
      if( isset($_POST['imprimir_obs']) )
      {
         $this->imprimir_observaciones = TRUE;
         setcookie('imprimir_obs', TRUE, time()+FS_COOKIES_EXPIRE);
      }
      else
      {
         $this->imprimir_observaciones = FALSE;
         setcookie('imprimir_obs', FALSE, time()-FS_COOKIES_EXPIRE);
      }
      
      $factura = new factura_cliente();
      
      if( $this->duplicated_petition($_POST['petition_id']) )
      {
         $this->new_error_msg('Petición duplicada. Has hecho doble clic sobre el botón Guardar
               y se han enviado dos peticiones. Mira en <a href="'.$factura->url().'">Facturas</a>
               para ver si la factura se ha guardado correctamente.');
         $continuar = FALSE;
      }
      
      if($continuar)
      {
         $factura->codejercicio = $ejercicio->codejercicio;
         $factura->codserie = $serie->codserie;
         $factura->set_fecha_hora($_POST['fecha'], $factura->hora);
         
         $factura->codalmacen = $_POST['almacen'];
         $factura->codpago = $forma_pago->codpago;
         $factura->coddivisa = $divisa->coddivisa;
         $factura->tasaconv = $divisa->tasaconv;
         
         if($_POST['tasaconv'] != '')
         {
            $factura->tasaconv = floatval($_POST['tasaconv']);
         }
         
         $factura->codagente = $this->agente->codagente;
         $factura->observaciones = $_POST['observaciones'];
         $factura->numero2 = $_POST['numero2'];
         $factura->porcomision = $this->agente->porcomision;
         
         if($forma_pago->genrecibos == 'Pagados')
         {
            $factura->pagada = TRUE;
         }
         
         $factura->vencimiento = Date('d-m-Y', strtotime($factura->fecha.' '.$forma_pago->vencimiento));
         
         $factura->codcliente = $this->cliente_s->codcliente;
         $factura->cifnif = $_POST['cifnif'];
         $factura->nombrecliente = $_POST['nombrecliente'];
         $factura->ciudad = $this->empresa->ciudad;
         $factura->codpais = $this->empresa->codpais;
         $factura->codpostal = $this->empresa->codpostal;
         $factura->provincia = $this->empresa->provincia;
         
         foreach($this->cliente_s->get_direcciones() as $d)
         {
            if($d->domfacturacion)
            {
               $factura->apartado = $d->apartado;
               $factura->ciudad = $d->ciudad;
               $factura->coddir = $d->id;
               $factura->codpais = $d->codpais;
               $factura->codpostal = $d->codpostal;
               $factura->direccion = $d->direccion;
               $factura->provincia = $d->provincia;
               break;
            }
         }
         
         $regularizacion = new regularizacion_iva();
         if( $regularizacion->get_fecha_inside($factura->fecha) )
         {
            $this->new_error_msg("El ".FS_IVA." de ese periodo ya ha sido regularizado."
                    . " No se pueden añadir más facturas en esa fecha.");
         }
         else if( $factura->save() )
         {
            $n = floatval($_POST['numlineas']);
            for($i = 1; $i <= $n; $i++)
            {
               if( isset($_POST['referencia_'.$i]) )
               {
                  $articulo = $this->articulo->get($_POST['referencia_'.$i]);
                  if($articulo)
                  {
                     $linea = new linea_factura_cliente();
                     $linea->idfactura = $factura->idfactura;
                     $linea->referencia = $articulo->referencia;
                     $linea->descripcion = $_POST['desc_'.$i];
                     
                     if( !$serie->siniva OR $this->cliente_s->regimeniva != 'Exento' )
                     {
                        $linea->codimpuesto = $articulo->codimpuesto;
                        $linea->iva = floatval($_POST['iva_'.$i]);
                        $linea->recargo = floatval($_POST['recargo_'.$i]);
                     }
                     
                     $linea->irpf = floatval($_POST['irpf_'.$i]);
                     $linea->pvpunitario = floatval($_POST['pvp_'.$i]);
                     $linea->cantidad = floatval($_POST['cantidad_'.$i]);
                     $linea->dtopor = floatval($_POST['dto_'.$i]);
                     $linea->pvpsindto = ($linea->pvpunitario * $linea->cantidad);
                     $linea->pvptotal = floatval($_POST['neto_'.$i]);
                     
                     if( $linea->save() )
                     {
                        /// descontamos del stock
                        $articulo->sum_stock($factura->codalmacen, 0 - $linea->cantidad);
                        
                        $factura->neto += $linea->pvptotal;
                        $factura->totaliva += ($linea->pvptotal * $linea->iva/100);
                        $factura->totalirpf += ($linea->pvptotal * $linea->irpf/100);
                        $factura->totalrecargo += ($linea->pvptotal * $linea->recargo/100);
                        
                        if($linea->irpf > $factura->irpf)
                        {
                           $factura->irpf = $linea->irpf;
                        }
                     }
                     else
                     {
                        $this->new_error_msg("¡Imposible guardar la linea con referencia: ".$linea->referencia);
                        $continuar = FALSE;
                     }
                  }
                  else
                  {
                     $this->new_error_msg("Artículo no encontrado: ".$_POST['referencia_'.$i]);
                     $continuar = FALSE;
                  }
               }
            }
            
            if($continuar)
            {
               /// redondeamos
               $factura->neto = round($factura->neto, FS_NF0);
               $factura->totaliva = round($factura->totaliva, FS_NF0);
               $factura->totalirpf = round($factura->totalirpf, FS_NF0);
               $factura->totalrecargo = round($factura->totalrecargo, FS_NF0);
               $factura->total = $factura->neto + $factura->totaliva - $factura->totalirpf + $factura->totalrecargo;
               
               if( abs(floatval($_POST['tpv_total2']) - $factura->total) >= .02 )
               {
                  $this->new_error_msg("El total difiere entre la vista y el controlador (".$_POST['tpv_total2'].
                          " frente a ".$factura->total."). Debes informar del error.");
                  $factura->delete();
               }
               else if( $factura->save() )
               {
                  $this->new_message("<a href='".$factura->url()."'>Factura</a> guardada correctamente.");
                  
                  $this->generar_asiento($factura);
                  
                  if($_POST['regalo'] == 'TRUE')
                  {
                     $this->imprimir_ticket_regalo($factura);
                  }
                  else
                  {
                     $this->imprimir_ticket( $factura, floatval($_POST['num_tickets']) );
                  }
                  
                  /// actualizamos la caja
                  $this->caja->dinero_fin += $factura->total;
                  $this->caja->tickets += 1;
                  $this->caja->ip = $_SERVER['REMOTE_ADDR'];
                  if( !$this->caja->save() )
                  {
                     $this->new_error_msg("¡Imposible actualizar la caja!");
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
   
   private function abrir_caja()
   {
      if($this->user->admin)
      {
         if($this->terminal)
         {
            $this->terminal->abrir_cajon();
            $this->terminal->save();
         }
      }
      else
         $this->new_error_msg('Sólo un administrador puede abrir la caja.');
   }
   
   private function cerrar_caja()
   {
      $this->caja->fecha_fin = Date('d-m-Y H:i:s');
      if( $this->caja->save() )
      {
         if( $this->terminal )
         {
            $this->terminal->add_linea_big("\nCIERRE DE CAJA:\n\n");
            $this->terminal->add_linea("Empleado: ".$this->user->codagente." ".$this->agente->get_fullname()."\n");
            $this->terminal->add_linea("Caja: ".$this->caja->fs_id."\n");
            $this->terminal->add_linea("Fecha inicial: ".$this->caja->fecha_inicial."\n");
            $this->terminal->add_linea("Dinero inicial: ".$this->show_precio($this->caja->dinero_inicial, FALSE, FALSE)."\n");
            $this->terminal->add_linea("Fecha fin: ".$this->caja->show_fecha_fin()."\n");
            $this->terminal->add_linea("Dinero fin: ".$this->show_precio($this->caja->dinero_fin, FALSE, FALSE)."\n");
            $this->terminal->add_linea("Diferencia: ".$this->show_precio($this->caja->diferencia(), FALSE, FALSE)."\n");
            $this->terminal->add_linea("Tickets: ".$this->caja->tickets."\n\n");
            $this->terminal->add_linea("Dinero pesado:\n\n\n");
            $this->terminal->add_linea("Observaciones:\n\n\n\n");
            $this->terminal->add_linea("Firma:\n\n\n\n\n\n\n\n\n\n");
            $this->terminal->cortar_papel();
            $this->terminal->abrir_cajon();
            $this->terminal->save();
            
            /// recargamos la página
            header('location: '.$this->url().'&terminal='.$this->terminal->id);
         }
         else
         {
            /// recargamos la página
            header('location: '.$this->url());
         }
      }
      else
         $this->new_error_msg("¡Imposible cerrar la caja!");
   }
   
   private function reimprimir_ticket()
   {
      $factura = new factura_cliente();
      $fac0 = FALSE;
      
      if($_GET['reticket'] == '')
      {
         foreach($factura->all() as $fac)
         {
            $fac0 = $fac;
            break;
         }
      }
      else
         $fac0 = $factura->get_by_codigo($_GET['reticket']);
      
      if($fac0)
      {
         $this->imprimir_ticket($fac0, 1, FALSE);
      }
      else
         $this->new_error_msg("Ticket no encontrado.");
   }
   
   /**
    * Añade el ticket a la cola de impresión.
    * @param factura_cliente $factura
    * @param type $num_tickets
    * @param type $cajon
    */
   private function imprimir_ticket($factura, $num_tickets = 1, $cajon = TRUE)
   {
      if($this->terminal)
      {
         if($cajon)
         {
            $this->terminal->abrir_cajon();
         }
         
         while($num_tickets > 0)
         {
            $this->terminal->imprimir_ticket($factura, $this->empresa, $this->imprimir_descripciones, $this->imprimir_observaciones);
            $num_tickets--;
         }
         
         $this->terminal->save();
         $this->new_message('<a href="#" data-toggle="modal" data-target="#modal_ayuda_ticket">¿No se imprime el ticket?</a>');
      }
      else
      {
         $this->new_error_msg('Terminal no encontrado.');
      }
   }
   
   /**
    * Imprime uno o varios tickets de la factura.
    * @param factura_cliente $factura
    * @param type $num_tickets
    * @param type $cajon
    */
   private function imprimir_ticket_regalo($factura, $num_tickets = 1, $cajon = TRUE)
   {
      if($this->terminal)
      {
         if($cajon)
         {
            $this->terminal->abrir_cajon();
         }
         
         while($num_tickets > 0)
         {
            $this->terminal->imprimir_ticket_regalo($factura, $this->empresa, $this->imprimir_descripciones, $this->imprimir_observaciones);
            $num_tickets--;
         }
         
         $this->terminal->save();
      }
   }
   
   /**
    * Genera el asiento para la factura, si procede
    * @param factura_cliente $factura
    */
   private function generar_asiento(&$factura)
   {
      if($this->empresa->contintegrada)
      {
         $asiento_factura = new asiento_factura();
         $asiento_factura->generar_asiento_venta($factura);
         
         foreach($asiento_factura->errors as $err)
         {
            $this->new_error_msg($err);
         }
         
         foreach($asiento_factura->messages as $msg)
         {
            $this->new_message($msg);
         }
      }
      else
      {
         /// de todas formas forzamos la generación de las líneas de iva
         $factura->get_lineas_iva();
      }
   }
   
   private function share_extensions()
   {
      $fsext = new fs_extension();
      $fsext->name = 'api_remote_printer';
      $fsext->from = __CLASS__;
      $fsext->type = 'api';
      $fsext->text = 'remote_printer';
      $fsext->save();
   }
}
