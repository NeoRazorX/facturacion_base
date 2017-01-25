<?php
/*
 * This file is part of FacturaScripts
 * Copyright (C) 2017  Carlos Garcia Gomez  neorazorx@gmail.com
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
require_model('articulo_traza.php');
require_model('factura_proveedor.php');

/**
 * Description of compras_trazabilidad
 *
 * @author Carlos Garcia Gomez
 */
class compras_trazabilidad extends fs_controller
{
   public $documento;
   public $lineas;
   public $tab;
   public $tipo;
   
   private $articulo;
   private $articulo_traza;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Trazabilidad', 'compras', FALSE, FALSE);
   }
   
   protected function private_core()
   {
      $this->documento = FALSE;
      if( isset($_GET['doc']) AND isset($_GET['id']) )
      {
         if($_GET['doc'] == 'albaran')
         {
            $alb = new albaran_proveedor();
            $this->documento = $alb->get($_GET['id']);
            $this->tipo = FS_ALBARAN.' de compra';
         }
         else if($_GET['doc'] == 'factura')
         {
            $fac = new factura_proveedor();
            $this->documento = $fac->get($_GET['id']);
            $this->tipo = 'factura de compra';
         }
      }
      
      $this->tab = isset($_GET['tab']);
      
      if($this->documento)
      {
         $this->articulo = new articulo();
         $this->articulo_traza = new articulo_traza();
         
         if( isset($_POST['id_0']) )
         {
            $this->modificar();
         }
         
         $this->get_lineas();
         
         /**
          * Cargamos las extensiones solamente si se usa trazabilidad,
          * hasta entonces no aparecerá la pestaña.
          */
         $this->share_extensions();
      }
      else
      {
         $this->new_error_msg('Documento no encontrado.', 'error', FALSE, FALSE);
      }
   }
   
   private function share_extensions()
   {
      $fsext = new fs_extension();
      $fsext->name = 'tab_compras_trazabilidad_fac';
      $fsext->from = __CLASS__;
      $fsext->to = 'compras_factura';
      $fsext->type = 'tab';
      $fsext->text = '<i class="fa fa-code-fork" aria-hidden="true"></i>'
              . '<span class="hidden-xs">&nbsp;Trazabilidad</span>';
      $fsext->params = '&doc=factura&tab=TRUE';
      $fsext->save();
      
      $fsext2 = new fs_extension();
      $fsext2->name = 'tab_compras_trazabilidad_alb';
      $fsext2->from = __CLASS__;
      $fsext2->to = 'compras_albaran';
      $fsext2->type = 'tab';
      $fsext2->text = '<i class="fa fa-code-fork" aria-hidden="true"></i>'
              . '<span class="hidden-xs">&nbsp;Trazabilidad</span>';
      $fsext2->params = '&doc=albaran&tab=TRUE';
      $fsext2->save();
   }
   
   public function url()
   {
      if($this->documento)
      {
         $extra = '';
         if($this->tab)
         {
            $extra = '&tab=TRUE';
         }
         
         if( get_class_name($this->documento) == 'albaran_proveedor' )
         {
            return parent::url().'&doc=albaran&id='.$this->documento->idalbaran.$extra;
         }
         else
            return parent::url().'&doc=factura&id='.$this->documento->idfactura.$extra;
      }
      else
         return parent::url();
   }
   
   private function get_lineas()
   {
      $this->lineas = array();
      
      /**
       * ¿Existen ya las lineas de trazabilidad para este documento?
       * Miramos linea a linea para ver si hay que crear o eliminar.
       */
      $nuevas = FALSE;
      foreach($this->documento->get_lineas() as $lindoc)
      {
         if($lindoc->referencia)
         {
            $articulo = $this->articulo->get($lindoc->referencia);
            if($articulo)
            {
               if($articulo->trazabilidad)
               {
                  $num = 0;
                  if( get_class_name($this->documento) == 'albaran_proveedor' )
                  {
                     foreach($this->articulo_traza->all_from_linea('idlalbcompra', $lindoc->idlinea) as $traza)
                     {
                        if($num > $lindoc->cantidad)
                        {
                           /// si hay más líneas de trazabilidad que cantidad, las eliminamos
                           $traza->delete();
                        }
                        else
                        {
                           $this->lineas[] = $traza;
                           $num++;
                        }
                     }
                  }
                  else
                  {
                     /// primero comprobamos albaranes previos
                     if($lindoc->idlineaalbaran)
                     {
                        foreach($this->articulo_traza->all_from_linea('idlalbcompra', $lindoc->idlineaalbaran) as $traza)
                        {
                           if( is_null($traza->idlfaccompra) )
                           {
                              $traza->idlfaccompra = $lindoc->idlinea;
                              $traza->save();
                           }
                        }
                     }
                     
                     /// ahora comprobamos de la factura
                     foreach($this->articulo_traza->all_from_linea('idlfaccompra', $lindoc->idlinea) as $traza)
                     {
                        if($num > $lindoc->cantidad)
                        {
                           $traza->delete();
                        }
                        else
                        {
                           $this->lineas[] = $traza;
                           $num++;
                        }
                     }
                  }
                  
                  /// creamos las lineas que falten
                  while($num < $lindoc->cantidad)
                  {
                     $traza = new articulo_traza();
                     $traza->referencia = $articulo->referencia;
                     $traza->fecha_entrada = $this->documento->fecha;
                     
                     if( get_class_name($this->documento) == 'albaran_proveedor' )
                     {
                        $traza->idlalbcompra = $lindoc->idlinea;
                     }
                     else
                     {
                        $traza->idlfaccompra = $lindoc->idlinea;
                     }
                     
                     if( $traza->save() )
                     {
                        $this->lineas[] = $traza;
                     }
                     $num++;
                     $nuevas = TRUE;
                  }
               }
            }
         }
      }
      
      /// si hay lineas nuevas reordenamos
      if($nuevas)
      {
         usort($this->lineas, function($a, $b) {
            if($a->id > $b->id)
            {
               return -1;
            }
            else if($a->id < $b->id)
            {
               return 1;
            }
            else
            {
               return 0;
            }
         });
      }
   }
   
   private function modificar()
   {
      $ok = TRUE;
      
      $this->get_lineas();
      foreach($this->lineas as $i => $value)
      {
         if($_POST['id_'.$i] == $value->id)
         {
            $this->lineas[$i]->numserie = NULL;
            if($_POST['numserie_'.$i])
            {
               $this->lineas[$i]->numserie = $_POST['numserie_'.$i];
            }
            
            $this->lineas[$i]->lote = NULL;
            if($_POST['lote_'.$i])
            {
               $this->lineas[$i]->lote = $_POST['lote_'.$i];
            }
            
            if( is_null($this->lineas[$i]->numserie) AND is_null($this->lineas[$i]->lote) )
            {
               if($ok)
               {
                  $this->new_error_msg('En la <b>linea '.($i+1).'</b> debes escribir un número de serie'
                          . ' o un lote o ambos, pero algo debes escribir.');
               }
               $ok = FALSE;
            }
            else if( !$this->lineas[$i]->save() )
            {
               $ok = FALSE;
            }
         }
         else
         {
            $this->new_error_msg('Error al comprobar los datos del formulario.');
            $ok = FALSE;
            break;
         }
      }
      
      if($ok)
      {
         if($this->tab)
         {
            $this->new_message('Datos guardados correctamente.');
         }
         else
         {
            header('Location: '.$this->documento->url());
         }
      }
   }
}
