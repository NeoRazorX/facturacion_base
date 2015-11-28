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

require_model('almacen.php');
require_model('articulo.php');
require_model('articulo_proveedor.php');
require_model('familia.php');
require_model('fabricante.php');
require_model('impuesto.php');
require_model('regularizacion_stock.php');
require_model('stock.php');
require_model('tarifa.php');

class ventas_articulo extends fs_controller
{
   public $allow_delete;
   public $almacen;
   public $articulo;
   public $fabricante;
   public $familia;
   public $impuesto;
   public $mostrar_boton_publicar;
   public $mostrar_tab_precios;
   public $mostrar_tab_stock;
   public $nuevos_almacenes;
   public $stocks;
   public $equivalentes;
   public $regularizaciones;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Articulo', 'ventas', FALSE, FALSE);
   }
   
   protected function private_core()
   {
      /// ¿El usuario tiene permiso para eliminar en esta página?
      $this->allow_delete = $this->user->allow_delete_on(__CLASS__);
      
      $articulo = new articulo();
      $this->almacen = new almacen();
      $this->articulo = FALSE;
      $this->impuesto = new impuesto();
      $this->fabricante= new fabricante();
      
      /**
       * Si hay alguna extensión de tipo config y texto no_tab_recios,
       * desactivamos la pestaña precios.
       */
      $this->mostrar_tab_precios = TRUE;
      foreach($this->extensions as $ext)
      {
         if($ext->type == 'config' AND $ext->text == 'no_tab_precios')
         {
            $this->mostrar_tab_precios = FALSE;
            break;
         }
      }
      
      /**
       * Si hay alguna extensión de tipo config y texto no_tab_stock,
       * desactivamos la pestaña stock.
       */
      $this->mostrar_tab_stock = TRUE;
      foreach($this->extensions as $ext)
      {
         if($ext->type == 'config' AND $ext->text == 'no_tab_stock')
         {
            $this->mostrar_tab_stock = FALSE;
            break;
         }
      }
      
      /**
       * Si hay alguna extensión de tipo config y texto no_button_publicar,
       * desactivamos el botón publicar.
       */
      $this->mostrar_boton_publicar = TRUE;
      foreach($this->extensions as $ext)
      {
         if($ext->type == 'config' AND $ext->text == 'no_button_publicar')
         {
            $this->mostrar_boton_publicar = FALSE;
            break;
         }
      }
      
      if( isset($_POST['referencia']) )
      {
         $this->articulo = $articulo->get($_POST['referencia']);
      }
      else if( isset($_GET['ref']) )
      {
         $this->articulo = $articulo->get($_GET['ref']);
      }
      
      if($this->articulo)
      {
         $this->modificar();
         $this->page->title = $this->articulo->referencia;
         
         if($this->articulo->bloqueado)
         {
            $this->new_advice("Este artículo está bloqueado / obsoleto.");
         }
         
         /**
          * Si está desactivado el control de stok en el artículo, no muestro la pestaña.
          */
         if($this->articulo->nostock)
         {
            $this->mostrar_tab_stock = FALSE;
         }
         
         $this->familia = $this->articulo->get_familia();
         if(!$this->familia)
         {
            $this->familia = new familia();
         }
         
         $this->fabricante = $this->articulo->get_fabricante();
         if(!$this->fabricante)
         {
            $this->fabricante = new fabricante();
         }
         
         $this->stocks = $this->articulo->get_stock();
         /// metemos en un array los almacenes que no tengan stock de este producto
         $this->nuevos_almacenes = array();
         foreach($this->almacen->all() as $a)
         {
            $encontrado = FALSE;
            foreach($this->stocks as $s)
            {
               if( $a->codalmacen == $s->codalmacen )
               {
                  $encontrado = TRUE;
               }
            }
            if( !$encontrado )
            {
               $this->nuevos_almacenes[] = $a;
            }
         }
         
         $reg = new regularizacion_stock();
         $this->regularizaciones = $reg->all_from_articulo($this->articulo->referencia);
         
         $this->equivalentes = $this->articulo->get_equivalentes();
      }
      else
      {
         $this->new_error_msg("Artículo no encontrado.");
      }
   }
   
   public function url()
   {
      if($this->articulo)
      {
         return $this->articulo->url();
      }
      else
         return $this->page->url();
   }
   
   private function modificar()
   {
      if( isset($_POST['pvpiva']) )
      {
         $continuar = TRUE;
         $this->articulo->set_impuesto( $_POST['codimpuesto'] );
         $this->articulo->set_pvp_iva( floatval($_POST['pvpiva']) );
         
         if( isset($_POST['preciocoste']) )
         {
            $this->articulo->costemedio = $this->articulo->preciocoste = floatval($_POST['preciocoste']);
         }
         
         if( $this->articulo->save() )
         {
            $this->new_message("Precio modificado correctamente.");
         }
         else
         {
            $this->new_error_msg("Error al modificar el precio.");
         }
      }
      else if( isset($_POST['almacen']) )
      {
         if($_POST['cantidadini'] == $_POST['cantidad'])
         {
            /// sin cambios de stock, pero aún así guardamos la ubicación
            foreach($this->articulo->get_stock() as $stock)
            {
               if($stock->codalmacen == $_POST['almacen'])
               {
                  $stock->ubicacion = $_POST['ubicacion'];
                  if( $stock->save() )
                  {
                     $this->new_message('Cambios guardados correctamente.');
                  }
               }
            }
         }
         else if( $this->articulo->set_stock($_POST['almacen'], $_POST['cantidad']) )
         {
            $this->new_message("Stock guardado correctamente.");
            
            /// añadimos la regularización
            foreach($this->articulo->get_stock() as $stock)
            {
               if($stock->codalmacen == $_POST['almacen'])
               {
                  $stock->ubicacion = $_POST['ubicacion'];
                  $stock->save();
                  
                  $regularizacion = new regularizacion_stock();
                  $regularizacion->idstock = $stock->idstock;
                  $regularizacion->cantidadini = floatval($_POST['cantidadini']);
                  $regularizacion->cantidadfin = floatval($_POST['cantidad']);
                  $regularizacion->codalmacendest = $_POST['almacen'];
                  $regularizacion->motivo = $_POST['motivo'];
                  $regularizacion->nick = $this->user->nick;
                  if( $regularizacion->save() )
                  {
                     $this->new_message('Cambios guardados correctamente.');
                  }
                  break;
               }
            }
         }
         else
         {
            $this->new_error_msg("Error al guardar el stock.");
         }
      }
      else if( isset($_GET['deletereg']) )
      {
         $reg = new regularizacion_stock();
         $regularizacion = $reg->get($_GET['deletereg']);
         if($regularizacion)
         {
            if( $regularizacion->delete() )
            {
               $this->new_message('Regularización eliminada correctamente.');
            }
            else
            {
               $this->new_error_msg('Error al eliminar la regularización.');
            }
         }
         else
         {
            $this->new_error_msg('Regularización no encontrada.');
         }
      }
      else if( isset($_POST['imagen']) )
      {
         if( is_uploaded_file($_FILES['fimagen']['tmp_name']) )
         {
            $png = ( substr( strtolower($_FILES['fimagen']['name']), -3) == 'png' );
            $this->articulo->set_imagen( file_get_contents($_FILES['fimagen']['tmp_name']), $png );
            if( $this->articulo->save() )
            {
               $this->new_message("Imagen del articulo modificada correctamente");
            }
            else
               $this->new_error_msg("¡Error al guardar la imagen del articulo!");
         }
      }
      else if( isset($_GET['delete_img']) )
      {
         $this->articulo->set_imagen(NULL);
         if( $this->articulo->save() )
         {
            $this->new_message("Imagen del articulo eliminada correctamente");
         }
         else
            $this->new_error_msg("¡Error al eliminar la imagen del articulo!");
      }
      else if( isset($_POST['referencia']) )
      {
         $this->articulo->descripcion = $_POST['descripcion'];
         
         $this->articulo->tipo = NULL;
         if($_POST['tipo'] != '')
         {
            $this->articulo->tipo = $_POST['tipo'];
         }
         
         $this->articulo->codfamilia = NULL;
         if($_POST['codfamilia'] != '')
         {
            $this->articulo->codfamilia = $_POST['codfamilia'];
         }
         
         $this->articulo->codfabricante = NULL;
         if($_POST['codfabricante'] != '')
         {
            $this->articulo->codfabricante = $_POST['codfabricante'];
         }
         
         /// ¿Existe ya ese código de barras?
         if($_POST['codbarras'] != '')
         {
            $arts = $this->articulo->search_by_codbar($_POST['codbarras']);
            if($arts)
            {
               foreach($arts as $art2)
               {
                  if($art2->referencia != $this->articulo->referencia)
                  {
                     $this->new_advice('Ya hay un artículo con este mismo código de barras. '
                             . 'En concreto, el artículo <a href="'.$art2->url().'">'.$art2->referencia.'</a>.');
                     break;
                  }
               }
            }
         }
         
         $this->articulo->codbarras = $_POST['codbarras'];
         $this->articulo->equivalencia = $_POST['equivalencia'];
         $this->articulo->bloqueado = isset($_POST['bloqueado']);
         $this->articulo->controlstock = isset($_POST['controlstock']);
         $this->articulo->nostock = isset($_POST['nostock']);
         $this->articulo->secompra = isset($_POST['secompra']);
         $this->articulo->sevende = isset($_POST['sevende']);
         $this->articulo->publico = isset($_POST['publico']);
         $this->articulo->observaciones = $_POST['observaciones'];
         $this->articulo->stockmin = floatval($_POST['stockmin']);
         $this->articulo->stockmax = floatval($_POST['stockmax']);
         if( $this->articulo->save() )
         {
            $this->new_message("Datos del articulo modificados correctamente");
            $this->articulo->set_referencia($_POST['nreferencia']);
            
            /**
             * Renombramos la referencia en el resto de tablas: lineasalbaranes, lineasfacturas...
             */
            if( $this->db->table_exists('lineasalbaranescli') )
            {
                $this->db->exec("UPDATE lineasalbaranescli SET referencia = '".$_POST['nreferencia']."' WHERE referencia = '".$_POST['referencia']."'");
            }
            
            if( $this->db->table_exists('lineasalbaranesprov') )
            {
                $this->db->exec("UPDATE lineasalbaranesprov SET referencia = '".$_POST['nreferencia']."' WHERE referencia = '".$_POST['referencia']."'");
            }
            
            if( $this->db->table_exists('lineasfacturascli') )
            {
                $this->db->exec("UPDATE lineasfacturascli SET referencia = '".$_POST['nreferencia']."' WHERE referencia = '".$_POST['referencia']."'");
            }
            
            if( $this->db->table_exists('lineasfacturasprov') )
            {
                $this->db->exec("UPDATE lineasfacturasprov SET referencia = '".$_POST['nreferencia']."' WHERE referencia = '".$_POST['referencia']."'");
            }
            
            /// esto es una personalización del plugin producción, será eliminado este código en futuras versiones.
            if( $this->db->table_exists('lineasfabricados') )
            {
                $this->db->exec("UPDATE lineasfabricados SET referencia = '".$_POST['nreferencia']."' WHERE referencia = '".$_POST['referencia']."'");
            }
         }
         else
            $this->new_error_msg("¡Error al guardar el articulo!");
      }
      else if( isset($_GET['recalcular_stock']) )
      {
         $this->calcular_stock_real();
      }
   }
   
   public function get_tarifas()
   {
      $tarlist = array();
      $tarifa = new tarifa();
      
      foreach($tarifa->all() as $tar)
      {
         $articulo = $this->articulo->get($this->articulo->referencia);
         if($articulo)
         {
            $articulo->dtopor = 0;
            $aux = array($articulo);
            $tar->set_precios($aux);
            $tarlist[] = $aux[0];
         }
      }
      
      return $tarlist;
   }
   
   public function get_articulo_proveedores()
   {
      $artprov = new articulo_proveedor();
      $alist = $artprov->all_from_ref($this->articulo->referencia);
      
      /// revismos el impuesto y la descripción
      foreach($alist as $i => $value)
      {
         $guardar = FALSE;
         if( is_null($value->codimpuesto) )
         {
            $alist[$i]->codimpuesto = $this->articulo->codimpuesto;
            $guardar = TRUE;
         }
         
         if( is_null($value->descripcion) )
         {
            $alist[$i]->descripcion = $this->articulo->descripcion;
            $guardar = TRUE;
         }
         
         if($guardar)
         {
            $alist[$i]->save();
         }
      }
      
      return $alist;
   }
   
   /**
    * Devuelve un array con los movimientos de stock del artículo.
    * @return type
    */
   public function get_movimientos()
   {
      $mlist = array();
      
      if( !isset($this->regularizaciones) )
      {
         $reg = new regularizacion_stock();
         $this->regularizaciones = $reg->all_from_articulo($this->articulo->referencia);
      }
      
      foreach($this->regularizaciones as $reg)
      {
         $mlist[] = array(
             'codalmacen' => $reg->codalmacendest,
             'origen' => 'Regularización',
             'url' => '#stock',
             'movimiento' => '-',
             'final' => $reg->cantidadfin,
             'fecha' => $reg->fecha,
             'hora' => $reg->hora
         );
      }
      
      if( $this->db->table_exists('albaranesprov') AND $this->db->table_exists('lineasalbaranesprov') )
      {
         /// buscamos el artículo en albaranes de compra
         $sql = "SELECT a.idalbaran,a.codigo,l.cantidad,a.fecha,a.hora,a.codalmacen
            FROM albaranesprov a, lineasalbaranesprov l
            WHERE a.idalbaran = l.idalbaran
            AND l.referencia = ".$this->articulo->var2str($this->articulo->referencia);
         
         $data = $this->db->select_limit($sql, 1000, 0);
         if($data)
         {
            foreach($data as $d)
            {
               $mlist[] = array(
                   'codalmacen' => $d['codalmacen'],
                   'origen' => ucfirst(FS_ALBARAN).' compra '.$d['codigo'],
                   'url' => 'index.php?page=compras_albaran&id='.intval($d['idalbaran']),
                   'movimiento' => floatval($d['cantidad']),
                   'final' => 0,
                   'fecha' => date('d-m-Y', strtotime($d['fecha'])),
                   'hora' => $d['hora']
               );
            }
         }
      }
      
      if( $this->db->table_exists('facturasprov') AND $this->db->table_exists('lineasfacturasprov') )
      {
         /// buscamos el artículo en facturas de compra
         $sql = "SELECT f.idfactura,f.codigo,l.cantidad,f.fecha,f.hora,f.codalmacen
            FROM facturasprov f, lineasfacturasprov l
            WHERE f.idfactura = l.idfactura AND l.idalbaran IS NULL
            AND l.referencia = ".$this->articulo->var2str($this->articulo->referencia);
         
         $data = $this->db->select_limit($sql, 1000, 0);
         if($data)
         {
            foreach($data as $d)
            {
               $mlist[] = array(
                   'codalmacen' => $d['codalmacen'],
                   'origen' => 'Factura compra '.$d['codigo'],
                   'url' => 'index.php?page=compras_factura&id='.intval($d['idfactura']),
                   'movimiento' => floatval($d['cantidad']),
                   'final' => 0,
                   'fecha' => date('d-m-Y', strtotime($d['fecha'])),
                   'hora' => $d['hora']
               );
            }
         }
      }
      
      if( $this->db->table_exists('albaranescli') AND $this->db->table_exists('lineasalbaranescli') )
      {
         /// buscamos el artículo en albaranes de venta
         $sql = "SELECT a.idalbaran,a.codigo,l.cantidad,a.fecha,a.hora,a.codalmacen
            FROM albaranescli a, lineasalbaranescli l
            WHERE a.idalbaran = l.idalbaran
            AND l.referencia = ".$this->articulo->var2str($this->articulo->referencia);
         
         $data = $this->db->select_limit($sql, 1000, 0);
         if($data)
         {
            foreach($data as $d)
            {
               $mlist[] = array(
                   'codalmacen' => $d['codalmacen'],
                   'origen' => ucfirst(FS_ALBARAN).' venta '.$d['codigo'],
                   'url' => 'index.php?page=ventas_albaran&id='.intval($d['idalbaran']),
                   'movimiento' => 0-floatval($d['cantidad']),
                   'final' => 0,
                   'fecha' => date('d-m-Y', strtotime($d['fecha'])),
                   'hora' => $d['hora']
               );
            }
         }
      }
      
      if( $this->db->table_exists('facturascli') AND $this->db->table_exists('lineasfacturascli') )
      {
         /// buscamos el artículo en facturas de venta
         $sql = "SELECT f.idfactura,f.codigo,l.cantidad,f.fecha,f.hora,f.codalmacen
            FROM facturascli f, lineasfacturascli l
            WHERE f.idfactura = l.idfactura AND l.idalbaran IS NULL
            AND l.referencia = ".$this->articulo->var2str($this->articulo->referencia);
         
         $data = $this->db->select_limit($sql, 1000, 0);
         if($data)
         {
            foreach($data as $d)
            {
               $mlist[] = array(
                   'codalmacen' => $d['codalmacen'],
                   'origen' => 'Factura venta '.$d['codigo'],
                   'url' => 'index.php?page=ventas_factura&id='.intval($d['idfactura']),
                   'movimiento' => 0-floatval($d['cantidad']),
                   'final' => 0,
                   'fecha' => date('d-m-Y', strtotime($d['fecha'])),
                   'hora' => $d['hora']
               );
            }
         }
      }
      
      /// ordenamos por fecha y hora
      usort($mlist, function($a,$b) {
         if( strtotime($a['fecha'].' '.$a['hora']) == strtotime($b['fecha'].' '.$b['hora']) )
         {
            return 0;
         }
         else if( strtotime($a['fecha'].' '.$a['hora']) < strtotime($b['fecha'].' '.$b['hora']) )
         {
            return -1;
         }
         else
            return 1;
      });
      
      /// recalculamos
      $inicial = 0;
      foreach( array_reverse($mlist) as $i => $value)
      {
         if($value['movimiento'] == '-')
         {
            $inicial = $value['final'];
         }
         else
         {
            $inicial -= $value['movimiento'];
         }
      }
      
      $total = max( array($inicial, 0) );
      foreach($mlist as $i => $value)
      {
         if($value['movimiento'] == '-')
         {
            $total = $value['final'];
         }
         else
            $total += $value['movimiento'];
         
         $mlist[$i]['final'] = $total;
      }
      
      return $mlist;
   }
   
   /**
    * Calcula el stock real del artículo en función de los movimientos y regularizaciones
    */
   private function calcular_stock_real()
   {
      $movimientos = $this->get_movimientos();
      
      foreach($this->almacen->all() as $alm)
      {
         $total = 0;
         foreach($movimientos as $mov)
         {
            if($mov['codalmacen'] == $alm->codalmacen)
            {
               $total = $mov['final'];
            }
         }
         
         if( $this->articulo->set_stock($alm->codalmacen, $total) )
         {
            $this->new_message('Recarculado el stock del almacén '.$alm->codalmacen.'.');
         }
         else
         {
            $this->new_error_msg('Error al recarcular el stock del almacén '.$alm->codalmacen.'.');
         }
      }
      
      $this->new_message("Stock actualizado.");
   }
}
