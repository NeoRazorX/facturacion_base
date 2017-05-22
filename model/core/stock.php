<?php
/*
 * This file is part of facturacion_base
 * Copyright (C) 2013-2017  Carlos Garcia Gomez  neorazorx@gmail.com
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

namespace FacturaScripts\model;

require_model('almacen.php');
require_model('articulo.php');

/**
 * La cantidad en inventario de un artículo en un almacén concreto.
 *
 * @author Carlos García Gómez <neorazorx@gmail.com>
 */
class stock extends \fs_model
{
   /**
    * Clave primaria.
    * @var type
    */
   public $idstock;

   public $codalmacen;

   public $referencia;

   public $nombre;

   public $cantidad;

   public $reservada;

   public $disponible;

   public $pterecibir;

   public $stockmin;

   public $stockmax;

   public $cantidadultreg;

   public $ubicacion;

   public function __construct($s=FALSE)
   {
      parent::__construct('stocks');
      if($s)
      {
         $this->idstock = $this->intval($s['idstock']);
         $this->codalmacen = $s['codalmacen'];
         $this->referencia = $s['referencia'];
         $this->nombre = $s['nombre'];
         $this->cantidad = floatval($s['cantidad']);
         $this->reservada = floatval($s['reservada']);
         $this->disponible = floatval($s['disponible']);
         $this->pterecibir = floatval($s['pterecibir']);
         $this->stockmin = floatval($s['stockmin']);
         $this->stockmax = floatval($s['stockmax']);
         $this->cantidadultreg = floatval($s['cantidadultreg']);
         $this->ubicacion = $s['ubicacion'];
      }
      else
      {
         $this->idstock = NULL;
         $this->codalmacen = NULL;
         $this->referencia = NULL;
         $this->nombre = '';
         $this->cantidad = 0;
         $this->reservada = 0;
         $this->disponible = 0;
         $this->pterecibir = 0;
         $this->stockmin = 0;
         $this->stockmax = 0;
         $this->cantidadultreg = 0;
         $this->ubicacion = NULL;
      }
   }

   protected function install()
   {
      /**
       * La tabla stocks tiene claves ajenas a artículos y almacenes,
       * por eso creamos un objeto de cada uno, para forzar la comprobación
       * de las tablas.
       */
      new \almacen();
      new \articulo();

      return '';
   }

   public function nombre()
   {
      $al0 = new \almacen();
      $almacen = $al0->get($this->codalmacen);
      if($almacen)
      {
         $this->nombre = $almacen->nombre;
      }

      return $this->nombre;
   }

   public function set_cantidad($c = 0)
   {
      $this->cantidad = floatval($c);

      if($this->cantidad < 0 AND !FS_STOCK_NEGATIVO)
      {
         $this->cantidad = 0;
      }

      $this->disponible = $this->cantidad - $this->reservada;
   }

   public function sum_cantidad($c = 0)
   {
      /// convertimos a flot por si acaso nos ha llegado un string
      $this->cantidad += floatval($c);

      if($this->cantidad < 0 AND !FS_STOCK_NEGATIVO)
      {
         $this->cantidad = 0;
      }

      $this->disponible = $this->cantidad - $this->reservada;
   }

   public function get($id)
   {
      $data = $this->db->select("SELECT * FROM ".$this->table_name." WHERE idstock = ".$this->var2str($id).";");
      if($data)
      {
         return new \stock($data[0]);
      }
      else
         return FALSE;
   }

   public function get_by_referencia($ref)
   {
      $data = $this->db->select("SELECT * FROM ".$this->table_name." WHERE referencia = ".$this->var2str($ref).";");
      if($data)
      {
         return new \stock($data[0]);
      }
      else
         return FALSE;
   }

   public function exists()
   {
      if( is_null($this->idstock) )
      {
         return FALSE;
      }
      else
         return $this->db->select("SELECT * FROM ".$this->table_name." WHERE idstock = ".$this->var2str($this->idstock).";");
   }

   public function save()
   {
      $this->cantidad = round($this->cantidad, 3);
      $this->reservada = round($this->reservada, 3);
      $this->disponible = $this->cantidad - $this->reservada;

      if( $this->exists() )
      {
         $sql = "UPDATE ".$this->table_name." SET codalmacen = ".$this->var2str($this->codalmacen)
                 .", referencia = ".$this->var2str($this->referencia)
                 .", nombre = ".$this->var2str($this->nombre)
                 .", cantidad = ".$this->var2str($this->cantidad)
                 .", reservada = ".$this->var2str($this->reservada)
                 .", disponible = ".$this->var2str($this->disponible)
                 .", pterecibir = ".$this->var2str($this->pterecibir)
                 .", stockmin = ".$this->var2str($this->stockmin)
                 .", stockmax = ".$this->var2str($this->stockmax)
                 .", cantidadultreg = ".$this->var2str($this->cantidadultreg)
                 .", ubicacion = ".$this->var2str($this->ubicacion)
                 ."  WHERE idstock = ".$this->var2str($this->idstock).";";

         return $this->db->exec($sql);
      }
      else
      {
         $sql = "INSERT INTO ".$this->table_name." (codalmacen,referencia,nombre,cantidad,reservada,
            disponible,pterecibir,stockmin,stockmax,cantidadultreg,ubicacion) VALUES
                   (".$this->var2str($this->codalmacen)
                 .",".$this->var2str($this->referencia)
                 .",".$this->var2str($this->nombre)
                 .",".$this->var2str($this->cantidad)
                 .",".$this->var2str($this->reservada)
                 .",".$this->var2str($this->disponible)
                 .",".$this->var2str($this->pterecibir)
                 .",".$this->var2str($this->stockmin)
                 .",".$this->var2str($this->stockmax)
                 .",".$this->var2str($this->cantidadultreg)
                 .",".$this->var2str($this->ubicacion).");";

         if( $this->db->exec($sql) )
         {
            $this->idstock = $this->db->lastval();
            return TRUE;
         }
         else
            return FALSE;
      }
   }

   public function delete()
   {
      return $this->db->exec("DELETE FROM ".$this->table_name." WHERE idstock = ".$this->var2str($this->idstock).";");
   }

   public function all_from_articulo($ref)
   {
      $stocklist = array();

      $data = $this->db->select("SELECT * FROM ".$this->table_name." WHERE referencia = ".$this->var2str($ref)." ORDER BY codalmacen ASC;");
      if($data)
      {
         foreach($data as $s)
         {
            $stocklist[] = new \stock($s);
         }
      }

      return $stocklist;
   }

   public function get_by_almacen($ref, $codalmacen)
   {
      $item = false;
      $data = $this->db->select("SELECT * FROM ".$this->table_name.
            " WHERE codalmacen = ".$this->var2str($codalmacen)." AND referencia = ".$this->var2str($ref).
            " ORDER BY referencia ASC;");
      if($data)
      {
         $item = new \stock($data[0]);
      }
      return $item;
   }

   public function total_from_articulo($ref, $codalmacen = FALSE)
   {
      $num = 0;
      $sql = "SELECT SUM(cantidad) as total FROM ".$this->table_name." WHERE referencia = ".$this->var2str($ref);

      if($codalmacen)
      {
         $sql .= " AND codalmacen = ".$this->var2str($codalmacen);
      }

      $data = $this->db->select($sql);
      if($data)
      {
         $num = round( floatval($data[0]['total']), 3);
      }

      return $num;
   }

   public function count()
   {
      $num = 0;

      $data = $this->db->select("SELECT COUNT(idstock) as total FROM ".$this->table_name.";");
      if($data)
      {
         $num = intval($data[0]['total']);
      }

      return $num;
   }

   public function count_by_articulo()
   {
      $num = 0;

      $data = $this->db->select("SELECT COUNT(DISTINCT referencia) as total FROM ".$this->table_name.";");
      if($data)
      {
         $num = intval($data[0]['total']);
      }

      return $num;
   }

   /**
    * Comprueba el stock de cada uno de los artículos del documento.
    * Devuelve TRUE si hay suficiente stock.
    * Originalmente esta función está en ventas_pedido
    * en el plugin presupuestos_y_pedidos
    * pero se puede extender y hacer obligatorio que todas los modelos
    * que tengan que ver con documentos tengan una funcion get_lineas()
    * con eso podemos crear y estandarizar multiples tipos de documentos
    * @return boolean
    */
   private function comprobar_stock($documento)
   {
      $ok = TRUE;

      $art0 = new articulo();
      foreach($documento->get_lineas() as $linea)
      {
         if($linea->referencia)
         {
            $articulo = $art0->get($linea->referencia);
            if($articulo)
            {
               if(!$articulo->controlstock)
               {
                  if($linea->cantidad > $articulo->stockfis)
                  {
                     /// si se pide más cantidad de la disponible, es que no hay suficiente
                     $ok = FALSE;
                  }
                  else
                  {
                     /// comprobamos el stock en el almacén del pedido
                     $ok = FALSE;
                     foreach($articulo->get_stock() as $stock)
                     {
                        if($stock->codalmacen == $documento->codalmacen)
                        {
                           if($stock->cantidad >= $linea->cantidad)
                           {
                              $ok = TRUE;
                           }
                           break;
                        }
                     }
                  }

                  if(!$ok)
                  {
                     $this->new_error_msg('No hay suficiente stock del artículo '.$linea->referencia);
                     break;
                  }
               }
            }
         }
      }

      return $ok;
   }

   /**
    * Obtenemos los movimientos de cada articulo
    * @param type $ref
    * @param type $almacen
    * @param type $desde
    * @param type $hasta
    * @return array
    */
   public function get_movimientos($ref, $desde, $hasta, $codalmacen=false, $codagente=false)
   {

      $sql_extra = '';
      $rango_fecha = '';
      if($desde)
      {
         $sql_extra .= " AND fecha >= ".$this->var2str(\date('Y-m-d',strtotime($desde)));
         $rango_fecha .= " AND fecha >= ".$this->var2str(\date('Y-m-d',strtotime($desde)));
      }

      if($hasta)
      {
         $sql_extra .= " AND fecha <= ".$this->var2str(\date('Y-m-d',strtotime($hasta)));
         $rango_fecha .= " AND fecha <= ".$this->var2str(\date('Y-m-d',strtotime($hasta)));
      }

      if($codagente)
      {
         $sql_extra .= " AND codagente = ".$this->var2str($codagente);
      }

      if($codalmacen)
      {
         $sql_extra .= " AND codalmacen = ".$this->var2str($codalmacen);
      }
      $mlist = array();

      if( !isset($this->regularizaciones) )
      {
         $reg = new regularizacion_stock();
         $this->regularizaciones = $reg->all_from_articulo($ref);
      }

      foreach($this->regularizaciones as $reg)
      {
         $continue = false;
         /// Solo tomamos las regularizaciones del almacén actual
         // Si la variable codalmacen tiene datos
         if($codalmacen AND ($reg->codalmacendest == $codalmacen))
         {
            $continue = true;
         }
         // Si está vacia tambien tomamos datos
         elseif(!$codalmacen)
         {
            $continue = TRUE;
         }

         if($continue)
         {
            $mlist[] = array(
               'codalmacen' => $reg->codalmacendest,
               'referencia' => $ref,
               'origen' => 'Regularización',
               'clipro' => '-',
               'url' => '#stock',
               'inicial' => $reg->cantidadini,
               'movimiento' => $reg->cantidadfin - $reg->cantidadini,
               'precio' => 0,
               'dto' => 0,
               'final' => $reg->cantidadfin,
               'fecha' => $reg->fecha,
               'hora' => $reg->hora
            );
         }
      }

      /// nos guardamos la lista de tablas para agilizar
      $tablas = $this->db->list_tables();

      if( $this->db->table_exists('transstock', $tablas) AND $this->db->table_exists('lineastransstock', $tablas) )
      {
         $sql_almdest = " codalmadestino != ''";
         if($codalmacen)
         {
            $sql_almdest .= " codalmadestino = ".$this->var2str($codalmacen);
         }
         //Buscamos los ingresos por transferencia
         $sql = "select codalmadestino as codalmacen, l.idtrans, fecha, hora, referencia, cantidad "
                 ." FROM lineastransstock AS ls "
                 ." JOIN transstock as l ON (ls.idtrans = l.idtrans) "
                 ." WHERE ".$sql_almdest.$rango_fecha
                 ." AND ls.referencia = ".$this->var2str($ref)
                 ." ORDER by l.idtrans;";
         $data = $this->db->select($sql);
         if($data)
         {
            foreach($data as $d)
            {
               $mlist[] = array(
                   'codalmacen' => $d['codalmacen'],
                   'referencia' => $ref,
                   'clipro' => '-',
                   'origen' => 'Ingreso por transferencia '.$d['idtrans'],
                   'url' => 'index.php?page=editar_transferencia_stock&id='.intval($d['idtrans']),
                   'inicial' => 0,
                   'movimiento' => floatval($d['cantidad']),
                   'precio' => 0,
                   'dto' => 0,
                   'final' => 0,
                   'fecha' => date('d-m-Y', strtotime($d['fecha'])),
                   'hora' => date('H:i:s', strtotime($d['hora']))
               );
            }
         }

         $sql_almorig = " codalmaorigen != ''";
         if($codalmacen)
         {
            $sql_almorig .= " codalmaorigen = ".$this->var2str($codalmacen);
         }
         //Buscamos las salidas por transferencia
         $sql = "select codalmaorigen as codalmacen, l.idtrans, fecha, hora, referencia, cantidad "
                 ." FROM lineastransstock AS ls "
                 ." JOIN transstock as l ON (ls.idtrans = l.idtrans) "
                 ." WHERE ".$sql_almorig.$rango_fecha
                 ." AND ls.referencia = ".$this->var2str($ref)
                 ." ORDER by l.idtrans;";
         $data = $this->db->select($sql);
         if($data)
         {
            foreach($data as $d)
            {
               $mlist[] = array(
                   'codalmacen' => $d['codalmacen'],
                   'referencia' => $ref,
                   'origen' => 'Salida por transferencia '.$d['idtrans'],
                   'clipro' => '-',
                   'url' => 'index.php?page=editar_transferencia_stock&id='.intval($d['idtrans']),
                   'inicial' => 0,
                   'movimiento' => 0-floatval($d['cantidad']),
                   'precio' => 0,
                   'dto' => 0,
                   'final' => 0,
                   'fecha' => date('d-m-Y', strtotime($d['fecha'])),
                   'hora' => date('H:i:s', strtotime($d['hora']))
               );
            }
         }
      }

      if( $this->db->table_exists('albaranesprov', $tablas) AND $this->db->table_exists('lineasalbaranesprov', $tablas) )
      {
         /// buscamos el artículo en albaranes de compra
         $sql = "SELECT a.idalbaran,a.codigo,codproveedor,nombre,l.cantidad,l.dtopor,l.pvpunitario,a.fecha,a.hora,a.codalmacen "
            ." FROM albaranesprov a, lineasalbaranesprov l "
            ." WHERE a.idalbaran = l.idalbaran "
            .$sql_extra
            ." AND l.referencia = ".$this->var2str($ref);
         $data = $this->db->select($sql);
         if($data)
         {
            foreach($data as $d)
            {
               $mlist[] = array(
                   'codalmacen' => $d['codalmacen'],
                   'referencia' => $ref,
                   'origen' => ucfirst(FS_ALBARAN).' compra '.$d['codigo'],
                   'clipro' => $d['codproveedor'].' - '.$d['nombre'],
                   'url' => 'index.php?page=compras_albaran&id='.intval($d['idalbaran']),
                   'inicial' => 0,
                   'movimiento' => floatval($d['cantidad']),
                   'precio' => floatval($d['pvpunitario']),
                   'dto' => floatval($d['dtopor']),
                   'final' => 0,
                   'fecha' => date('d-m-Y', strtotime($d['fecha'])),
                   'hora' => date('H:i:s', strtotime($d['hora']))
               );
            }
         }
      }

      if( $this->db->table_exists('facturasprov', $tablas) AND $this->db->table_exists('lineasfacturasprov', $tablas) )
      {
         /// buscamos el artículo en facturas de compra
         $sql = "SELECT f.idfactura,f.codigo,codproveedor,nombre,l.cantidad,l.dtopor,l.pvpunitario,f.fecha,f.hora,f.codalmacen "
            ." FROM facturasprov f, lineasfacturasprov l "
            ." WHERE f.idfactura = l.idfactura AND l.idalbaran IS NULL "
            .$sql_extra
            ." AND l.referencia = ".$this->var2str($ref);

         $data = $this->db->select($sql);
         if($data)
         {
            foreach($data as $d)
            {
               $mlist[] = array(
                   'codalmacen' => $d['codalmacen'],
                   'referencia' => $ref,
                   'origen' => 'Factura compra '.$d['codigo'],
                   'clipro' => $d['codproveedor'].' - '.$d['nombre'],
                   'url' => 'index.php?page=compras_factura&id='.intval($d['idfactura']),
                   'inicial' => 0,
                   'movimiento' => floatval($d['cantidad']),
                   'precio' => floatval($d['pvpunitario']),
                   'dto' => floatval($d['dtopor']),
                   'final' => 0,
                   'fecha' => date('d-m-Y', strtotime($d['fecha'])),
                   'hora' => date('H:i:s', strtotime($d['hora']))
               );
            }
         }
      }

      if( $this->db->table_exists('albaranescli', $tablas) AND $this->db->table_exists('lineasalbaranescli', $tablas) )
      {
         /// buscamos el artículo en albaranes de venta
         $sql = "SELECT a.idalbaran,a.codigo,codcliente,nombrecliente,l.cantidad,l.dtopor,l.pvpunitario,a.fecha,a.hora,a.codalmacen "
            ." FROM albaranescli a, lineasalbaranescli l "
            ." WHERE a.idalbaran = l.idalbaran "
            .$sql_extra
            ." AND l.referencia = ".$this->var2str($ref);

         $data = $this->db->select($sql);
         if($data)
         {
            foreach($data as $d)
            {
               $mlist[] = array(
                   'codalmacen' => $d['codalmacen'],
                   'referencia' => $ref,
                   'origen' => ucfirst(FS_ALBARAN).' venta '.$d['codigo'],
                   'clipro' => $d['codcliente'].' - '.$d['nombrecliente'],
                   'url' => 'index.php?page=ventas_albaran&id='.intval($d['idalbaran']),
                   'inicial' => 0,
                   'movimiento' => 0-floatval($d['cantidad']),
                   'precio' => floatval($d['pvpunitario']),
                   'dto' => floatval($d['dtopor']),
                   'final' => 0,
                   'fecha' => date('d-m-Y', strtotime($d['fecha'])),
                   'hora' => date('H:i:s', strtotime($d['hora']))
               );
            }
         }
      }

      if( $this->db->table_exists('facturascli', $tablas) AND $this->db->table_exists('lineasfacturascli', $tablas) )
      {
         /// buscamos el artículo en facturas de venta
         $sql = "SELECT f.idfactura,f.codigo,codcliente,nombrecliente,l.cantidad,l.dtopor,l.pvpunitario,f.fecha,f.hora,f.codalmacen "
            ." FROM facturascli f, lineasfacturascli l "
            ." WHERE f.idfactura = l.idfactura AND l.idalbaran IS NULL "
            .$sql_extra
            ." AND l.referencia = ".$this->var2str($ref);

         $data = $this->db->select($sql);
         if($data)
         {
            foreach($data as $d)
            {
               $mlist[] = array(
                   'codalmacen' => $d['codalmacen'],
                   'referencia' => $ref,
                   'origen' => 'Factura venta '.$d['codigo'],
                   'clipro' => $d['codcliente'].' - '.$d['nombrecliente'],
                   'url' => 'index.php?page=ventas_factura&id='.intval($d['idfactura']),
                   'inicial' => 0,
                   'movimiento' => 0-floatval($d['cantidad']),
                   'precio' => floatval($d['pvpunitario']),
                   'dto' => floatval($d['dtopor']),
                   'final' => 0,
                   'fecha' => date('d-m-Y', strtotime($d['fecha'])),
                   'hora' => date('H:i:s', strtotime($d['hora']))
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

      /// recalculamos las cantidades finales hacia atrás
      $final = $this->total_from_articulo($ref, $codalmacen);
      for($i = count($mlist) - 1; $i >= 0; $i--)
      {
         $mlist[$i]['final'] = $final;
         $final -= $mlist[$i]['movimiento'];
         $mlist[$i]['inicial'] = $final;
      }

      return $mlist;
   }

   /**
    * Recalculamos el saldo de un artículo y almacén en base a los documentos que muevan
    * @param type $ref
    * @param type $almacen
    * @param type $total_saldo
    * @return type integer
    */
   public function saldo_articulo($ref, $almacen, $total_saldo = 0)
   {
      $continue = FALSE;
      $tablas = $this->db->list_tables();
      $total_ingresos = 0;
      //Facturas de compra sin albaran
      $sql_compras1 = "SELECT sum(cantidad) as total FROM lineasfacturasprov as lfp" .
              " JOIN facturasprov as fp on (fp.idfactura = lfp.idfactura)" .
              " WHERE anulada = FALSE and idalbaran IS NULL " .
              " AND codalmacen = " . $this->var2str($almacen) .
              " AND referencia = " . $this->var2str($ref);
      $data_Compras1 = $this->db->select($sql_compras1);
      if ($data_Compras1)
      {
         $total_ingresos += $data_Compras1[0]['total'];
         $continue = TRUE;
      }

      //Albaranes de compra
      $sql_compras2 = "SELECT sum(cantidad) as total FROM lineasalbaranesprov as lap" .
              " JOIN albaranesprov as ap on (ap.idalbaran = lap.idalbaran)" .
              " WHERE codalmacen = " . $this->var2str($almacen) .
              " AND referencia = " . $this->var2str($ref);
      $data_Compras2 = $this->db->select($sql_compras2);
      if ($data_Compras2)
      {
         $total_ingresos += $data_Compras2[0]['total'];
         $continue = TRUE;
      }

      $total_salidas = 0;
      //Facturas de venta sin albaran
      $sql_ventas1 = "SELECT sum(cantidad) as total FROM lineasfacturascli as lfc" .
              " JOIN facturascli as fc on (fc.idfactura = lfc.idfactura)" .
              " WHERE anulada = FALSE and idalbaran IS NULL " .
              " AND codalmacen = " . $this->var2str($almacen) .
              " AND referencia = " . $this->var2str($ref);
      $data_Ventas1 = $this->db->select($sql_ventas1);
      if ($data_Ventas1)
      {
         $total_salidas += $data_Ventas1[0]['total'];
         $continue = TRUE;
      }

      //Albaranes de venta
      $sql_ventas2 = "SELECT sum(cantidad) as total FROM lineasalbaranescli as lac" .
              " JOIN albaranescli as ac on (ac.idalbaran = lac.idalbaran)" .
              " WHERE codalmacen = " . $this->var2str($almacen) .
              " AND referencia = " . $this->var2str($ref);
      $data_Ventas2 = $this->db->select($sql_ventas2);
      if ($data_Ventas2)
      {
         $total_salidas += $data_Ventas2[0]['total'];
         $continue = TRUE;
      }

      //Si existen estas tablas se genera la información de las transferencias de stock
      if ($this->db->table_exists('transstock', $tablas) AND $this->db->table_exists('lineastransstock', $tablas))
      {
         /*
          * Generamos la informacion de las transferencias por ingresos entre almacenes que se hayan hecho a los stocks
          */
         $sql_transstock1 = "select sum(cantidad) as total FROM lineastransstock AS ls" .
                 " JOIN transstock as l ON(ls.idtrans = l.idtrans) " .
                 " WHERE codalmadestino = " . $this->var2str($almacen) .
                 " AND referencia = " . $this->var2str($ref);
         $data_transstock1 = $this->db->select($sql_transstock1);
         if ($data_transstock1)
         {
            $total_ingresos += $data_transstock1[0]['total'];
            $continue = TRUE;
         }

         /*
          * Generamos la informacion de las transferencias por salidas entre almacenes que se hayan hecho a los stocks
          */
         $sql_transstock2 = "select sum(cantidad) as total FROM lineastransstock AS ls " .
                 " JOIN transstock as l ON(ls.idtrans = l.idtrans) " .
                 " WHERE codalmaorigen = " . $this->var2str($almacen) .
                 " AND referencia = " . $this->var2str($ref);
         $data_transstock2 = $this->db->select($sql_transstock2);
         if ($data_transstock2)
         {
            $total_salidas += $data_transstock2[0]['total'];
            $continue = TRUE;
         }
      }

      //Si existe esta tabla se genera la información de las regularizaciones de stock y se agrega como salida el resultado
      if ($this->db->table_exists('lineasregstocks', $tablas))
      {
         $sql_regstocks = "select sum(cantidadini-cantidadfin) as total from lineasregstocks AS ls " .
                 " JOIN stocks as l ON(ls.idstock = l.idstock) " .
                 " WHERE codalmacen = " . $this->var2str($almacen) .
                 " AND referencia = " . $this->var2str($ref);
         $data_regstocks = $this->db->select($sql_regstocks);
         if ($data_regstocks)
         {
            $total_salidas += $data_regstocks[0]['total'];
            $continue = TRUE;
         }
      }

      if($continue)
      {
         $total_saldo += ($total_ingresos - $total_salidas);
         $stock = $this->get_by_almacen($ref, $almacen);
         if($stock)
         {
            $stock->cantidad = $total_saldo;
            $stock->disponible = $total_saldo;
            $stock->save();
         }
         else
         {
            $stock = new \stock();
            $stock->referencia = $ref;
            $stock->codalmacen = $almacen;
            $stock->cantidad = $total_saldo;
            $stock->disponible = $total_saldo;
            $stock->save();
         }
      }
      return $continue;
   }

}
