<?php

/*
 * This file is part of FacturaScripts
 * Copyright (C) 2016  Carlos Garcia Gomez  neorazorx@gmail.com
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

/**
 * Description of dashboard
 *
 * @author carlos
 */
class dashboard extends fs_controller
{
   public $anterior;
   public $anyo;
   public $mes;
   public $neto;
   public $noticias;
   public $trimestre;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Dashboard', 'informes', FALSE, TRUE, TRUE);
   }
   
   protected function private_core()
   {
      /// cargamos la configuración
      $fsvar = new fs_var();
      if( isset($_POST['anterior']) )
      {
         $this->anterior = $_POST['anterior'];
         $fsvar->simple_save('dashboard_anterior', $this->anterior);
         
         $this->neto = $_POST['neto'];
         $fsvar->simple_save('dashboard_neto', $this->neto);
         
         $this->new_message('Datos guardados correctamente.');
      }
      else
      {
         $this->anterior = $fsvar->simple_get('dashboard_anterior');
         if(!$this->anterior)
         {
            $this->anterior = 'periodo';
         }
         
         $this->neto = $fsvar->simple_get('dashboard_neto');
         if(!$this->neto)
         {
            $this->neto = 'FALSE';
         }
      }
      
      $this->mes = array(
          'desde' => date('1-m-Y'),
          'hasta' => date('d-m-Y'),
          'anterior' => '-1 month',
          'compras' => 0,
          'compras_neto' => 0,
          'compras_anterior' => 0,
          'compras_anterior_neto' => 0,
          'compras_mejora' => 0,
          'compras_albaranes_pte' => 0,
          'compras_pedidos_pte' => 0,
          'compras_sinpagar' => 0,
          'ventas' => 0,
          'ventas_neto' => 0,
          'ventas_anterior' => 0,
          'ventas_anterior_neto' => 0,
          'ventas_mejora' => 0,
          'ventas_albaranes_pte' => 0,
          'ventas_pedidos_pte' => 0,
          'ventas_sinpagar' => 0,
          'impuestos' => 0,
          'impuestos_anterior' => 0,
          'impuestos_mejora' => 0,
          'beneficios' => 0,
          'beneficios_anterior' => 0,
          'beneficios_mejora' => 0,
      );
      if( intval( date('d') ) <= 3 )
      {
         /// en los primeros días del mes, mejor comparamos los datos del anterior
         $this->mes['desde'] = date('1-m-Y', strtotime('-1 month'));
         $this->mes['hasta'] = date('t-m-Y', strtotime('-1 month'));
      }
      if($this->anterior == 'año')
      {
         $this->mes['anterior'] = '-1 year';
      }
      $this->calcula_periodo($this->mes, TRUE);
      
      $this->trimestre = array(
          'anterior' => '-3 months',
          'compras' => 0,
          'compras_neto' => 0,
          'compras_anterior' => 0,
          'compras_anterior_neto' => 0,
          'compras_mejora' => 0,
          'ventas' => 0,
          'ventas_neto' => 0,
          'ventas_anterior' => 0,
          'ventas_anterior_neto' => 0,
          'ventas_mejora' => 0,
          'impuestos' => 0,
          'impuestos_anterior' => 0,
          'impuestos_mejora' => 0,
          'beneficios' => 0,
          'beneficios_anterior' => 0,
          'beneficios_mejora' => 0,
      );
      
      /// ahora hay que calcular bien las fechas del trimestre
      switch( date('m') )
      {
         case '1':
            $this->trimestre['desde'] = date('1-10-Y', strtotime('-1 year'));
            $this->trimestre['hasta'] = date('t-12-Y', strtotime('-1 year'));
            break;
         
         case '2':
         case '3':
         case '4':
            $this->trimestre['desde'] = date('1-1-Y');
            $this->trimestre['hasta'] = date('t-3-Y');
            break;
         
         case '5':
         case '6':
         case '7':
            $this->trimestre['desde'] = date('1-4-Y');
            $this->trimestre['hasta'] = date('t-6-Y');
            break;
         
         case '8':
         case '9':
         case '10':
            $this->trimestre['desde'] = date('1-7-Y');
            $this->trimestre['hasta'] = date('t-9-Y');
            break;
         
         case '11':
         case '12':
            $this->trimestre['desde'] = date('1-10-Y');
            $this->trimestre['hasta'] = date('t-12-Y');
            break;
      }
      if($this->anterior == 'año')
      {
         $this->trimestre['anterior'] = '-1 year';
      }
      $this->calcula_periodo($this->trimestre);
      
      $this->anyo = array(
          'desde' => date('1-1-Y'),
          'hasta' => date('d-m-Y'),
          'anterior' => '-1 year',
          'compras' => 0,
          'compras_neto' => 0,
          'compras_anterior' => 0,
          'compras_anterior_neto' => 0,
          'compras_mejora' => 0,
          'ventas' => 0,
          'ventas_neto' => 0,
          'ventas_anterior' => 0,
          'ventas_anterior_neto' => 0,
          'ventas_mejora' => 0,
          'impuestos' => 0,
          'impuestos_anterior' => 0,
          'impuestos_mejora' => 0,
          'beneficios' => 0,
          'beneficios_anterior' => 0,
          'beneficios_mejora' => 0,
      );
      $this->calcula_periodo($this->anyo);
      
      $this->leer_noticias();
   }
   
   private function calcula_periodo(&$stats, $pendiente = FALSE)
   {
      if( $this->db->table_exists('facturascli') )
      {
         /// calculamos las ventas de este mes
         $sql = "select sum(totaleuros) as total, sum(neto/tasaconv) as neto from facturascli where"
                 . " fecha >= ".$this->empresa->var2str($stats['desde'])
                 . " and fecha <= ".$this->empresa->var2str($stats['hasta']).";";
         
         $data = $this->db->select($sql);
         if($data)
         {
            $stats['ventas'] = $this->euro_convert( floatval($data[0]['total']) );
            $stats['ventas_neto'] = $this->euro_convert( floatval($data[0]['neto']) );
         }
         
         /// calculamos las ventas del mes pasado
         $sql = "select sum(totaleuros) as total, sum(neto/tasaconv) as neto from facturascli where"
                 . " fecha >= ".$this->empresa->var2str(date('d-m-Y', strtotime($stats['desde'].' '.$stats['anterior'])))
                 . " and fecha <= ".$this->empresa->var2str(date('d-m-Y', strtotime($stats['hasta'].' '.$stats['anterior']))).";";
         
         $data = $this->db->select($sql);
         if($data)
         {
            $stats['ventas_anterior'] = $this->euro_convert( floatval($data[0]['total']) );
            $stats['ventas_anterior_neto'] = $this->euro_convert( floatval($data[0]['neto']) );
            
            if($this->neto)
            {
               $stats['ventas_mejora'] = $this->calcular_diff($stats['ventas_neto'], $stats['ventas_anterior_neto']);
            }
            else
            {
               $stats['ventas_mejora'] = $this->calcular_diff($stats['ventas'], $stats['ventas_anterior']);
            }
         }
         
         /// calculamos los impuestos de las ventas de este mes
         $sql = "select sum((totaliva+totalrecargo-totalirpf)/tasaconv) as total from facturascli where"
                 . " fecha >= ".$this->empresa->var2str($stats['desde'])
                 . " and fecha <= ".$this->empresa->var2str($stats['hasta']).";";
         
         $data = $this->db->select($sql);
         if($data)
         {
            $stats['impuestos'] = $this->euro_convert( floatval($data[0]['total']) );
         }
         
         /// calculamos los impuestos de las ventas del mes anterior
         $sql = "select sum((totaliva+totalrecargo-totalirpf)/tasaconv) as total from facturascli where"
                 . " fecha >= ".$this->empresa->var2str(date('d-m-Y', strtotime($stats['desde'].' '.$stats['anterior'])))
                 . " and fecha <= ".$this->empresa->var2str(date('d-m-Y', strtotime($stats['hasta'].' '.$stats['anterior']))).";";
         
         $data = $this->db->select($sql);
         if($data)
         {
            $stats['impuestos_anterior'] = $this->euro_convert( floatval($data[0]['total']) );
         }
      }
      
      if( $this->db->table_exists('facturasprov') )
      {
         /// calculamos las compras de este mes
         $sql = "select sum(totaleuros) as total, sum(neto/tasaconv) as neto from facturasprov where"
                 . " fecha >= ".$this->empresa->var2str($stats['desde'])
                 . " and fecha <= ".$this->empresa->var2str($stats['hasta']).";";
         
         $data = $this->db->select($sql);
         if($data)
         {
            $stats['compras'] = $this->euro_convert( floatval($data[0]['total']) );
            $stats['compras_neto'] = $this->euro_convert( floatval($data[0]['neto']) );
         }
         
         /// calculamos las compras del mes pasado
         $sql = "select sum(totaleuros) as total, sum(neto/tasaconv) as neto from facturasprov where"
                 . " fecha >= ".$this->empresa->var2str(date('d-m-Y', strtotime($stats['desde'].' '.$stats['anterior'])))
                 . " and fecha <= ".$this->empresa->var2str(date('d-m-Y', strtotime($stats['hasta'].' '.$stats['anterior']))).";";
         
         $data = $this->db->select($sql);
         if($data)
         {
            $stats['compras_anterior'] = $this->euro_convert( floatval($data[0]['total']) );
            $stats['compras_anterior_neto'] = $this->euro_convert( floatval($data[0]['neto']) );
            
            if($this->neto)
            {
               $stats['compras_mejora'] = $this->calcular_diff($stats['compras_neto'], $stats['compras_anterior_neto']);
            }
            else
            {
               $stats['compras_mejora'] = $this->calcular_diff($stats['compras'], $stats['compras_anterior']);
            }
         }
         
         /// calculamos los impuestos de las compras de este mes
         $sql = "select sum((totaliva+totalrecargo-totalirpf)/tasaconv) as total from facturasprov where"
                 . " fecha >= ".$this->empresa->var2str($stats['desde'])
                 . " and fecha <= ".$this->empresa->var2str($stats['hasta']).";";
         
         $data = $this->db->select($sql);
         if($data)
         {
            $stats['impuestos'] -= $this->euro_convert( floatval($data[0]['total']) );
            if($stats['impuestos'] < 0)
            {
               $stats['impuestos'] = 0;
            }
         }
         
         /// calculamos los impuestos de las compras del mes anterior
         $sql = "select sum((totaliva+totalrecargo-totalirpf)/tasaconv) as total from facturasprov where"
                 . " fecha >= ".$this->empresa->var2str(date('d-m-Y', strtotime($stats['desde'].' '.$stats['anterior'])))
                 . " and fecha <= ".$this->empresa->var2str(date('d-m-Y', strtotime($stats['hasta'].' '.$stats['anterior']))).";";
         
         $data = $this->db->select($sql);
         if($data)
         {
            $stats['impuestos_anterior'] -= $this->euro_convert( floatval($data[0]['total']) );
            if($stats['impuestos_anterior'] < 0)
            {
               $stats['impuestos_anterior'] = 0;
            }
         }
      }
      
      if( $this->db->table_exists('co_partidas') AND $this->empresa->codpais == 'ESP' )
      {
         /// calculamos el saldo de todos aquellos asientos que afecten a caja y no se correspondan con facturas
         $sql = "select sum(debe-haber) as total from co_partidas where codsubcuenta LIKE '57%' and idasiento"
                 . " in (select idasiento from co_asientos where tipodocumento IS NULL"
                 . " and fecha >= ".$this->empresa->var2str($stats['desde'])
                 . " and fecha <= ".$this->empresa->var2str($stats['hasta']).");";
         
         $data = $this->db->select($sql);
         if($data)
         {
            $saldo = floatval($data[0]['total']);
            if($saldo < 0)
            {
               $stats['impuestos'] += abs($saldo);
            }
         }
         
         /// calculamos el saldo de todos aquellos asientos que afecten a caja y no se correspondan con facturas
         $sql = "select sum(debe-haber) as total from co_partidas where codsubcuenta LIKE '57%' and idasiento"
                 . " in (select idasiento from co_asientos where tipodocumento IS NULL"
                 . " and fecha >= ".$this->empresa->var2str(date('d-m-Y', strtotime($stats['desde'].' '.$stats['anterior'])))
                 . " and fecha <= ".$this->empresa->var2str(date('d-m-Y', strtotime($stats['hasta'].' '.$stats['anterior']))).");";
         
         $data = $this->db->select($sql);
         if($data)
         {
            $saldo = floatval($data[0]['total']);
            if($saldo < 0)
            {
               $stats['impuestos_anterior'] += abs($saldo);
            }
         }
      }
      
      if($pendiente)
      {
         if( $this->db->table_exists('facturascli') )
         {
            /// calculamos las facturas de venta vencidas hasta hoy
            $sql = "select sum(totaleuros) as total, sum(neto/tasaconv) as neto from facturascli"
                    . " where pagada = false and vencimiento <= ".$this->empresa->var2str($stats['hasta']).";";
            
            $data = $this->db->select($sql);
            if($data)
            {
               if($this->neto)
               {
                  $stats['ventas_sinpagar'] = $this->euro_convert( floatval($data[0]['neto']) );
               }
               else
               {
                  $stats['ventas_sinpagar'] = $this->euro_convert( floatval($data[0]['total']) );
               }
            }
         }
         
         if( $this->db->table_exists('albaranescli') )
         {
            /// calculamos los albaranes de venta pendientes
            $sql = "select sum(totaleuros) as total, sum(neto/tasaconv) as neto from albaranescli"
                    . " where idfactura IS NULL;";
            
            $data = $this->db->select($sql);
            if($data)
            {
               if($this->neto)
               {
                  $stats['ventas_albaranes_pte'] = $this->euro_convert( floatval($data[0]['neto']) );
               }
               else
               {
                  $stats['ventas_albaranes_pte'] = $this->euro_convert( floatval($data[0]['total']) );
               }
            }
         }
         
         if( $this->db->table_exists('pedidoscli') )
         {
            /// calculamos los pedidos de venta pendientes
            $sql = "select sum(totaleuros) as total, sum(neto/tasaconv) as neto from pedidoscli"
                    . " where status = 0;";
            
            $data = $this->db->select($sql);
            if($data)
            {
               if($this->neto)
               {
                  $stats['ventas_pedidos_pte'] = $this->euro_convert( floatval($data[0]['neto']) );
               }
               else
               {
                  $stats['ventas_pedidos_pte'] = $this->euro_convert( floatval($data[0]['total']) );
               }
            }
         }
         
         if( $this->db->table_exists('facturasprov') )
         {
            /// calculamos las facturas de compra sin pagar
            $sql = "select sum(totaleuros) as total, sum(neto/tasaconv) as neto from facturasprov"
                    . " where pagada = false;";
            
            $data = $this->db->select($sql);
            if($data)
            {
               if($this->neto)
               {
                  $stats['compras_sinpagar'] = $this->euro_convert( floatval($data[0]['neto']) );
               }
               else
               {
                  $stats['compras_sinpagar'] = $this->euro_convert( floatval($data[0]['total']) );
               }
            }
         }
         
         if( $this->db->table_exists('albaranesprov') )
         {
            /// calculamos los albaranes de compra pendientes
            $sql = "select sum(totaleuros) as total, sum(neto/tasaconv) as neto from albaranesprov"
                    . " where idfactura IS NULL;";
            
            $data = $this->db->select($sql);
            if($data)
            {
               if($this->neto)
               {
                  $stats['compras_albaranes_pte'] = $this->euro_convert( floatval($data[0]['neto']) );
               }
               else
               {
                  $stats['compras_albaranes_pte'] = $this->euro_convert( floatval($data[0]['total']) );
               }
            }
         }
         
         if( $this->db->table_exists('pedidosprov') )
         {
            /// calculamos los pedidos de compra pendientes
            $sql = "select sum(totaleuros) as total, sum(neto/tasaconv) as neto from pedidosprov"
                    . " where idalbaran IS NULL;";
            
            $data = $this->db->select($sql);
            if($data)
            {
               if($this->neto)
               {
                  $stats['compras_pedidos_pte'] = $this->euro_convert( floatval($data[0]['neto']) );
               }
               else
               {
                  $stats['compras_pedidos_pte'] = $this->euro_convert( floatval($data[0]['total']) );
               }
            }
         }
      }
      
      $stats['impuestos_mejora'] = $this->calcular_diff($stats['impuestos'], $stats['impuestos_anterior']);
      
      $stats['beneficios'] = $stats['ventas'] - $stats['compras'] - $stats['impuestos'];
      $stats['beneficios_anterior'] = $stats['ventas_anterior'] - $stats['compras_anterior'] - $stats['impuestos_anterior'];
      $stats['beneficios_mejora'] = $this->calcular_diff($stats['beneficios'], $stats['beneficios_anterior']);
   }
   
   private function calcular_diff($nuevo, $anterior)
   {
      if($nuevo == 0 OR $anterior == 0)
      {
         if($nuevo == 0 AND $anterior > 0)
         {
            return -100;
         }
         else if($nuevo > 0 AND $anterior == 0)
         {
            return 100;
         }
         else
            return 0;
      }
      else if($nuevo != $anterior)
      {
         if($anterior < 0)
         {
            return 0 - (($nuevo*100/$anterior) - 100);
         }
         else
         {
            return ($nuevo*100/$anterior) - 100;
         }
      }
      else
      {
         return 0;
      }
   }
   
   private function leer_noticias()
   {
      $this->noticias = $this->cache->get_array('community_changelog');
      if(!$this->noticias)
      {
         $data = $this->curl_get_contents(FS_COMMUNITY_URL.'/index.php?page=community_changelog&json=TRUE');
         if($data)
         {
            $this->noticias = json_decode($data);
            
            /// guardamos en caché
            $this->cache->set('community_changelog', $this->noticias);
         }
      }
   }
   
   /**
    * Descarga el contenido con curl o file_get_contents
    * @param type $url
    * @param type $timeout
    * @return type
    */
   private function curl_get_contents($url)
   {
      if( function_exists('curl_init') )
      {
         $ch = curl_init();
         curl_setopt($ch, CURLOPT_URL, $url);
         curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
         curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
         curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
         curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
         $data = curl_exec($ch);
         $info = curl_getinfo($ch);
         
         if($info['http_code'] == 301 OR $info['http_code'] == 302)
         {
            $redirs = 0;
            return $this->curl_redirect_exec($ch, $redirs);
         }
         else
         {
            curl_close($ch);
            return $data;
         }
      }
      else
         return file_get_contents($url);
   }
   
   /**
    * Función alternativa para cuando el followlocation falla.
    * @param type $ch
    * @param type $redirects
    * @param type $curlopt_header
    * @return type
    */
   private function curl_redirect_exec($ch, &$redirects, $curlopt_header = false)
   {
      curl_setopt($ch, CURLOPT_HEADER, true);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      $data = curl_exec($ch);
      $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      
      if($http_code == 301 || $http_code == 302)
      {
         list($header) = explode("\r\n\r\n", $data, 2);
         $matches = array();
         preg_match("/(Location:|URI:)[^(\n)]*/", $header, $matches);
         $url = trim(str_replace($matches[1], "", $matches[0]));
         $url_parsed = parse_url($url);
         if( isset($url_parsed) )
         {
            curl_setopt($ch, CURLOPT_URL, $url);
            $redirects++;
            return $this->curl_redirect_exec($ch, $redirects, $curlopt_header);
         }
      }
      
      if($curlopt_header)
      {
         curl_close($ch);
         return $data;
      }
      else
      {
         list(, $body) = explode("\r\n\r\n", $data, 2);
         curl_close($ch);
         return $body;
      }
   }
}
