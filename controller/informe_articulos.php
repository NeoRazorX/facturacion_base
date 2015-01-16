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
require_model('linea_albaran_cliente.php');
require_model('linea_albaran_proveedor.php');

class informe_articulos extends fs_controller
{
   public $articulo;
   private $offset;
   public $pestanya;
   public $resultados;
   public $stats;
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
         $linea_alb_cli = new linea_albaran_cliente();
         $linea_alb_pro = new linea_albaran_proveedor();
         $this->top_ventas = $this->top_articulo_albcli();
         $this->top_compras = $this->top_articulo_albpro();
      }
      else if($this->pestanya == 'stock')
      {
         $this->resultados = $this->stock($this->offset);
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
   
   private function top_articulo_albcli()
   {
      $toplist = $this->cache->get_array('albcli_top_articulos');
      if( !$toplist )
      {
         $articulo = new articulo();
         $lineas = $this->db->select_limit("SELECT referencia, SUM(cantidad) as ventas
            FROM lineasalbaranescli GROUP BY referencia ORDER BY ventas DESC", FS_ITEM_LIMIT, 0);
         if($lineas)
         {
            foreach($lineas as $l)
            {
               $art0 = $articulo->get($l['referencia']);
               if($art0)
                  $toplist[] = array($art0, intval($l['ventas']));
            }
         }
         $this->cache->set('albcli_top_articulos', $toplist);
      }
      return $toplist;
   }
   
   private function top_articulo_albpro()
   {
      $toplist = $this->cache->get('albpro_top_articulos');
      if( !$toplist )
      {
         $articulo = new articulo();
         $lineas = $this->db->select_limit("SELECT referencia, SUM(cantidad) as compras
            FROM lineasalbaranesprov GROUP BY referencia ORDER BY compras DESC", FS_ITEM_LIMIT, 0);
         if($lineas)
         {
            foreach($lineas as $l)
            {
               $art0 = $articulo->get($l['referencia']);
               if($art0)
                  $toplist[] = array($art0, intval($l['compras']));
            }
         }
         $this->cache->set('albpro_top_articulos', $toplist);
      }
      return $toplist;
   }
   
   private function stock($offset = 0)
   {
      $slist = array();
      
      $sql = "SELECT codalmacen,s.referencia,a.descripcion,s.cantidad,a.stockmin,a.stockmax "
              . "FROM stocks s, articulos a WHERE s.referencia = a.referencia ORDER BY referencia ASC";
      
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
      $extra = '&tab=stock';
      
      if($this->offset>'0')
      {
         $url = $this->url()."&offset=".($this->offset-FS_ITEM_LIMIT).$extra;
      }
      
      return $url;
   }
   
   public function siguiente_url()
   {
      $url = '';
      $extra = '&tab=stock';
      
      if(count($this->resultados) == FS_ITEM_LIMIT)
      {
         $url = $this->url()."&offset=".($this->offset+FS_ITEM_LIMIT).$extra;
      }
      
      return $url;
   }
}
