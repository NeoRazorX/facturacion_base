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

require_model('albaran_proveedor.php');
require_model('asiento.php');
require_model('asiento_factura.php');
require_model('divisa.php');
require_model('ejercicio.php');
require_model('factura_proveedor.php');
require_model('forma_pago.php');
require_model('partida.php');
require_model('proveedor.php');
require_model('regularizacion_iva.php');
require_model('serie.php');
require_model('subcuenta.php');

class compras_agrupar_albaranes extends fs_controller
{
   public $albaran;
   public $coddivisa;
   public $codserie;
   public $desde;
   public $divisa;
   public $hasta;
   public $proveedor;
   public $resultados;
   public $serie;
   public $neto;
   public $total;
   
   private $ejercicio;
   private $forma_pago;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Agrupar '.FS_ALBARANES, 'compras', FALSE, FALSE);
   }
   
   protected function private_core()
   {
      $this->albaran = new albaran_proveedor();
      $this->divisa = new divisa();
      $this->ejercicio = new ejercicio();
      $this->forma_pago = new forma_pago();
      $this->proveedor = FALSE;
      $this->serie = new serie();
      $this->neto = 0;
      $this->total = 0;
      
      $this->coddivisa = $this->empresa->coddivisa;
      if( isset($_REQUEST['coddivisa']) )
      {
         $this->coddivisa = $_REQUEST['coddivisa'];
      }
      
      $this->codserie = $this->empresa->codserie;
      if( isset($_REQUEST['codserie']) )
      {
         $this->codserie = $_REQUEST['codserie'];
      }
      
      $this->desde = Date('01-01-Y');
      if( isset($_REQUEST['desde']) )
      {
         $this->desde = $_REQUEST['desde'];
      }
      
      $this->hasta = Date('t-m-Y');
      if( isset($_REQUEST['hasta']) )
      {
         $this->hasta = $_REQUEST['hasta'];
      }
      
      /// el desde no puede ser mayor que el hasta
      if( strtotime($this->desde) > strtotime($this->hasta) )
      {
         $this->hasta = $this->desde;
      }
      
      if( isset($_REQUEST['buscar_proveedor']) )
      {
         $this->buscar_proveedor();
      }
      else if( isset($_POST['idalbaran']) )
      {
         $this->proveedor = new proveedor();
         $this->agrupar();
      }
      else if( isset($_REQUEST['codproveedor']) )
      {
         $pr0 = new proveedor();
         $this->proveedor = $pr0->get($_REQUEST['codproveedor']);
         
         if($this->proveedor)
         {
            $this->resultados = $this->albaran->search_from_proveedor(
                    $this->proveedor->codproveedor,
                    $this->desde,
                    $this->hasta,
                    $this->codserie,
                    $this->coddivisa
            );
            if($this->resultados)
            {
               foreach($this->resultados as $alb)
               {
                  $this->neto += $alb->neto;
                  $this->total += $alb->total;
               }
            }
            else
            {
               $this->new_message("Sin resultados.");
            }
         }
      }
      else
         $this->share_extensions();
   }
   
   private function buscar_proveedor()
   {
      /// desactivamos la plantilla HTML
      $this->template = FALSE;
      
      $proveedor = new proveedor();
      $json = array();
      foreach($proveedor->search($_REQUEST['buscar_proveedor']) as $pro)
      {
         $json[] = array('value' => $pro->razonsocial, 'data' => $pro->codproveedor);
      }
      
      header('Content-Type: application/json');
      echo json_encode( array('query' => $_REQUEST['buscar_proveedor'], 'suggestions' => $json) );
   }
   
   private function agrupar()
   {
      $continuar = TRUE;
      $albaranes = array();
      
      if( $this->duplicated_petition($_POST['petition_id']) )
      {
         $this->new_error_msg('Petición duplicada. Has hecho doble clic sobre el botón y se han enviado dos peticiones.');
         $continuar = FALSE;
      }
      else
      {
         foreach($_POST['idalbaran'] as $id)
         {
            $albaranes[] = $this->albaran->get($id);
         }
         
         foreach($albaranes as $alb)
         {
            if(!$alb->ptefactura)
            {
               $this->new_error_msg("El ".FS_ALBARAN." <a href='".$alb->url()."'>".$alb->codigo."</a> ya está facturado.");
               $continuar = FALSE;
               break;
            }
         }
      }
      
      if($continuar)
      {
         if( isset($_POST['individuales']) )
         {
            foreach($albaranes as $alb)
            {
               $this->generar_factura( array($alb) );
            }
         }
         else
            $this->generar_factura($albaranes);
      }
   }
   
   private function generar_factura($albaranes)
   {
      $continuar = TRUE;
      
      $factura = new factura_proveedor();
      $factura->codagente = $this->user->codagente;
      $factura->codalmacen = $albaranes[0]->codalmacen;
      $factura->coddivisa = $albaranes[0]->coddivisa;
      $factura->tasaconv = $albaranes[0]->tasaconv;
      $factura->codpago = $albaranes[0]->codpago;
      $factura->codserie = $albaranes[0]->codserie;
      $factura->irpf = $albaranes[0]->irpf;
      $factura->numproveedor = $albaranes[0]->numproveedor;
      $factura->observaciones = $albaranes[0]->observaciones;
      
      /// obtenemos los datos actualizados del proveedor
      $proveedor = $this->proveedor->get($albaranes[0]->codproveedor);
      if($proveedor)
      {
         $factura->cifnif = $proveedor->cifnif;
         $factura->codproveedor = $proveedor->codproveedor;
         $factura->nombre = $proveedor->razonsocial;
      }
      
      /// calculamos neto e iva
      foreach($albaranes as $alb)
      {
         foreach($alb->get_lineas() as $l)
         {
            $factura->neto += $l->pvptotal;
            $factura->totaliva += $l->pvptotal * $l->iva/100;
            $factura->totalirpf += $l->pvptotal * $l->irpf/100;
            $factura->totalrecargo += $l->pvptotal * $l->recargo/100;
         }
      }
      
      /// redondeamos
      $factura->neto = round($factura->neto, FS_NF0);
      $factura->totaliva = round($factura->totaliva, FS_NF0);
      $factura->totalirpf = round($factura->totalirpf, FS_NF0);
      $factura->totalrecargo = round($factura->totalrecargo, FS_NF0);
      $factura->total = $factura->neto + $factura->totaliva - $factura->totalirpf + $factura->totalrecargo;
      
      /// asignamos el ejercicio que corresponde a la fecha elegida
      $eje0 = $this->ejercicio->get_by_fecha($_POST['fecha']);
      if($eje0)
      {
         $factura->codejercicio = $eje0->codejercicio;
         $factura->set_fecha_hora($_POST['fecha'], $factura->hora);
      }
      
      /// comprobamos la forma de pago para saber si hay que marcar la factura como pagada
      $formapago = $this->forma_pago->get($factura->codpago);
      if($formapago)
      {
         if($formapago->genrecibos == 'Pagados')
         {
            $factura->pagada = TRUE;
         }
      }
      
      $regularizacion = new regularizacion_iva();
      
      if( !$eje0 )
      {
         $this->new_error_msg("Ejercicio no encontrado o está cerrado.");
      }
      else if( !$eje0->abierto() )
      {
         $this->new_error_msg('El ejercicio '.$eje0->codejercicio.' está cerrado.');
      }
      else if( $regularizacion->get_fecha_inside($factura->fecha) )
      {
         /*
          * comprobamos que la fecha de la factura no esté dentro de un periodo de
          * IVA regularizado.
          */
         $this->new_error_msg('El '.FS_IVA.' de ese periodo ya ha sido regularizado. No se pueden añadir más facturas en esa fecha.');
      }
      else if( $factura->save() )
      {
         foreach($albaranes as $alb)
         {
            foreach($alb->get_lineas() as $l)
            {
               $n = new linea_factura_proveedor();
               $n->idalbaran = $alb->idalbaran;
               $n->idfactura = $factura->idfactura;
               $n->cantidad = $l->cantidad;
               $n->codimpuesto = $l->codimpuesto;
               $n->descripcion = $l->descripcion;
               $n->dtopor = $l->dtopor;
               $n->irpf = $l->irpf;
               $n->iva = $l->iva;
               $n->pvpsindto = $l->pvpsindto;
               $n->pvptotal = $l->pvptotal;
               $n->pvpunitario = $l->pvpunitario;
               $n->recargo = $l->recargo;
               $n->referencia = $l->referencia;
               
               if( !$n->save() )
               {
                  $continuar = FALSE;
                  $this->new_error_msg("¡Imposible guardar la línea el artículo ".$n->referencia."! ");
                  break;
               }
            }
         }
         
         if($continuar)
         {
            foreach($albaranes as $alb)
            {
               $alb->idfactura = $factura->idfactura;
               $alb->ptefactura = FALSE;
               
               if( !$alb->save() )
               {
                  $this->new_error_msg("¡Imposible vincular el ".FS_ALBARAN." con la nueva factura!");
                  $continuar = FALSE;
                  break;
               }
            }
            
            if( $continuar )
            {
               $this->generar_asiento($factura);
            }
            else
            {
               if( $factura->delete() )
               {
                  $this->new_error_msg("La factura se ha borrado.");
               }
               else
                  $this->new_error_msg("¡Imposible borrar la factura!");
            }
         }
         else
         {
            if( $factura->delete() )
            {
               $this->new_error_msg("La factura se ha borrado.");
            }
            else
               $this->new_error_msg("¡Imposible borrar la factura!");
         }
      }
      else
         $this->new_error_msg("¡Imposible guardar la factura!");
   }
   
   private function generar_asiento(&$factura)
   {
      if($this->empresa->contintegrada)
      {
         $asiento_factura = new asiento_factura();
         if( $asiento_factura->generar_asiento_compra($factura) )
         {
            $this->new_message("<a href='".$factura->url()."'>Factura</a> generada correctamente.");
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
      else
      {
         $this->new_message("<a href='".$factura->url()."'>Factura</a> generada correctamente.");
      }
      
      $this->new_change('Factura Proveedor '.$factura->codigo, $factura->url(), TRUE);
   }
   
   private function share_extensions()
   {
      $extension = array(
          'name' => 'agrupar_albaranes',
          'page_from' => __CLASS__,
          'page_to' => 'compras_albaranes',
          'type' => 'button',
          'text' => '<span class="glyphicon glyphicon-duplicate"></span><span class="hidden-xs">&nbsp; Agrupar</span>',
          'params' => ''
      );
      $fsext = new fs_extension($extension);
      $fsext->save();
   }
   
   public function pendientes()
   {
      $pendientes = array();
      
      $offset = 0;
      $albaranes = $this->albaran->all_ptefactura($offset);
      while($albaranes)
      {
         foreach($albaranes as $alb)
         {
            if($alb->codproveedor)
            {
               /// Comprobamos si el proveedor ya está en la lista.
               $encontrado = FALSE;
               foreach($pendientes as $i => $pe)
               {
                  if($alb->codproveedor == $pe['codproveedor'] AND $alb->codserie == $pe['codserie'] AND $alb->coddivisa == $pe['coddivisa'])
                  {
                     $encontrado = TRUE;
                     $pendientes[$i]['num']++;
                     
                     if( strtotime($alb->fecha) < strtotime($pe['desde']) )
                     {
                        $pendientes[$i]['desde'] = $alb->fecha;
                     }
                     
                     if( strtotime($alb->fecha) > strtotime($pe['hasta']) )
                     {
                        $pendientes[$i]['hasta'] = $alb->fecha;
                     }
                     
                     break;
                  }
               }
               
               /// Añadimos a la lista de pendientes.
               if(!$encontrado)
               {
                  $pendientes[] = array(
                      'codproveedor' => $alb->codproveedor,
                      'nombre' => $alb->nombre,
                      'codserie' => $alb->codserie,
                      'coddivisa' => $alb->coddivisa,
                      'desde' => date('d-m-Y', min( array( strtotime($alb->fecha), strtotime($this->desde) ) )),
                      'hasta' => date('d-m-Y', max( array( strtotime($alb->fecha), strtotime($this->hasta) ) )),
                      'num' => 1
                  );
               }
            }
            
            $offset++;
         }
         
         $albaranes = $this->albaran->all_ptefactura($offset);
      }
      
      return $pendientes;
   }
}
