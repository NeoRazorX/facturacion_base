<?php
/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2014-2015  Carlos Garcia Gomez  neorazorx@gmail.com
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
require_model('linea_factura_cliente.php');
require_model('linea_factura_proveedor.php');

class informe_articulos extends fs_controller
{
   public $articulo;
   private $offset;
   public $pestanya;
   public $resultados;
   public $sin_vender;
   public $stats;
   public $tipo_stock;
   public $top_ventas;
   public $top_compras;

   public function __construct()
   {
      parent::__construct(__CLASS__, 'Artículos', 'informes', FALSE, TRUE);
   }
   
   protected function process()
   {
      $this->pestanya = 'stats';
      if( isset($_GET['tab']) )
      {
         $this->pestanya = $_GET['tab'];
      }
      
      $this->offset = 0;
      if( isset($_GET['offset']) )
      {
         $this->offset = intval($_GET['offset']);
      }
      
      if($this->pestanya == 'stats')
      {
         $this->articulo = new articulo();
         $this->stats = $this->stats();
         
         $linea_fac_cli = new linea_factura_cliente();
         $linea_fac_pro = new linea_factura_proveedor();
         
         $this->top_ventas = $this->top_articulo_faccli();
         $this->sin_vender = $this->sin_vender();
         $this->top_compras = $this->top_articulo_facpro();
      }
      else if($this->pestanya == 'stock')
      {
         $this->tipo_stock = 'todo';
         if( isset($_GET['tipo']) )
         {
            $this->tipo_stock = $_GET['tipo'];
         }
         
         if( isset($_GET['download']) )
         {
            $this->template = FALSE;
            
            header("content-type:application/csv;charset=UTF-8");
            header("Content-Disposition: attachment; filename=\"stock.csv\"");
            echo "almacen,referencia,descripcion,stock,stockmin,stockmax\n";
            
            $offset = 0;
            $resultados = $this->stock($offset, $this->tipo_stock);
            while( count($resultados) > 0 )
            {
               foreach($resultados as $res)
               {
                  echo '"'.$res['codalmacen'].'","'.$res['referencia'].'","'.$res['descripcion'].'","'.
                          $res['cantidad'].'","'.$res['stockmin'].'","'.$res['stockmax']."\"\n";
                  $offset++;
               }
               
               $resultados = $this->stock($offset, $this->tipo_stock);
            }
         }
         else
            $this->resultados = $this->stock($this->offset, $this->tipo_stock);
      }
   }
   
   private function stats()
   {
      $stats = array(
          'total' => 0,
          'con_stock' => 0,
          'bloqueados' => 0,
          'publicos' => 0,
          'factualizado' => Date('d-m-Y', strtotime(0) )
      );
      
      $aux = $this->db->select("SELECT GREATEST( COUNT(referencia), 0) as art,
         GREATEST( SUM(case when stockfis > 0 then 1 else 0 end), 0) as stock,
         GREATEST( SUM(".$this->db->sql_to_int('bloqueado')."), 0) as bloq,
         GREATEST( SUM(".$this->db->sql_to_int('publico')."), 0) as publi,
         MAX(factualizado) as factualizado FROM articulos;");
      if($aux)
      {
         $stats['total'] = intval($aux[0]['art']);
         $stats['con_stock'] = intval($aux[0]['stock']);
         $stats['bloqueados'] = intval($aux[0]['bloq']);
         $stats['publicos'] = intval($aux[0]['publi']);
         $stats['factualizado'] = Date('d-m-Y', strtotime($aux[0]['factualizado']) );
      }
      
      return $stats;
   }
   
   private function top_articulo_faccli()
   {
      $toplist = $this->cache->get_array('faccli_top_articulos');
      if( !$toplist )
      {
         $articulo = new articulo();
         $lineas = $this->db->select_limit("SELECT referencia, SUM(cantidad) as ventas
            FROM lineasfacturascli GROUP BY referencia ORDER BY ventas DESC", FS_ITEM_LIMIT, 0);
         if($lineas)
         {
            foreach($lineas as $l)
            {
               $art0 = $articulo->get($l['referencia']);
               if($art0)
                  $toplist[] = array($art0, intval($l['ventas']));
            }
         }
         $this->cache->set('faccli_top_articulos', $toplist);
      }
      return $toplist;
   }
   
   private function sin_vender()
   {
      $toplist = $this->cache->get_array('top_articulos_sin_vender');
      if( !$toplist )
      {
         $articulo = new articulo();
         $lineas = $this->db->select_limit("SELECT * FROM articulos WHERE stockfis > 0 AND sevende AND
            referencia NOT IN (SELECT referencia FROM lineasfacturascli
            WHERE idfactura IN (SELECT idfactura FROM facturascli
            WHERE fecha >= ".$articulo->var2str(Date('1-m-Y')).")) ORDER BY stockfis DESC", FS_ITEM_LIMIT, 0);
         if($lineas)
         {
            foreach($lineas as $l)
               $toplist[] = new articulo($l);
         }
         $this->cache->set('top_articulos_sin_vender', $toplist);
      }
      return $toplist;
   }
   
   private function top_articulo_facpro()
   {
      $toplist = $this->cache->get('facpro_top_articulos');
      if( !$toplist )
      {
         $articulo = new articulo();
         $lineas = $this->db->select_limit("SELECT referencia, SUM(cantidad) as compras
            FROM lineasfacturasprov GROUP BY referencia ORDER BY compras DESC", FS_ITEM_LIMIT, 0);
         if($lineas)
         {
            foreach($lineas as $l)
            {
               $art0 = $articulo->get($l['referencia']);
               if($art0)
                  $toplist[] = array($art0, intval($l['compras']));
            }
         }
         $this->cache->set('facpro_top_articulos', $toplist);
      }
      return $toplist;
   }
   
   private function stock($offset = 0, $tipo = 'todo')
   {
      $slist = array();
      
      $sql = "SELECT codalmacen,s.referencia,a.descripcion,s.cantidad,a.stockmin,a.stockmax "
              . "FROM stocks s, articulos a WHERE s.referencia = a.referencia";
      
      if($tipo == 'min')
      {
         $sql .= " AND s.cantidad < a.stockmin";
      }
      else if($tipo == 'max')
      {
         $sql .= " AND a.stockmax > 0 AND s.cantidad > a.stockmax";
      }
      
      $sql .= " ORDER BY referencia ASC";
      
      $data = $this->db->select_limit($sql, FS_ITEM_LIMIT, $offset);
      if($data)
      {
         foreach($data as $d)
            $slist[] = $d;
      }
      
      return $slist;
   }
   
   public function anterior_url()
   {
      $url = '';
      $extra = '&tab=stock&tipo='.$this->tipo_stock;
      
      if($this->offset>'0')
      {
         $url = $this->url()."&offset=".($this->offset-FS_ITEM_LIMIT).$extra;
      }
      
      return $url;
   }
   
   public function siguiente_url()
   {
      $url = '';
      $extra = '&tab=stock&tipo='.$this->tipo_stock;
      
      if(count($this->resultados) == FS_ITEM_LIMIT)
      {
         $url = $this->url()."&offset=".($this->offset+FS_ITEM_LIMIT).$extra;
      }
      
      return $url;
   }
}
