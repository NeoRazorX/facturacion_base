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

require_model('albaran_proveedor.php');
require_model('articulo.php');
require_model('asiento.php');
require_model('asiento_factura.php');
require_model('divisa.php');
require_model('ejercicio.php');
require_model('factura_proveedor.php');
require_model('familia.php');
require_model('forma_pago.php');
require_model('impuesto.php');
require_model('partida.php');
require_model('proveedor.php');
require_model('regularizacion_iva.php');
require_model('serie.php');
require_model('subcuenta.php');

class compras_albaran extends fs_controller
{
   public $agente;
   public $albaran;
   public $allow_delete;
   public $divisa;
   public $ejercicio;
   public $familia;
   public $forma_pago;
   public $impuesto;
   public $nuevo_albaran_url;
   public $proveedor;
   public $proveedor_s;
   public $serie;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, FS_ALBARAN.' de proveedor', 'compras', FALSE, FALSE);
   }
   
   protected function process()
   {
      $this->ppage = $this->page->get('compras_albaranes');
      $this->agente = FALSE;
      
      $albaran = new albaran_proveedor();
      $this->albaran = FALSE;
      $this->divisa = new divisa();
      $this->ejercicio = new ejercicio();
      $this->familia = new familia();
      $this->forma_pago = new forma_pago();
      $this->impuesto = new impuesto();
      $this->proveedor = new proveedor();
      $this->proveedor_s = FALSE;
      $this->serie = new serie();
      
      /// ¿El usuario tiene permiso para eliminar en esta página?
      $this->allow_delete = $this->user->allow_delete_on(__CLASS__);
      
      /// comprobamos si el usuario tiene acceso a nueva_compra
      $this->nuevo_albaran_url = FALSE;
      if( $this->user->have_access_to('nueva_compra', FALSE) )
      {
         $nuevoalbp = $this->page->get('nueva_compra');
         if($nuevoalbp)
            $this->nuevo_albaran_url = $nuevoalbp->url();
      }
      
      if( isset($_POST['idalbaran']) )
      {
         $this->albaran = $albaran->get($_POST['idalbaran']);
         $this->modificar();
      }
      else if( isset($_GET['id']) )
      {
         $this->albaran = $albaran->get($_GET['id']);
      }
      
      if($this->albaran)
      {
         $this->page->title = $this->albaran->codigo;
         
         /// cargamos el agente
         if( !is_null($this->albaran->codagente) )
         {
            $agente = new agente();
            $this->agente = $agente->get($this->albaran->codagente);
         }
         
         /// cargamos el proveedor
         $this->proveedor_s = $this->proveedor->get($this->albaran->codproveedor);
         
         /// comprobamos el albarán
         $this->albaran->full_test();
         
         if( isset($_GET['facturar']) AND isset($_GET['petid']) AND $this->albaran->ptefactura )
         {
            if( $this->duplicated_petition($_GET['petid']) )
            {
               $this->new_error_msg('Petición duplicada. Evita hacer doble clic sobre los botones.');
            }
            else
               $this->generar_factura();
         }
         else if( isset($_POST['actualizar_precios']) )
         {
            $this->actualizar_precios();
         }
         else if( isset($_GET['forze_fecha']) )
         {
            $this->forzar_fecha();
         }
      }
      else
         $this->new_error_msg("¡".FS_ALBARAN." de proveedor no encontrado!");
   }
   
   public function url()
   {
      if( !isset($this->albaran) )
      {
         return parent::url();
      }
      else if($this->albaran)
      {
         return $this->albaran->url();
      }
      else
         return $this->page->url();
   }
   
   private function modificar()
   {
      $error = FALSE;
      $this->albaran->numproveedor = $_POST['numproveedor'];
      $this->albaran->observaciones = $_POST['observaciones'];
      
      if($this->albaran->ptefactura)
      {
         $eje0 = $this->ejercicio->get_by_fecha($_POST['fecha']);
         if($eje0->codejercicio == $this->albaran->codejercicio)
         {
            $this->albaran->fecha = $_POST['fecha'];
            $this->albaran->hora = $_POST['hora'];
         }
         else
         {
            $error = TRUE;
            $this->new_error_msg('La fecha seleccionada está fuere del rando del ejercicio '.$this->albaran->codejercicio
                    .'. Si deseas asignar la fecha '.$_POST['fecha'].' pulsa <a href="'.$this->url().'&forze_fecha='.$_POST['fecha'].'">aquí</a>'
                    . ' y se asignará un nuevo código y un nuevo número al '.FS_ALBARAN.'.');
         }
         
         /// ¿Cambiamos el proveedor?
         if($_POST['proveedor'] != $this->albaran->codproveedor)
         {
            $proveedor = $this->proveedor->get($_POST['proveedor']);
            if($proveedor)
            {
               $this->albaran->codproveedor = $proveedor->codproveedor;
               $this->albaran->nombre = $proveedor->razonsocial;
               $this->albaran->cifnif = $proveedor->cifnif;
            }
            else
               die('No se ha encontrado el proveedor');
         }
         else
            $proveedor = $this->proveedor->get($this->albaran->codproveedor);
         
         $serie = $this->serie->get($this->albaran->codserie);
         
         /// ¿cambiamos la serie?
         if($_POST['serie'] != $this->albaran->codserie)
         {
            $serie2 = $this->serie->get($_POST['serie']);
            if($serie2)
            {
               $this->albaran->codserie = $serie2->codserie;
               $this->albaran->irpf = $serie2->irpf;
               $this->albaran->new_codigo();
               
               $serie = $serie2;
            }
         }
         
         $this->albaran->codpago = $_POST['forma_pago'];
         
         /// ¿Cambiamos la divisa?
         if($_POST['divisa'] != $this->albaran->coddivisa)
         {
            $divisa = $this->divisa->get($_POST['divisa']);
            if($divisa)
            {
               $this->albaran->coddivisa = $divisa->coddivisa;
               $this->albaran->tasaconv = $divisa->tasaconv_compra;
            }
         }
         else if($_POST['tasaconv'] != '')
         {
            $this->albaran->tasaconv = floatval($_POST['tasaconv']);
         }
         
         if( isset($_POST['numlineas']) )
         {
            $numlineas = intval($_POST['numlineas']);
            
            $this->albaran->neto = 0;
            $this->albaran->totaliva = 0;
            $this->albaran->totalirpf = 0;
            $this->albaran->totalrecargo = 0;
            $lineas = $this->albaran->get_lineas();
            $articulo = new articulo();
            
            /// eliminamos las líneas que no encontremos en el $_POST
            foreach($lineas as $l)
            {
               $encontrada = FALSE;
               for($num = 0; $num <= $numlineas; $num++)
               {
                  if( isset($_POST['idlinea_'.$num]) )
                  {
                     if($l->idlinea == intval($_POST['idlinea_'.$num]))
                     {
                        $encontrada = TRUE;
                        break;
                     }
                  }
               }
               if(!$encontrada)
               {
                  if( $l->delete() )
                  {
                     /// actualizamos el stock
                     $art0 = $articulo->get($l->referencia);
                     if($art0)
                        $art0->sum_stock($this->albaran->codalmacen, 0 - $l->cantidad);
                  }
                  else
                     $this->new_error_msg("¡Imposible eliminar la línea del artículo ".$l->referencia."!");
               }
            }
            
            /// modificamos y/o añadimos las demás líneas
            for($num = 0; $num <= $numlineas; $num++)
            {
               $encontrada = FALSE;
               if( isset($_POST['idlinea_'.$num]) )
               {
                  foreach($lineas as $k => $value)
                  {
                     /// modificamos la línea
                     if($value->idlinea == intval($_POST['idlinea_'.$num]))
                     {
                        $encontrada = TRUE;
                        $cantidad_old = $value->cantidad;
                        $lineas[$k]->cantidad = floatval($_POST['cantidad_'.$num]);
                        $lineas[$k]->pvpunitario = floatval($_POST['pvp_'.$num]);
                        $lineas[$k]->dtopor = floatval($_POST['dto_'.$num]);
                        $lineas[$k]->pvpsindto = ($value->cantidad * $value->pvpunitario);
                        $lineas[$k]->pvptotal = ($value->cantidad * $value->pvpunitario * (100 - $value->dtopor)/100);
                        $lineas[$k]->descripcion = $_POST['desc_'.$num];
                        
                        $lineas[$k]->codimpuesto = NULL;
                        $lineas[$k]->iva = 0;
                        $lineas[$k]->recargo = 0;
                        $lineas[$k]->irpf = floatval($_POST['irpf_'.$num]);
                        if( !$serie->siniva AND $proveedor->regimeniva != 'Exento' )
                        {
                           $imp0 = $this->impuesto->get_by_iva($_POST['iva_'.$num]);
                           if($imp0)
                              $lineas[$k]->codimpuesto = $imp0->codimpuesto;
                           
                           $lineas[$k]->iva = floatval($_POST['iva_'.$num]);
                           $lineas[$k]->recargo = floatval($_POST['recargo_'.$num]);
                        }
                        
                        if( $lineas[$k]->save() )
                        {
                           $this->albaran->neto += $value->pvptotal;
                           $this->albaran->totaliva += $value->pvptotal * $value->iva/100;
                           $this->albaran->totalirpf += $value->pvptotal * $value->irpf/100;
                           $this->albaran->totalrecargo += $value->pvptotal * $value->recargo/100;
                           
                           if($lineas[$k]->cantidad != $cantidad_old)
                           {
                              /// actualizamos el stock
                              $art0 = $articulo->get($value->referencia);
                              if($art0)
                                 $art0->sum_stock($this->albaran->codalmacen, $lineas[$k]->cantidad - $cantidad_old);
                           }
                        }
                        else
                           $this->new_error_msg("¡Imposible modificar la línea del artículo ".$value->referencia."!");
                        break;
                     }
                  }
                  
                  /// añadimos la línea
                  if(!$encontrada AND intval($_POST['idlinea_'.$num]) == -1 AND isset($_POST['referencia_'.$num]))
                  {
                     $linea = new linea_albaran_proveedor();
                     $linea->idalbaran = $this->albaran->idalbaran;
                     $linea->descripcion = $_POST['desc_'.$num];
                     
                     if( !$serie->siniva AND $proveedor->regimeniva != 'Exento' )
                     {
                        $imp0 = $this->impuesto->get_by_iva($_POST['iva_'.$num]);
                        if($imp0)
                           $linea->codimpuesto = $imp0->codimpuesto;
                        
                        $linea->iva = floatval($_POST['iva_'.$num]);
                        $linea->recargo = floatval($_POST['recargo_'.$num]);
                     }
                     
                     $linea->irpf = floatval($_POST['irpf_'.$num]);
                     $linea->cantidad = floatval($_POST['cantidad_'.$num]);
                     $linea->pvpunitario = floatval($_POST['pvp_'.$num]);
                     $linea->dtopor = floatval($_POST['dto_'.$num]);
                     $linea->pvpsindto = ($linea->cantidad * $linea->pvpunitario);
                     $linea->pvptotal = ($linea->cantidad * $linea->pvpunitario * (100 - $linea->dtopor)/100);
                     
                     $art0 = $articulo->get( $_POST['referencia_'.$num] );
                     if($art0)
                     {
                        $linea->referencia = $art0->referencia;
                     }
                     
                     if( $linea->save() )
                     {
                        if($art0)
                        {
                           /// actualizamos el stock
                           $art0->sum_stock($this->albaran->codalmacen, $linea->cantidad);
                        }
                        
                        $this->albaran->neto += $linea->pvptotal;
                        $this->albaran->totaliva += $linea->pvptotal * $linea->iva/100;
                        $this->albaran->totalirpf += $linea->pvptotal * $linea->irpf/100;
                        $this->albaran->totalrecargo += $linea->pvptotal * $linea->recargo/100;
                     }
                     else
                        $this->new_error_msg("¡Imposible guardar la línea del artículo ".$linea->referencia."!");
                  }
               }
            }
            
            /// redondeamos
            $this->albaran->neto = round($this->albaran->neto, FS_NF0);
            $this->albaran->totaliva = round($this->albaran->totaliva, FS_NF0);
            $this->albaran->totalirpf = round($this->albaran->totalirpf, FS_NF0);
            $this->albaran->totalrecargo = round($this->albaran->totalrecargo, FS_NF0);
            $this->albaran->total = $this->albaran->neto + $this->albaran->totaliva - $this->albaran->totalirpf + $this->albaran->totalrecargo;
            
            if( abs(floatval($_POST['atotal']) - $this->albaran->total) >= .02 )
            {
               $this->new_error_msg("El total difiere entre el controlador y la vista (".$this->albaran->total.
                       " frente a ".$_POST['atotal']."). Debes informar del error.");
            }
         }
      }
      
      if( $this->albaran->save() )
      {
         if(!$error)
         {
            $this->new_message(ucfirst(FS_ALBARAN)." modificado correctamente.");
         }
         
         $this->new_change(ucfirst(FS_ALBARAN).' Proveedor '.$this->albaran->codigo, $this->albaran->url());
      }
      else
         $this->new_error_msg("¡Imposible modificar el ".FS_ALBARAN."!");
   }
   
   private function forzar_fecha()
   {
      $eje0 = $this->ejercicio->get_by_fecha($_GET['forze_fecha']);
      if($eje0)
      {
         $this->albaran->fecha = $_GET['forze_fecha'];
         $this->albaran->codejercicio = $eje0->codejercicio;
         $this->albaran->new_codigo();
         
         if( $this->albaran->save() )
         {
            $this->new_message(ucfirst(FS_ALBARAN)." modificado correctamente.");
         }
         else
            $this->new_error_msg("¡Imposible modificar el ".FS_ALBARAN."!");
      }
      else
         $this->new_error_msg('No se ha posido asignar un ejercicio a esta fecha.');
   }
   
   private function generar_factura()
   {
      $factura = new factura_proveedor();
      $factura->automatica = TRUE;
      $factura->editable = FALSE;
      $factura->cifnif = $this->albaran->cifnif;
      $factura->codalmacen = $this->albaran->codalmacen;
      $factura->coddivisa = $this->albaran->coddivisa;
      $factura->tasaconv = $this->albaran->tasaconv;
      $factura->codejercicio = $this->albaran->codejercicio;
      $factura->codpago = $this->albaran->codpago;
      $factura->codproveedor = $this->albaran->codproveedor;
      $factura->codserie = $this->albaran->codserie;
      $factura->irpf = $this->albaran->irpf;
      $factura->neto = $this->albaran->neto;
      $factura->nombre = $this->albaran->nombre;
      $factura->numproveedor = $this->albaran->numproveedor;
      $factura->observaciones = $this->albaran->observaciones;
      $factura->recfinanciero = $this->albaran->recfinanciero;
      $factura->total = $this->albaran->total;
      $factura->totalirpf = $this->albaran->totalirpf;
      $factura->totaliva = $this->albaran->totaliva;
      $factura->totalrecargo = $this->albaran->totalrecargo;
      $factura->codagente = $this->albaran->codagente;
      
      /// comprobamos la forma de pago para saber si hay que marcar la factura como pagada
      $forma0 = new forma_pago();
      $formapago = $forma0->get($factura->codpago);
      if($formapago)
      {
         if($formapago->genrecibos == 'Pagados')
         {
            $factura->pagada = TRUE;
         }
      }
      
      /// asignamos la mejor fecha posible, pero dentro del ejercicio
      $eje0 = $this->ejercicio->get($factura->codejercicio);
      $factura->fecha = $eje0->get_best_fecha($factura->fecha);
      
      $regularizacion = new regularizacion_iva();
      
      if( !$eje0->abierto() )
      {
         $this->new_error_msg("El ejercicio está cerrado.");
      }
      else if( $regularizacion->get_fecha_inside($factura->fecha) )
      {
         $this->new_error_msg("El IVA de ese periodo ya ha sido regularizado. No se pueden añadir más facturas en esa fecha.");
      }
      else if( $factura->save() )
      {
         $continuar = TRUE;
         foreach($this->albaran->get_lineas() as $l)
         {
            $linea = new linea_factura_proveedor();
            $linea->cantidad = $l->cantidad;
            $linea->codimpuesto = $l->codimpuesto;
            $linea->descripcion = $l->descripcion;
            $linea->dtopor = $l->dtopor;
            $linea->idalbaran = $l->idalbaran;
            $linea->idfactura = $factura->idfactura;
            $linea->irpf = $l->irpf;
            $linea->iva = $l->iva;
            $linea->pvpsindto = $l->pvpsindto;
            $linea->pvptotal = $l->pvptotal;
            $linea->pvpunitario = $l->pvpunitario;
            $linea->recargo = $l->recargo;
            $linea->referencia = $l->referencia;
            if( !$linea->save() )
            {
               $continuar = FALSE;
               $this->new_error_msg("¡Imposible guardar la línea el artículo ".$linea->referencia."! ");
               break;
            }
         }
         
         if( $continuar )
         {
            $this->albaran->idfactura = $factura->idfactura;
            $this->albaran->ptefactura = FALSE;
            if( $this->albaran->save() )
            {
               $this->generar_asiento($factura);
            }
            else
            {
               $this->new_error_msg("¡Imposible vincular el ".FS_ALBARAN." con la nueva factura!");
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
   
   private function actualizar_precios()
   {
      $articulo = new articulo();
      $lineas = $this->albaran->get_lineas();
      
      foreach($lineas as $linea)
      {
         for($i = 0; $i < count($lineas); $i++)
         {
            if( !isset($_POST['referencia_'.$i]) )
            {
               
            }
            else if($_POST['referencia_'.$i] == $linea->referencia)
            {
               $art0 = $articulo->get($_POST['referencia_'.$i]);
               if($art0)
               {
                  if( isset($_POST['update_all']) )
                  {
                     $art0->descripcion = $linea->descripcion;
                     
                     if($_POST['codbar_'.$i] != '')
                     {
                        $art0->codbarras = $_POST['codbar_'.$i];
                     }
                  }
                  
                  $art0->set_impuesto($linea->codimpuesto);
                  $art0->set_pvp_iva($_POST['pvpiva_'.$i]);
                  if( !$art0->save() )
                     $this->new_error_msg('Imposible actualizar el artículo '.$art0->referencia);
               }
               
               break;
            }
         }
      }
      
      $this->new_message('Precios actualizados.');
   }
}
