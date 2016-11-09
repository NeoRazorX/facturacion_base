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

require_model('albaran_cliente.php');
require_model('asiento.php');
require_model('asiento_factura.php');
require_model('cliente.php');
require_model('divisa.php');
require_model('ejercicio.php');
require_model('factura_cliente.php');
require_model('forma_pago.php');
require_model('partida.php');
require_model('regularizacion_iva.php');
require_model('serie.php');
require_model('subcuenta.php');

class ventas_agrupar_albaranes extends fs_controller
{
   public $albaran;
   public $cliente;
   public $coddivisa;
   public $codserie;
   public $desde;
   public $divisa;
   public $hasta;
   public $neto;
   public $observaciones;
   public $resultados;
   public $serie;
   public $total;
   
   private $ejercicio;
   private $forma_pago;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Agrupar '.FS_ALBARANES, 'ventas', FALSE, FALSE);
   }
   
   protected function private_core()
   {
      $this->albaran = new albaran_cliente();
      $this->cliente = FALSE;
      $this->divisa = new divisa();
      $this->ejercicio = new ejercicio();
      $this->forma_pago = new forma_pago();
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
      
      $this->observaciones = '';
      if( isset($_REQUEST['observaciones']) )
      {
         $this->observaciones = $_REQUEST['observaciones'];
      }
      
      if( isset($_REQUEST['buscar_cliente']) )
      {
         $this->buscar_cliente();
      }
      else if( isset($_POST['idalbaran']) )
      {
         $this->cliente = new cliente();
         $this->agrupar();
      }
      else if( isset($_REQUEST['codcliente']) )
      {
         $cli0 = new cliente();
         $this->cliente = $cli0->get($_REQUEST['codcliente']);
         
         if($this->cliente)
         {
            $this->resultados = $this->albaran->search_from_cliente(
                    $this->cliente->codcliente,
                    $this->desde,
                    $this->hasta,
                    $this->codserie,
                    $this->observaciones,
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
   
   private function buscar_cliente()
   {
      /// desactivamos la plantilla HTML
      $this->template = FALSE;
      
      $cliente = new cliente();
      $json = array();
      foreach($cliente->search($_REQUEST['buscar_cliente']) as $cli)
      {
         $json[] = array('value' => $cli->razonsocial, 'data' => $cli->codcliente);
      }
      
      header('Content-Type: application/json');
      echo json_encode( array('query' => $_REQUEST['buscar_cliente'], 'suggestions' => $json) );
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
            if( !$alb->ptefactura )
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
   
   /**
    * Genera una factura a partir de un array de albaranes.
    * @param albaran_cliente $albaranes
    */
   private function generar_factura($albaranes)
   {
      $continuar = TRUE;
      
      $factura = new factura_cliente();
      $factura->codagente = $this->user->codagente;
      $factura->codalmacen = $albaranes[0]->codalmacen;
      $factura->coddivisa = $albaranes[0]->coddivisa;
      $factura->tasaconv = $albaranes[0]->tasaconv;
      $factura->codpago = $albaranes[0]->codpago;
      $factura->codserie = $albaranes[0]->codserie;
      $factura->irpf = $albaranes[0]->irpf;
      $factura->numero2 = $albaranes[0]->numero2;
      $factura->observaciones = $albaranes[0]->observaciones;
      $factura->apartado = $albaranes[0]->apartado;
      $factura->cifnif = $albaranes[0]->cifnif;
      $factura->ciudad = $albaranes[0]->ciudad;
      $factura->codcliente = $albaranes[0]->codcliente;
      $factura->coddir = $albaranes[0]->coddir;
      $factura->codpais = $albaranes[0]->codpais;
      $factura->codpostal = $albaranes[0]->codpostal;
      $factura->direccion = $albaranes[0]->direccion;
      $factura->nombrecliente = $albaranes[0]->nombrecliente;
      $factura->provincia = $albaranes[0]->provincia;
      
      $factura->envio_apellidos = $albaranes[0]->envio_apellidos;
      $factura->envio_ciudad = $albaranes[0]->envio_ciudad;
      $factura->envio_codigo = $albaranes[0]->envio_codigo;
      $factura->envio_codpostal = $albaranes[0]->envio_codpostal;
      $factura->envio_codtrans = $albaranes[0]->envio_codtrans;
      $factura->envio_direccion = $albaranes[0]->envio_direccion;
      $factura->envio_nombre = $albaranes[0]->envio_nombre;
      $factura->envio_provincia = $albaranes[0]->envio_provincia;
      
      /// obtenemos los datos actuales del cliente, por si ha habido cambios
      $cliente = $this->cliente->get($albaranes[0]->codcliente);
      if($cliente)
      {
         foreach($cliente->get_direcciones() as $dir)
         {
            /**
             * Si la dirección es de facturación y ha sido modificada posteriormente
             * al albarán, la usamos para la factura, porque está más actualizada.
             */
            if( $dir->domfacturacion AND strtotime($dir->fecha) > strtotime($albaranes[0]->fecha) )
            {
               $factura->apartado = $dir->apartado;
               $factura->cifnif = $cliente->cifnif;
               $factura->ciudad = $dir->ciudad;
               $factura->codcliente = $cliente->codcliente;
               $factura->coddir = $dir->id;
               $factura->codpais = $dir->codpais;
               $factura->codpostal = $dir->codpostal;
               $factura->direccion = $dir->direccion;
               $factura->nombrecliente = $cliente->razonsocial;
               $factura->provincia = $dir->provincia;
               break;
            }
         }
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
         
         $factura->vencimiento = Date('d-m-Y', strtotime($factura->fecha.' '.$formapago->vencimiento));
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
               $n = new linea_factura_cliente();
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
         if( $asiento_factura->generar_asiento_venta($factura) )
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
      
      $this->new_change('Factura '.$factura->codigo, $factura->url(), TRUE);
   }
   
   private function share_extensions()
   {
      $extension = array(
          'name' => 'agrupar_albaranes',
          'page_from' => __CLASS__,
          'page_to' => 'ventas_albaranes',
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
            if($alb->codcliente)
            {
               /// Comprobamos si el cliente ya está en la lista.
               $encontrado = FALSE;
               foreach($pendientes as $i => $pe)
               {
                  if($alb->codcliente == $pe['codcliente'] AND $alb->codserie == $pe['codserie'] AND $alb->coddivisa == $pe['coddivisa'])
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
                      'codcliente' => $alb->codcliente,
                      'nombre' => $alb->nombrecliente,
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
